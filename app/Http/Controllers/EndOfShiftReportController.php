<?php

namespace App\Http\Controllers;

use App\Models\EndOfShiftReport;
use App\Models\User;
use App\Notifications\EndOfShiftReportSubmittedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EndOfShiftReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->canOpenPage($user), 403);

        $reports = EndOfShiftReport::query()
            ->with(['user.brand', 'reportToUser'])
            ->tap(fn (Builder $query) => $this->applyVisibility($query, $user))
            ->when($request->filled('date'), fn (Builder $query) => $query->whereDate('shift_date', $request->date('date')))
            ->latest('shift_date')
            ->latest('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('production.eos.index', [
            'reports' => $reports,
            'canSubmitEos' => (bool) $user?->enable_end_of_shift_report,
            'canViewAllEos' => $this->isAdmin($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless((bool) $user?->enable_end_of_shift_report, 403);

        $validated = $request->validate([
            'shift_date' => ['required', 'date'],
            'work_done' => ['required', 'string', 'max:5000'],
            'pending_work' => ['nullable', 'string', 'max:5000'],
            'blockers' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $reportToUser = $this->reportToUser($user);

        $report = EndOfShiftReport::create([
            'user_id' => $user->id,
            'report_to_user_id' => $reportToUser?->id,
            'report_to_hris_employee_id' => $user->reports_to_hris_employee_id,
            'shift_date' => $validated['shift_date'],
            'work_done' => $validated['work_done'],
            'pending_work' => $validated['pending_work'] ?? null,
            'blockers' => $validated['blockers'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'submitted_at' => now(),
        ])->load('user');

        try {
            $this->notifyReportViewers($report, $reportToUser);
        } catch (\Throwable $exception) {
            Log::warning('End of Shift report notification failed after report was saved.', [
                'report_id' => $report->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'End of Shift report submitted successfully.');
    }

    private function canOpenPage(?User $user): bool
    {
        return $this->isAdmin($user)
            || (bool) $user?->enable_end_of_shift_report
            || $this->hasHrisDirectReports($user);
    }

    private function applyVisibility(Builder $query, ?User $user): void
    {
        if ($this->isAdmin($user)) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user?->id)
                ->orWhere('report_to_user_id', $user?->id);

            if ($user?->hris_employee_id) {
                $query->orWhere('report_to_hris_employee_id', $user->hris_employee_id);
            }
        });
    }

    private function hasHrisDirectReports(?User $user): bool
    {
        if (! $user?->hris_employee_id) {
            return false;
        }

        return User::query()
            ->where('reports_to_hris_employee_id', $user->hris_employee_id)
            ->exists();
    }

    private function reportToUser(User $user): ?User
    {
        if (! $user->reports_to_hris_employee_id) {
            return null;
        }

        return User::query()
            ->where('hris_employee_id', $user->reports_to_hris_employee_id)
            ->first();
    }

    private function notifyReportViewers(EndOfShiftReport $report, ?User $reportToUser): void
    {
        $report->loadMissing('user');

        $recipients = User::query()
            ->with('role')
            ->whereNull('suspended_at')
            ->where(function (Builder $query) use ($reportToUser): void {
                $query->whereHas('role', fn (Builder $role) => $role->where('name', 'Admin'));

                if ($reportToUser) {
                    $query->orWhereKey($reportToUser->id);
                }
            })
            ->get()
            ->reject(fn (User $user) => (int) $user->id === (int) $report->user_id);

        $recipients->each(fn (User $user) => $user->notify(new EndOfShiftReportSubmittedNotification($report)));
    }

    private function isAdmin(?User $user): bool
    {
        return $user?->role?->name === 'Admin';
    }
}
