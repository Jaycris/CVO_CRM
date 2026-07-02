<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\UserInvitationMail;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Lead;
use App\Models\LeadAssignmentHistory;
use App\Models\Permission;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class UserController extends Controller
{
    public function index()
    {
        $this->ensureAdmin();

        $users = User::with(['role', 'brand', 'team'])->withCount([
            'assignedLeads as active_assigned_leads_count' => fn ($query) => $query
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->where(function ($query) {
                    $query->whereNull('sales_stage')
                        ->orWhereIn('sales_stage', ['pipeline', 'prospect', 'scheduled_callback']);
                }),
        ])->latest()->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $this->ensureAdmin();

        $roles = Role::orderBy('department')->orderBy('name')->get();
        $departments = $this->departments();
        $brands = $this->brands();
        $defaultBrandId = $this->defaultBrandId();

        return view('admin.users.create', compact('roles', 'departments', 'brands', 'defaultBrandId'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query->where('department', (string) $request->input('department'))
                ),
            ],
            'brand_id' => ['required', 'exists:brands,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255', Rule::in($this->departments())],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['nullable', 'required_if:department,Sales', 'string', 'max:50'],
        ], [
            'role_id.exists' => 'Please choose a role that belongs to the selected department.',
        ]);

        $validated['email'] = mb_strtolower(trim($validated['email']));
        $this->ensureUserIsNotDuplicate($validated);
        $this->ensureRoleBelongsToDepartment($request, $validated['role_id'], $validated['department'] ?? null);

        $user = User::create([
            'role_id'   => $validated['role_id'],
            'brand_id' => $validated['brand_id'],
            'first_name'    => $validated['first_name'],
            'last_name'     => $validated['last_name'],
            'department'    => $validated['department'] ?? null,
            'email'         =>  $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,

            // Temporary random password
            'password'      => Hash::make(Str::random(32)),


            // not verified yet
            'email_verified_at'     => null,
            'password_created_at'   => null,

            // expires after 7 days
            'invitation_expires_at' => now()->addDays(7),
        ]);

        try {
            Mail::to($user->email)->send(new UserInvitationMail($user));
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return redirect()
                ->route('admin.users.index')
                ->with('error', 'User created successfully, but the invitation email could not be sent. Please check the mail connection and resend the invitation.');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully. Invitation email sent.');
    }

    public function show(User $user)
    {
        $this->ensureAdmin();

        $user->load('role', 'permissionOverrides');

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $this->ensureAdmin();

        $roles = Role::with('permissionRecords')->orderBy('department')->orderBy('name')->get();
        $departments = $this->departments();
        $brands = $this->brands();
        $defaultBrandId = $this->defaultBrandId();
        $availablePermissions = $this->availablePermissions();
        $user->load('role.permissionRecords', 'permissionOverrides');

        return view('admin.users.edit', compact('user', 'roles', 'departments', 'brands', 'defaultBrandId', 'availablePermissions'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'brand_id' => ['required', 'exists:brands,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255', Rule::in($this->departments())],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'phone_number' => ['nullable', 'required_if:department,Sales', 'string', 'max:50'],
            'final_permissions' => ['nullable', 'array'],
            'final_permissions.*' => ['string'],
        ]);

        $validated['email'] = mb_strtolower(trim($validated['email']));
        $this->ensureUserIsNotDuplicate($validated, $user);
        $this->ensureRoleBelongsToDepartment($request, $validated['role_id'], $validated['department'] ?? null);

        $user->update(collect($validated)->except(['final_permissions'])->all());
        $this->syncUserPermissionOverrides($user, $request);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->ensureAdmin();

        if ($user->id === 1) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'The first admin user cannot be deleted.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function suspend(Request $request, User $user)
    {
        $this->ensureAdmin();

        if ($user->is($request->user())) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot suspend your own account.');
        }

        if ($user->id === 1) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'The first admin user cannot be suspended.');
        }

        $validated = $request->validate([
            'suspension_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $releasedLeadCount = 0;

        DB::transaction(function () use ($request, $user, $validated, &$releasedLeadCount) {
            $user->update([
                'suspended_at' => now(),
                'suspended_by' => $request->user()->id,
                'suspension_reason' => $validated['suspension_reason'] ?? 'User suspended or resigned.',
            ]);

            $leadIds = Lead::where('assigned_to', $user->id)
                ->whereNull('returned_at')
                ->whereNull('archived_at')
                ->where(function ($query) {
                    $query->whereNull('sales_stage')
                        ->orWhereIn('sales_stage', ['pipeline', 'prospect', 'scheduled_callback']);
                })
                ->pluck('id');

            $releasedLeadCount = $leadIds->count();

            if ($releasedLeadCount > 0) {
                Lead::whereIn('id', $leadIds)->update([
                    'previous_agent_id' => $user->id,
                    'previous_agent_released_at' => now(),
                    'previous_agent_release_reason' => $validated['suspension_reason'] ?? 'User suspended or resigned.',
                    'assigned_to' => null,
                    'assigned_date' => null,
                    'returned_at' => null,
                    'returned_by' => null,
                    'return_notes' => null,
                    'sales_stage' => null,
                    'sales_stage_updated_at' => null,
                    'lead_generation_stage' => 'ready_to_assign',
                    'verification_assigned_to' => null,
                ]);

                LeadAssignmentHistory::whereIn('lead_id', $leadIds)
                    ->whereNull('released_at')
                    ->update([
                        'released_at' => now(),
                        'released_by' => $request->user()->id,
                        'release_reason' => $validated['suspension_reason'] ?? 'User suspended or resigned.',
                    ]);
            }
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User suspended successfully. {$releasedLeadCount} assigned lead(s) moved to Unassigned Leads.");
    }

    public function unsuspend(User $user)
    {
        $this->ensureAdmin();

        $user->update([
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User reactivated successfully.');
    }

    private function departments(): array
    {
        return Department::orderBy('name')->pluck('name')->all();
    }

    private function brands()
    {
        return Brand::orderByRaw("imprint_name = 'CreatiVision Outsourcing' desc")
            ->orderBy('imprint_name')
            ->get();
    }

    private function defaultBrandId(): ?int
    {
        return Brand::where('imprint_name', 'CreatiVision Outsourcing')->value('id');
    }

    private function ensureAdmin(): void
    {
        abort_unless(
            request()->user()?->role?->name === 'Admin'
            || request()->user()?->hasPermission('manage_users'),
            403
        );
    }

    private function availablePermissions(): array
    {
        return Permission::orderBy('id')
            ->get()
            ->mapWithKeys(fn (Permission $permission) => [
                $permission->key => [
                    'group' => $permission->group,
                    'label' => $permission->label,
                    'description' => $permission->description,
                ],
            ])
            ->all();
    }

    private function validPermissions(array $permissions): array
    {
        return collect(array_values($permissions))
            ->map(fn ($permission) => (string) $permission)
            ->intersect(array_keys($this->availablePermissions()))
            ->values()
            ->all();
    }

    private function syncUserPermissionOverrides(User $user, Request $request): void
    {
        $finalPermissions = $this->validPermissions($request->input('final_permissions', []));
        $rolePermissions = $user->role?->permissionRecords()
            ->pluck('key')
            ->all() ?? [];

        $allowed = array_values(array_diff($finalPermissions, $rolePermissions));
        $denied = array_values(array_diff($rolePermissions, $finalPermissions));

        $permissionIds = Permission::whereIn('key', array_unique([...$allowed, ...$denied]))
            ->pluck('id', 'key');

        $sync = [];

        foreach ($allowed as $permission) {
            if (isset($permissionIds[$permission]) && ! in_array($permission, $denied, true)) {
                $sync[$permissionIds[$permission]] = ['effect' => 'allow'];
            }
        }

        foreach ($denied as $permission) {
            if (isset($permissionIds[$permission])) {
                $sync[$permissionIds[$permission]] = ['effect' => 'deny'];
            }
        }

        $user->permissionOverrides()->sync($sync);
    }

    private function ensureRoleBelongsToDepartment(Request $request, int|string $roleId, ?string $department): void
    {
        $role = Role::find($roleId);

        if ($role && $department && $role->department !== $department) {
            back()
                ->withErrors(['role_id' => 'The selected role does not belong to the selected department.'])
                ->withInput()
                ->throwResponse();
        }
    }

    private function ensureUserIsNotDuplicate(array $userData, ?User $currentUser = null): void
    {
        $emailExists = User::withTrashed()
            ->when($currentUser, fn ($query) => $query->whereKeyNot($currentUser->id))
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($userData['email'])])
            ->exists();

        if ($emailExists) {
            back()
                ->withErrors(['email' => 'A user with this email already exists.'])
                ->withInput()
                ->throwResponse();
        }

        $normalizedPhone = $this->normalizePhoneForDuplicateCheck($userData['phone_number'] ?? '');

        if (($userData['department'] ?? null) === 'Sales' && $normalizedPhone !== '') {
            $phoneExists = User::withTrashed()
                ->when($currentUser, fn ($query) => $query->whereKeyNot($currentUser->id))
                ->whereNotNull('phone_number')
                ->get(['id', 'phone_number'])
                ->contains(fn (User $user) => $this->normalizePhoneForDuplicateCheck((string) $user->phone_number) === $normalizedPhone);

            if ($phoneExists) {
                back()
                    ->withErrors(['phone_number' => 'A user with this phone number already exists.'])
                    ->withInput()
                    ->throwResponse();
            }
        }
    }

    private function normalizePhoneForDuplicateCheck(string $phoneNumber): string
    {
        return preg_replace('/\D+/', '', $phoneNumber) ?? '';
    }

}
