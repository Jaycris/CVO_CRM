<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\User;
use App\Notifications\LeadAutoReturnedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AutoReturnUntouchedLeads
{
    private const RELEASE_REASON = 'Lead auto-returned after no sales activity.';

    public function handle(?int $hours = null): int
    {
        $hours ??= AppSetting::autoReturnUntouchedLeadsHours();

        if ($hours <= 0) {
            return 0;
        }

        $cutoff = now()->subHours($hours);
        $leads = $this->eligibleLeads($cutoff);

        if ($leads->isEmpty()) {
            return 0;
        }

        $agentGroups = $leads->groupBy('assigned_to');
        $managerRecipients = $this->managerRecipients($leads);

        DB::transaction(function () use ($leads): void {
            $now = now();

            $leads->each(function (Lead $lead) use ($now): void {
                $previousAgentId = $lead->assigned_to;

                $lead->forceFill([
                    'previous_agent_id' => $previousAgentId,
                    'previous_agent_released_at' => $now,
                    'previous_agent_release_reason' => self::RELEASE_REASON,
                    'assigned_to' => null,
                    'assigned_date' => null,
                    'returned_at' => null,
                    'returned_by' => null,
                    'return_notes' => null,
                    'sales_stage' => null,
                    'sales_stage_updated_at' => null,
                    'lead_generation_stage' => 'ready_to_assign',
                    'verification_assigned_to' => null,
                ])->save();

                LeadAssignmentHistory::query()
                    ->where('lead_id', $lead->id)
                    ->whereNull('released_at')
                    ->update([
                        'released_at' => $now,
                        'released_by' => null,
                        'release_reason' => self::RELEASE_REASON,
                    ]);
            });
        });

        $this->notifyAgents($agentGroups);
        $this->notifyManagers($managerRecipients, $leads);

        return $leads->count();
    }

    private function eligibleLeads(\Carbon\CarbonInterface $cutoff): Collection
    {
        return Lead::query()
            ->with(['assignedUser', 'brand'])
            ->whereNotNull('assigned_to')
            ->whereNull('returned_at')
            ->whereNull('archived_at')
            ->whereNull('disposed_at')
            ->whereNull('sales_stage')
            ->whereNull('sales_stage_updated_at')
            ->where(function (Builder $query): void {
                $query->whereNull('sales_notes')
                    ->orWhereRaw("TRIM(COALESCE(sales_notes, '')) = ''");
            })
            ->whereHas('assignedUser', function (Builder $query): void {
                $query->where('is_commission_eligible', true)
                    ->whereNull('suspended_at');
            })
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereHas('assignmentHistories', function (Builder $history) use ($cutoff): void {
                    $history->whereNull('released_at')
                        ->where('assigned_at', '<=', $cutoff);
                })->orWhere(function (Builder $fallback) use ($cutoff): void {
                    $fallback->whereDoesntHave('assignmentHistories', function (Builder $history): void {
                        $history->whereNull('released_at');
                    })->whereDate('assigned_date', '<=', $cutoff->toDateString());
                });
            })
            ->orderBy('id')
            ->get();
    }

    private function managerRecipients(Collection $leads): Collection
    {
        $brandIds = $leads->pluck('brand_id')->filter()->unique()->values();

        return User::query()
            ->with(['role.permissionRecords', 'permissionOverrides', 'brand'])
            ->whereNull('suspended_at')
            ->get()
            ->filter(function (User $user) use ($brandIds): bool {
                if (! $this->canReceiveManagerNotice($user)) {
                    return false;
                }

                if (BrandScope::canAccessAllBrands($user)) {
                    return true;
                }

                return $brandIds->contains((int) $user->brand_id);
            })
            ->values();
    }

    private function canReceiveManagerNotice(User $user): bool
    {
        return $user->role?->name === 'Admin'
            || $user->hasPermission('assign_leads')
            || $user->hasPermission('reassign_team_leads');
    }

    private function notifyAgents(Collection $agentGroups): void
    {
        $agents = User::query()
            ->with(['role.permissionRecords', 'permissionOverrides'])
            ->whereIn('id', $agentGroups->keys()->filter()->all())
            ->whereNull('suspended_at')
            ->get()
            ->keyBy('id');

        $agentGroups->each(function (Collection $leads, int|string $agentId) use ($agents): void {
            $agent = $agents->get((int) $agentId);

            if (! $agent || ! $agent->hasPermission('view_assigned_leads')) {
                return;
            }

            $agent->notify(new LeadAutoReturnedNotification(
                $leads->count() === 1 ? $leads->first() : null,
                $leads->count(),
            ));
        });
    }

    private function notifyManagers(Collection $recipients, Collection $leads): void
    {
        $recipients->each(function (User $user) use ($leads): void {
            $user->notify(new LeadAutoReturnedNotification(
                $leads->count() === 1 ? $leads->first() : null,
                $leads->count(),
                true,
            ));
        });
    }
}
