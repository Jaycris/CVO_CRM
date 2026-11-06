<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\CommissionRelease;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\SalesTarget;
use App\Models\User;
use App\Support\BrandScope;
use App\Support\SalesMtdCalculator;
use App\Support\SalesMtdDeductions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommissionReleaseController extends Controller
{
    public function sync(Request $request)
    {
        abort_unless(self::canManage($request->user()), 403);
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m'], 'agent_id' => ['nullable', 'integer', 'exists:users,id']]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month']);
        $count = app(\App\Support\PhremsCommissionReleases::class)->syncMonth($month, $validated['agent_id'] ?? null);
        return back()->with('success', "Fetched PHREMS release status for {$count} commission share(s). Any unknown matches require review before a new dispute.");
    }

    public static function canManage(?User $user): bool
    {
        return $user?->role?->name === 'Admin';
    }

    public function index(Request $request)
    {
        abort_unless(self::canManage($request->user()), 403);
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m'], 'agent_id' => ['nullable', 'integer']]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now()->format('Y-m'));
        $agentId = $validated['agent_id'] ?? null;
        $activities = SalesActivity::with(['brand', 'agent.commissionProfile.rules', 'frankieAgent.commissionProfile.rules', 'service', 'payment'])
            ->where('payment_status', 'Payment Success')->whereNotNull('sales_payment_id')
            ->whereDate('sold_date', '>=', $month->toDateString())->whereDate('sold_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->when($agentId, fn ($query) => $query->where(fn ($query) => $query->where('agent_id', $agentId)->orWhere('frankie_agent_id', $agentId)))
            ->get();
        $rows = $this->calculateRows($activities, $month, $request->user())
            ->filter(fn ($row) => $row['activity']->payment && (! $agentId || $row['agent_id'] == $agentId))
            ->sortByDesc(fn ($row) => $row['activity']->sold_date->timestamp)->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = AppSetting::leadsSalesRecordsPerPage();
        $rows = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $history = CommissionRelease::with('creator')->whereIn('sales_payment_id', collect($rows->items())->pluck('activity.sales_payment_id'))
            ->latest('id')->get()->groupBy(fn ($entry) => $entry->sales_payment_id.':'.$entry->agent_id);

        return view('reports.commission-releases', [
            'month' => $month, 'agentId' => $agentId, 'rows' => $rows, 'history' => $history,
            'agents' => User::where('department', 'Sales')->tap(fn ($query) => BrandScope::apply($query, $request->user()))->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(self::canManage($request->user()), 403);
        $validated = $request->validate([
            'sales_payment_id' => ['required', 'integer'], 'agent_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['unreleased', 'released'])],
            'released_at' => ['nullable', 'required_if:status,released', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        DB::transaction(function () use ($request, $validated) {
            // Payment updates and release confirmations take the same lock before deciding dispute treatment.
            $payment = SalesPayment::query()->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->whereKey($validated['sales_payment_id'])->lockForUpdate()->firstOrFail();
            $activity = SalesActivity::where('sales_payment_id', $payment->id)->firstOrFail();
            abort_unless((int) $activity->agent_id === (int) $validated['agent_id']
                || ((int) $activity->frankie_agent_id === (int) $validated['agent_id'] && (float) $activity->frankie_credit_amount > 0), 403);
            if ($payment->status !== 'Payment Success') {
                throw ValidationException::withMessages(['status' => 'Release status can only be confirmed for a successful payment. Resolve the dispute before changing its release status.']);
            }
            $last = CommissionRelease::latestFor($payment->id, $validated['agent_id']);
            if ($last?->status === $validated['status']) return;
            $month = $activity->sold_date->copy()->startOfMonth();
            $activities = SalesActivity::with(['agent.commissionProfile.rules', 'frankieAgent.commissionProfile.rules', 'service'])
                ->where('payment_status', 'Payment Success')
                ->whereDate('sold_date', '>=', $month->toDateString())->whereDate('sold_date', '<=', $month->copy()->endOfMonth()->toDateString())
                ->where(fn ($query) => $query->where('agent_id', $validated['agent_id'])->orWhere('frankie_agent_id', $validated['agent_id']))->get();
            $row = $this->calculateRows($activities, $month)->first(fn ($row) => $row['activity']->id === $activity->id && $row['agent_id'] == $validated['agent_id']);
            abort_unless($row, 422);
            if ($validated['status'] === 'released' && $validated['released_at'] < $activity->sold_date->toDateString()) {
                throw ValidationException::withMessages(['released_at' => 'The release date cannot be before the sale date.']);
            }
            CommissionRelease::create([
                'sales_payment_id' => $payment->id, 'agent_id' => $validated['agent_id'], 'brand_id' => $activity->brand_id,
                'status' => $validated['status'], 'released_at' => $validated['status'] === 'released' ? $validated['released_at'] : null,
                'commission_breakdown' => collect($row)->except(['activity', 'agent', 'commission_profile'])->all(),
                'reason' => $validated['reason'], 'created_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Commission release status confirmed. The confirmation and calculation have been saved for audit.');
    }

    private function calculateRows($activities, Carbon $month, ?User $viewer = null)
    {
        $targets = SalesTarget::whereDate('target_month', $month->toDateString())->where('target_type', 'agent')->get();
        $targets = SalesMtdCalculator::agentTargetRows($targets);
        return SalesMtdCalculator::withMtdDeductions(SalesMtdCalculator::statementRows($activities, $targets), $targets, SalesMtdDeductions::entries($month, $viewer));
    }
}
