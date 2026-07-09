<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        $this->ensureCanManageRolesPermissions(request());

        $departments = Department::orderBy('name')->get();
        $departmentRoleCounts = Role::query()
            ->select('department', DB::raw('count(*) as total'))
            ->groupBy('department')
            ->pluck('total', 'department');
        $roles = Role::query()
            ->with('permissionRecords')
            ->when(request('search'), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(request('department'), fn ($query, string $department) => $query->where('department', $department))
            ->orderBy('department')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $availablePermissions = $this->availablePermissions();

        $permissionGroups = [
            [
                'title' => 'Page Access',
                'description' => 'Controls which areas each role can open from the sidebar or direct links.',
                'items' => [
                    ['name' => 'Dashboard', 'allowed' => 'All active users', 'note' => 'General landing page after login.'],
                    ['name' => 'Mine Leads', 'allowed' => 'Admin, permitted Lead Generation users', 'note' => 'Starting pool for newly mined leads before sending them to verification.'],
                    ['name' => 'My Leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Lead Miners see their own newly added leads before sending them to verification.'],
                    ['name' => 'Verification Queue', 'allowed' => 'Admin, Verifier', 'note' => 'Verifier reviews only the leads assigned to them by the balanced verification queue.'],
                    ['name' => 'Unassigned Leads', 'allowed' => 'Admin, permitted Lead Generation users', 'note' => 'Ready-to-assign leads after verifier approval. New items appear as sidebar badge counts.'],
                    ['name' => 'Returned Leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Lead Miners see returned leads that need repair or re-checking. New items appear as sidebar badge counts.'],
                    ['name' => 'Archived Leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Lead Miners see archived leads. New archived records appear as sidebar badge counts.'],
                    ['name' => 'New Leads', 'allowed' => 'Sales users with View Assigned Leads', 'note' => 'Sales agents use this label to see newly assigned leads and receive personal assigned/returned-to-agent notifications.'],
                    ['name' => 'Assigned Leads', 'allowed' => 'Admin, Lead Generation, permitted managers', 'note' => 'This is the monitor table for leads already assigned to Sales users. Access is controlled by View Assigned Leads Monitor.'],
                    ['name' => 'Team Leads', 'allowed' => 'Admin, permitted Sales Team Leaders', 'note' => 'Team Leaders can monitor assigned Sales department leads across agents.'],
                    ['name' => 'Sales', 'allowed' => 'Admin, Sales users', 'note' => 'Sales users work pipeline, prospect, callbacks, sold, and refunds.'],
                    ['name' => 'Sales Endorsement', 'allowed' => 'Admin, Finance users, permitted Sales users', 'note' => 'This page is shown under Finance. Sales users may be allowed to submit endorsements for Finance review.'],
                    ['name' => 'Services Catalog', 'allowed' => 'Admin, permitted users', 'note' => 'Read-only service list for agents and staff to see prices, descriptions, and inclusions.'],
                    ['name' => 'Payment Records', 'allowed' => 'Admin, Finance Officer', 'note' => 'Finance users can view, update, search, or delete payment records based on permissions.'],
                    ['name' => 'Sold Clients', 'allowed' => 'Admin, Finance Officer', 'note' => 'Finance users can view clients with successful payment records.'],
                    ['name' => 'Refunds & Disputes', 'allowed' => 'Admin, Finance Officer', 'note' => 'Finance users can separate refund and dispute records from paid clients.'],
                    ['name' => 'Contract Records', 'allowed' => 'Admin, Finance Officer', 'note' => 'Finance users can view clients sent for contract and signed contracts. Contract records can be endorsed to Production when Finance is ready.'],
                    ['name' => 'Fulfillment Tracker', 'allowed' => 'Admin, permitted Production users, permitted Sales users', 'note' => 'Production users see the trackers they are allowed to access. Sales agents can see fulfillment progress for their own clients only. Task Tracker requires a Production tracker permission.'],
                    ['name' => 'Team Client Project Progress', 'allowed' => 'Admin, permitted Sales Team Leaders and Managers', 'note' => 'View-only access to Fulfillment Tracker progress for clients owned by Sales agents in the user’s team.'],
                    ['name' => 'My New Task / My Complete Tasks', 'allowed' => 'Assigned Production users and Fulfillment Officers', 'note' => 'Shows active assigned production work separately from completed production work. New task notifications remain personal to the assigned user.'],
                    ['name' => 'Publishing Tracker', 'allowed' => 'Admin, Publishing fulfillment users, permitted managers', 'note' => 'Shows projects assigned to the Publishing fulfillment lane.'],
                    ['name' => 'Marketing Tracker', 'allowed' => 'Admin, Marketing fulfillment users, permitted managers', 'note' => 'Shows projects assigned to the Marketing fulfillment lane.'],
                    ['name' => 'Events Tracker', 'allowed' => 'Admin, Marketing fulfillment users, permitted managers', 'note' => 'Shows event-related fulfillment projects.'],
                    ['name' => 'Users', 'allowed' => 'Admin only', 'note' => 'User directory and invitations.'],
                    ['name' => 'Brands / Accounts', 'allowed' => 'Admin only', 'note' => 'Manages imprints and brand accounts under CreatiVision Outsourcing.'],
                    ['name' => 'Services', 'allowed' => 'Admin, permitted managers', 'note' => 'Manages brand/account services, inclusions, pricing, and production position templates.'],
                    ['name' => 'Roles & Permissions', 'allowed' => 'Admin only', 'note' => 'Access rules reference page.'],
                ],
            ],
            [
                'title' => 'Data Actions',
                'description' => 'Controls who can change, remove, or move records.',
                'items' => [
                    ['name' => 'Create or import leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Adds leads to the lead-generation pool.'],
                    ['name' => 'Edit leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Lead Miners can edit only their own leads. Sales users cannot edit lead-mining details.'],
                    ['name' => 'Delete leads', 'allowed' => 'Admin only', 'note' => 'Permanent delete should stay limited.'],
                    ['name' => 'Archive leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Lead Miners can archive only their own leads. DNC numbers also move leads to archive automatically.'],
                    ['name' => 'Reassign leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Sales users cannot reassign leads. Assignment triggers personal notifications for the assigned user.'],
                    ['name' => 'Send leads to verification', 'allowed' => 'Admin, Lead Miner', 'note' => 'Can move newly mined or repaired returned leads to Verification Queue. The system assigns each lead to a verifier with the lightest queue.'],
                    ['name' => 'Move verified leads to ready queue', 'allowed' => 'Admin, Verifier', 'note' => 'Can move verified new leads to Unassigned Leads or verified returned leads back to Returned Leads. These use sidebar badge counts.'],
                    ['name' => 'Send returned leads back', 'allowed' => 'Admin, Lead Miner', 'note' => 'After repair and verification, Lead Miners can send returned leads back and notify the assigned sales agent.'],
                    ['name' => 'Return leads', 'allowed' => 'Admin, Sales users', 'note' => 'Sales users can return assigned leads and add notes. Returned Leads uses sidebar badge counts.'],
                    ['name' => 'Move sales stage', 'allowed' => 'Admin, Sales users', 'note' => 'Pipeline, Prospect, Scheduled Callback, Sold, and Refunds.'],
                    ['name' => 'Reassign team leads', 'allowed' => 'Admin, permitted Sales Team Leaders', 'note' => 'Can reassign active assigned Sales leads to another Sales department user.'],
                    ['name' => 'Unassign team leads', 'allowed' => 'Admin, permitted Sales Team Leaders', 'note' => 'Can move active assigned Sales leads back to the ready-to-assign queue.'],
                    ['name' => 'Submit sales endorsement', 'allowed' => 'Admin, permitted Sales users', 'note' => 'Creates a sales endorsement record for Finance review. The page is grouped under Finance, but Sales users can still be granted this action.'],
                    ['name' => 'Delete sales endorsements', 'allowed' => 'Admin by default', 'note' => 'Deletes selected sales endorsement records.'],
                    ['name' => 'Manage payment records', 'allowed' => 'Admin, Finance Officer', 'note' => 'Adds or updates payment records.'],
                    ['name' => 'Delete finance records', 'allowed' => 'Admin, Finance Officer', 'note' => 'Deletes Payment Records, Sold Clients, Refunds & Disputes, and Contracts data.'],
                    ['name' => 'Manage contract records', 'allowed' => 'Admin, Finance Officer', 'note' => 'Marks contracts as sent or signed.'],
                    ['name' => 'Endorse projects to Production', 'allowed' => 'Admin, Finance Officer', 'note' => 'Sends selected contract records to Publishing, Marketing, or Events fulfillment trackers.'],
                    ['name' => 'Assign production projects', 'allowed' => 'Admin, Fulfillment Officer, Operation Manager', 'note' => 'Assigns endorsed projects to Fulfillment Officers or production team members and notifies the assigned user.'],
                    ['name' => 'Update production project status', 'allowed' => 'Admin, Fulfillment Officer, Operation Manager, assigned Production users', 'note' => 'Updates status as Pending, In Progress, Fulfilled, or Hold Off. When assigned staff marks a task Done, the Fulfillment Officer is notified.'],
                    ['name' => 'Delete fulfillment records', 'allowed' => 'Admin by default, permitted Production users', 'note' => 'Soft-deletes selected fulfillment tracker records so Admin can restore them from Trash.'],
                    ['name' => 'Manage services', 'allowed' => 'Admin by default, permitted managers', 'note' => 'Can create, edit, and delete services and inclusions for accessible brands/accounts.'],
                    ['name' => 'View services catalog', 'allowed' => 'Admin, permitted users', 'note' => 'Can browse services without editing the service setup.'],
                ],
            ],
            [
                'title' => 'Notifications & Badges',
                'description' => 'Explains how alerts are controlled by permissions.',
                'items' => [
                    ['name' => 'Personal notifications', 'allowed' => 'Based on page/action permission', 'note' => 'Used for work assigned to the authenticated user, such as assigned leads, returned-to-agent leads, verifier queue alerts, refunds, assigned production tasks, and completed production tasks for Fulfillment Officers.'],
                    ['name' => 'Shared queue badges', 'allowed' => 'Based on page-view permission', 'note' => 'Unassigned Leads, Returned Leads, Archived Leads, and newly endorsed Production projects use live sidebar badge counts instead of bell notifications.'],
                    ['name' => 'Badge reset', 'allowed' => 'When user opens the page', 'note' => 'Opening the shared queue marks it as seen for that user and clears their badge count.'],
                ],
            ],
            [
                'title' => 'Admin Controls',
                'description' => 'Controls high-impact system settings.',
                'items' => [
                    ['name' => 'Assign leads', 'allowed' => 'Admin, Lead Miner', 'note' => 'Sales users cannot reassign leads. Assignment is limited to Branding Specialist users.'],
                    ['name' => 'View reports', 'allowed' => 'Admin, Sales Director, Operation Manager, Finance Officer', 'note' => 'Report pages will follow this access when added.'],
                    ['name' => 'Manage users', 'allowed' => 'Admin only', 'note' => 'Create, edit, invite, and delete users. The first admin user cannot be deleted.'],
                    ['name' => 'Manage roles & permissions', 'allowed' => 'Admin only', 'note' => 'Future permission editing belongs here.'],
                ],
            ],
        ];

        return view('admin.roles-permissions.index', compact('departments', 'departmentRoleCounts', 'roles', 'availablePermissions', 'permissionGroups'));
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $this->ensureCanManageRolesPermissions($request);

        $validated = $request->validate([
            'department_name' => ['required', 'string', 'max:255', 'unique:departments,name'],
        ]);

        Department::create([
            'name' => trim($validated['department_name']),
        ]);

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', 'Department created successfully.');
    }

    public function updateDepartment(Request $request, Department $department): RedirectResponse
    {
        $this->ensureCanManageRolesPermissions($request);

        $validated = $request->validate([
            'department_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department),
            ],
        ]);

        $oldName = $department->name;
        $newName = trim($validated['department_name']);

        DB::transaction(function () use ($department, $oldName, $newName) {
            $department->update(['name' => $newName]);

            Role::where('department', $oldName)->update(['department' => $newName]);
            User::where('department', $oldName)->update(['department' => $newName]);
        });

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', 'Department updated successfully.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->ensureCanManageRolesPermissions($request);

        $validated = $request->validate([
            'department' => ['required', 'string', 'max:255', 'exists:departments,name'],
            'role_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $role = Role::create([
            'name' => trim($validated['role_name']),
            'slug' => $this->uniqueRoleSlug($validated['department'], $validated['role_name']),
            'department' => $validated['department'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncRolePermissions($role, $this->submittedPermissions($request));
        $savedPermissionCount = $role->permissionRecords()->count();

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('admin.roles-permissions.index'))
            ->with('success', "Role created successfully. {$savedPermissionCount} permission(s) enabled.");
    }

    public function updateRole(Request $request, Role $role): RedirectResponse
    {
        $this->ensureCanManageRolesPermissions($request);
        $isAdminRole = $role->name === 'Admin';

        $validated = $request->validate([
            'department' => ['required', 'string', 'max:255', 'exists:departments,name'],
            'role_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $role->update([
            'name' => trim($validated['role_name']),
            'department' => $validated['department'],
            'description' => $validated['description'] ?? null,
        ]);

        $this->syncRolePermissions(
            $role,
            $isAdminRole ? array_keys($this->availablePermissions()) : $this->submittedPermissions($request)
        );
        $savedPermissionCount = $role->permissionRecords()->count();

        return redirect()
            ->to($this->safeReturnUrl($validated['return_to'] ?? null) ?? route('admin.roles-permissions.index'))
            ->with('success', "Role updated successfully. {$savedPermissionCount} permission(s) enabled.");
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

    private function submittedPermissions(Request $request): array
    {
        if ($request->has('permission_flags')) {
            $permissionFlags = $request->input('permission_flags', []);

            if (array_is_list($permissionFlags)) {
                return $this->validPermissions($permissionFlags);
            }

            return $this->validPermissions(
                collect($permissionFlags)
                    ->filter(fn ($enabled) => (string) $enabled === '1')
                    ->keys()
                    ->all()
            );
        }

        if ($request->has('permissions')) {
            return $this->validPermissions($request->input('permissions', []));
        }

        $payload = json_decode($request->input('permissions_payload', '[]'), true);

        return $this->validPermissions(is_array($payload) ? $payload : []);
    }

    private function syncRolePermissions(Role $role, array $permissionKeys): void
    {
        $permissionIds = Permission::whereIn('key', $permissionKeys)->pluck('id')->all();

        $role->permissionRecords()->sync($permissionIds);
        $role->forceFill(['permissions' => $permissionKeys])->save();
    }

    private function ensureCanManageRolesPermissions(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || $request->user()?->hasPermission('manage_roles_permissions'),
            403
        );
    }

    private function uniqueRoleSlug(string $department, string $roleName): string
    {
        $baseSlug = Str::slug($department . ' ' . $roleName);
        $slug = $baseSlug;
        $suffix = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function safeReturnUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return str_starts_with($url, url('/')) ? $url : null;
    }
}
