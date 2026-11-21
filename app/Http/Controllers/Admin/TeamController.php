<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Support\BrandScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        $this->authorizeViewAccess();

        $teams = Team::with(['brand', 'accessibleBrands', 'manager', 'teamLeader', 'members.role'])
            ->withCount('members')
            ->latest()
            ->paginate(\App\Models\AppSetting::recordsPerPage())
            ->withQueryString();

        return view('admin.teams.index', [
            'teams' => $teams,
            'canManageTeams' => $this->canManageTeams(),
            'brands' => Brand::orderByRaw("imprint_name = 'CreatiVision Outsourcing' desc")->orderBy('imprint_name')->get(),
            'parentBrandId' => Brand::where('imprint_name', 'CreatiVision Outsourcing')->value('id'),
            'departments' => Department::orderBy('name')->pluck('name')->all(),
            'users' => User::with(['role', 'brand', 'accessibleBrands'])->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManageAccess();

        $validated = $this->validatedTeamData($request);
        DB::transaction(function () use ($validated) {
            $team = Team::create(collect($validated)->except(['member_ids', 'allowed_brand_ids'])->all());
            $team->accessibleBrands()->sync($validated['multiple_brands_enabled'] ? $validated['allowed_brand_ids'] : []);
            $this->syncMembers($team, $validated['member_ids'] ?? []);
        });

        return redirect()
            ->route('admin.teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function update(Request $request, Team $team)
    {
        $this->authorizeManageAccess();

        $validated = $this->validatedTeamData($request, $team);
        DB::transaction(function () use ($team, $validated) {
            $team->update(collect($validated)->except(['member_ids', 'allowed_brand_ids'])->all());
            $team->accessibleBrands()->sync($validated['multiple_brands_enabled'] ? $validated['allowed_brand_ids'] : []);
            $this->syncMembers($team, $validated['member_ids'] ?? []);
        });

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
            'multiple_brands_enabled' => ['nullable', 'boolean'],
            'allowed_brand_ids' => [Rule::requiredIf($request->boolean('multiple_brands_enabled')), 'nullable', 'array',
                ...($request->boolean('multiple_brands_enabled') ? ['min:2'] : [])],
            'allowed_brand_ids.*' => ['integer', 'distinct', Rule::exists('brands', 'id')->whereNull('deleted_at')->where('is_sales_brand', true)],
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
            'show_on_sales_dashboard' => ['nullable', 'boolean'],
        ]);

        $validated['show_on_sales_dashboard'] = $validated['department'] === 'Sales'
            && $request->boolean('show_on_sales_dashboard');
        $validated['multiple_brands_enabled'] = $request->boolean('multiple_brands_enabled');
        if ($validated['multiple_brands_enabled'] && Brand::find($validated['brand_id'])?->is_sales_brand
            && ! in_array((int) $validated['brand_id'], array_map('intval', $validated['allowed_brand_ids']), true)) {
            throw ValidationException::withMessages(['allowed_brand_ids' => 'Include the primary sales brand in the eligible brands.']);
        }

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

        $brandIds = $validated['multiple_brands_enabled']
            ? array_map('intval', $validated['allowed_brand_ids']) : [(int) $validated['brand_id']];
        $selectedUsers = User::with(['brand', 'accessibleBrands'])->whereIn('id', $userIds)->get();
        $invalidUserExists = $selectedUsers->count() !== $userIds->count()
            || $selectedUsers->contains(fn ($user) => $user->department !== $validated['department']
                || (! ($isParentBrand && ! $validated['multiple_brands_enabled']) && ! BrandScope::canAccessAllBrands($user)
                    && ! array_intersect($brandIds, BrandScope::allowedBrandIds($user))));

        if ($invalidUserExists) {
            throw ValidationException::withMessages([
                'member_ids' => 'Selected manager, team leader, and members must match the team department and have access to at least one eligible team brand.',
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
