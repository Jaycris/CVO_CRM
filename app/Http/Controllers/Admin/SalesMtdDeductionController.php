<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\SalesMtdDeduction;
use App\Models\User;
use App\Support\BrandScope;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SalesMtdDeductionController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = Carbon::createFromFormat('!Y-m', $validated['month'] ?? now()->format('Y-m'));

        return view('admin.sales-mtd-deductions.index', [
            'month' => $month,
            'agents' => User::with('brand')->where('department', 'Sales')->orderBy('first_name')->orderBy('last_name')->get(),
            'deductions' => SalesMtdDeduction::with(['agent', 'brand', 'creator', 'voidedBy'])
                ->whereDate('effective_month', $month->toDateString())
                ->latest()->latest('id')->paginate(AppSetting::recordsPerPage())->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate([
            'request_key' => ['required', 'uuid'],
            'agent_id' => ['required', 'integer', Rule::exists('users', 'id')->where('department', 'Sales')->whereNull('deleted_at')],
            'month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $agent = User::findOrFail($validated['agent_id']);
        $deduction = SalesMtdDeduction::firstOrCreate(['request_key' => $validated['request_key']], [
            'agent_id' => $agent->id,
            'brand_id' => BrandScope::userBrandId($agent),
            'effective_month' => Carbon::createFromFormat('!Y-m', $validated['month']),
            'amount' => $validated['amount'], 'reason' => $validated['reason'], 'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.sales-mtd-deductions.index', ['month' => $deduction->effective_month->format('Y-m')])
            ->with('success', 'Sales MTD deduction recorded. Related commissions have been recalculated; original sales and payments are unchanged.');
    }

    public function void(Request $request, SalesMtdDeduction $deduction): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['void_reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $deduction, $validated) {
            $entry = SalesMtdDeduction::whereKey($deduction->id)->lockForUpdate()->firstOrFail();
            if (! $entry->voided_at) {
                $entry->forceFill([
                    'voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $validated['void_reason'],
                ])->save();
            }
        });

        return redirect()->route('admin.sales-mtd-deductions.index', ['month' => $deduction->effective_month->format('Y-m')])
            ->with('success', 'Deduction voided. The audit record has been kept.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);
        abort_unless(Schema::hasTable('sales_mtd_deductions'), 503, 'Run the latest database migrations before managing MTD deductions.');
    }
}
