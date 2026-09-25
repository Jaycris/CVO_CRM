<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\User;
use App\Support\BrandScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadGenerationActivityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
                || (bool) $request->user()?->hasPermission('view_lead_generation_activity'),
            403
        );

        $date = $request->date('date') ?? now();
        $dateString = $date->toDateString();

        $leadGenerationUsers = User::query()
            ->with(['role.permissionRecords', 'permissionOverrides'])
            ->whereDoesntHave('role', fn ($query) => $query->where('name', 'Admin'))
            ->where(function ($query) {
                $query->where('department', 'Lead Generation')
                    ->orWhereHas('role', fn ($query) => $query->whereIn('name', ['Lead Miner', 'Verifier']))
                    ->orWhereHas('role.permissionRecords', fn ($query) => $query->whereIn('key', [
                        'create_leads',
                        'send_leads_to_verification',
                        'verify_leads',
                        'view_verification_queue',
                    ]));
            })
            ->withCount([
                'minedLeads as mined_today_count' => function ($query) use ($request, $dateString) {
                    BrandScope::apply($query, $request->user());
                    $query->whereDate('created_at', $dateString);
                },
                'minedLeads as mined_month_count' => function ($query) use ($request, $date) {
                    BrandScope::apply($query, $request->user());
                    $query->whereBetween('created_at', [
                        $date->copy()->startOfMonth(),
                        $date->copy()->endOfMonth(),
                    ]);
                },
            ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(function (User $user) use ($request, $date) {
                $user->setAttribute('verified_today_count', $this->verifierActivityCount(
                    $user,
                    $request,
                    $date->copy()->startOfDay(),
                    $date->copy()->endOfDay(),
                ));
                $user->setAttribute('verified_month_count', $this->verifierActivityCount(
                    $user,
                    $request,
                    $date->copy()->startOfMonth(),
                    $date->copy()->endOfMonth(),
                ));

                return $user;
            });

        $leadMiners = $leadGenerationUsers
            ->filter(fn (User $user) => $user->role?->name === 'Lead Miner'
                || $user->hasPermission('create_leads')
                || $user->hasPermission('send_leads_to_verification')
                || (int) $user->mined_today_count > 0
                || (int) $user->mined_month_count > 0)
            ->sortByDesc('mined_today_count')
            ->values();

        $verifiers = $leadGenerationUsers
            ->filter(fn (User $user) => $user->role?->name === 'Verifier'
                || $user->hasPermission('verify_leads')
                || $user->hasPermission('view_verification_queue')
                || (int) $user->verified_today_count > 0
                || (int) $user->verified_month_count > 0)
            ->sortByDesc('verified_today_count')
            ->values();

        $totalMinedToday = $leadMiners->sum('mined_today_count');
        $totalVerifiedToday = $verifiers->sum('verified_today_count');

        return view('reports.lead-generation-activity', compact(
            'date',
            'dateString',
            'leadMiners',
            'verifiers',
            'totalMinedToday',
            'totalVerifiedToday'
        ));
    }

    private function leadQuery(Request $request)
    {
        return Lead::query()
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()));
    }

    private function verifierActivityCount(User $user, Request $request, $start, $end): int
    {
        return $this->leadQuery($request)
            ->where(function ($query) use ($user) {
                $query->where('verified_by', $user->id)
                    ->orWhere('verification_assigned_to', $user->id);
            })
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('verified_at', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->whereBetween('updated_at', [$start, $end])
                            ->where(function ($query) {
                                $query->where('verify_score', '>=', 25)
                                    ->orWhereNotNull('verification_notes')
                                    ->orWhere('author_confirmed', true)
                                    ->orWhere('book_confirmed', true)
                                    ->orWhere('phone_confirmed', true)
                                    ->orWhere('email_confirmed', true)
                                    ->orWhereNotNull('phone_number_statuses')
                                    ->orWhereNotNull('verified_phone_numbers');
                            });
                    });
            })
            ->count();
    }
}
