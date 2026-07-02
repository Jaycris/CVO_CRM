<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unique(['permission_id', 'role_id']);
        });

        $permissions = [
            'view_all_leads' => ['Lead Pages', 'View All Leads', 'Can see all added and imported leads.'],
            'view_my_leads' => ['Lead Pages', 'View My Leads', 'Can see leads added or imported by the user.'],
            'view_verification_queue' => ['Lead Pages', 'View Verification Queue', 'Can see unverified and unassigned leads waiting for review.'],
            'view_unassigned_leads' => ['Lead Pages', 'View Unassigned Leads', 'Can see verified or unverified leads that are not assigned yet.'],
            'view_returned_leads' => ['Lead Pages', 'View Returned Leads', 'Can see returned leads related to the user.'],
            'view_archived_leads' => ['Lead Pages', 'View Archived Leads', 'Can see archived leads related to the user.'],
            'view_assigned_leads' => ['Sales Pages', 'View Assigned Leads', 'Can see leads assigned to the user.'],
            'view_sales' => ['Sales Pages', 'View Sales Menu', 'Can open Pipeline, Prospect, Scheduled Callback, Sold, and Refunds.'],
            'view_sales_endorsement_form' => ['Sales Pages', 'View Sales Endorsement Form', 'Can open the sales endorsement form page.'],
            'view_payment_records' => ['Finance Pages', 'View Payment Records', 'Can view sales payment records.'],
            'create_leads' => ['Lead Actions', 'Create or Import Leads', 'Can add leads to the system.'],
            'edit_leads' => ['Lead Actions', 'Edit Leads', 'Can edit lead details. Non-admin users are limited to their own leads.'],
            'delete_leads' => ['Lead Actions', 'Delete Leads', 'Can permanently delete leads.'],
            'archive_leads' => ['Lead Actions', 'Archive Leads', 'Can archive leads. Non-admin users are limited to their own leads.'],
            'assign_leads' => ['Lead Actions', 'Assign or Reassign Leads', 'Can assign leads to Branding Specialists.'],
            'verify_leads' => ['Lead Actions', 'Verify Leads', 'Can verify leads and save verification scores.'],
            'move_sales_stage' => ['Sales Actions', 'Update Pipeline', 'Can move assigned leads through sales stages.'],
            'return_leads' => ['Sales Actions', 'Return Leads', 'Can return leads with notes.'],
            'submit_sales_endorsement' => ['Sales Actions', 'Can Submit a Form', 'Can submit sales endorsement forms.'],
            'manage_payment_records' => ['Finance Actions', 'Manage Payment Records', 'Can update payment method, sold date, and payment status.'],
            'view_reports' => ['Reports', 'View Reports', 'Can view reporting pages when reports are added.'],
            'manage_users' => ['Administration', 'Manage Users', 'Can create, edit, invite, and delete users.'],
            'manage_roles_permissions' => ['Administration', 'Manage Roles & Permissions', 'Can create/edit roles, departments, and permissions.'],
        ];

        foreach ($permissions as $key => [$group, $label, $description]) {
            DB::table('permissions')->insert([
                'key' => $key,
                'group' => $group,
                'label' => $label,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        DB::table('roles')->orderBy('id')->get()->each(function ($role) use ($permissionIds) {
            $keys = json_decode($role->permissions ?? '[]', true);

            if (! is_array($keys)) {
                $keys = [];
            }

            if ($role->name === 'Admin') {
                $keys = $permissionIds->keys()->all();
            }

            foreach (array_unique($keys) as $key) {
                if (! isset($permissionIds[$key])) {
                    continue;
                }

                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionIds[$key],
                    'role_id' => $role->id,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }
};
