<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        $this->authorizeViewAccess();

        $teams = Team::with(['brand', 'manager', 'teamLeader', 'members.role'])
            ->withCount('members')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.teams.index', [
            'teams' => $teams,
            'canManageTeams' => $this->canManageTeams(),
            'brands' => Brand::orderByRaw("imprint_name = 'CreatiVision Outsourcing' desc")->orderBy('imprint_name')->get(),
            'parentBrandId' => Brand::where('imprint_name', 'CreatiVision Outsourcing')->value('id'),
            'departments' => Department::orderBy('name')->pluck('name')->all(),
            'users' => User::with(['role', 'brand'])->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManageAccess();

        $validated = $this->validatedTeamData($request);
        $team = Team::create(collect($validated)->except('member_ids')->all());
        $this->syncMembers($team, $validated['member_ids'] ?? []);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function update(Request $request, Team $team)
    {
        $this->authorizeManageAccess();

        $validated = $this->validatedTeamData($request, $team);
        $team->update(collect($validated)->except('member_ids')->all());
        $this->syncMembers($team, $validated['member_ids'] ?? []);

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        $this->authorizeManageAccess();

        if ($team->members()->exists()) {
            return redirect()
                ->route('admin.teams.index')
                ->with('error', 'This team still has members. Move the users first before deleting it.');
        }

        $team->delete();

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team deleted successfully.');
    }

    private function validatedTeamData(Request $request, ?Team $team = null): array
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'exists:brands,id'],
            'department' => ['required', Rule::in(Department::orderBy('name')->pluck('name')->all())],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')
                    ->where('brand_id', $request->input('brand_id'))
                    ->where('department', $request->input('department'))
                    ->ignore($team),
            ],
            'manager_id' => ['nullable', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->ensureUsersMatchTeamData($validated);

        return $validated;
    }

    private function ensureUsersMatchTeamData(array $validated): void
    {
        $userIds = collect([
                $validated['manager_id'] ?? null,
                $validated['team_leader_id'] ?? null,
            ])
            ->merge($validated['member_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();

        if ($userIds->isEmpty()) {
            return;
        }

        $parentBrandId = Brand::where('imprint_name', 'CreatiVision Outsourcing')->value('id');
        $isParentBrand = (int) $validated['brand_id'] === (int) $parentBrandId;

        $invalidUserExists = User::whereIn('id', $userIds)
            ->where(function ($query) use ($validated, $isParentBrand) {
                $query->where('department', '!=', $validated['department']);

                if (! $isParentBrand) {
                    $query->orWhere('brand_id', '!=', $validated['brand_id']);
                }
            })
            ->exists();

        if ($invalidUserExists) {
            throw ValidationException::withMessages([
                'member_ids' => 'Selected manager, team leader, and members must match the team brand and department.',
            ]);
        }
    }

    private function syncMembers(Team $team, array $memberIds): void
    {
        $memberIds = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $team->members()
            ->when($memberIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $memberIds))
            ->update(['team_id' => null]);

        if ($memberIds->isNotEmpty()) {
            User::whereIn('id', $memberIds)
                ->update(['team_id' => $team->id]);
        }
    }

    private function authorizeViewAccess(): void
    {
        abort_unless(
            request()->user()?->role?->name === 'Admin'
            || request()->user()?->hasPermission('view_all_teams')
            || request()->user()?->hasPermission('manage_teams'),
            403
        );
    }

    private function authorizeManageAccess(): void
    {
        abort_unless($this->canManageTeams(), 403);
    }

    private function canManageTeams(): bool
    {
        return request()->user()?->role?->name === 'Admin'
            || request()->user()?->hasPermission('manage_teams');
    }
}
