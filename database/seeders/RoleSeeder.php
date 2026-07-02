<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name'  => 'Admin',
                'department' => 'Administration',
                'description' => 'Full system access.',
            ],
            [
                'name' => 'Finance Officer',
                'department' => 'Administration',
                'description' => 'Handles finance-related access and records.',
            ],
            [
                'name' => 'HR',
                'department' => 'Administration',
                'description' => 'Handles people operations and staff records.',
            ],
            [
                'name' => 'Lead Miner',
                'department' => 'Lead Generation',
                'description' => 'Adds, edits, reassigns, receives returned, and archives own leads.',
            ],
            [
                'name' => 'Branding Specialist',
                'department' => 'Sales',
                'description' => 'Manages branding conversations and client positioning.',
            ],
            [
                'name' => 'Sales Director',
                'department' => 'Sales',
                'description' => 'Oversees sales performance and pipeline direction.',
            ],
            [
                'name' => 'Team Leader',
                'department' => 'Sales',
                'description' => 'Leads a sales or operations team.',
            ],
            [
                'name' => 'Trainee',
                'department' => 'Sales',
                'description' => 'Entry-level team member in training.',
            ],
            [
                'name' => 'Operation Manager',
                'slug' => 'operation-manager',
                'department' => 'Sales',
                'description' => 'Manages day-to-day operations and workflow coordination.',
            ],
            [
                'name' => 'Operation Manager',
                'slug' => 'production-operation-manager',
                'department' => 'Production',
                'description' => 'Manages production operations and workflow coordination.',
            ],
            [
                'name' => 'Fulfillment Officer',
                'department' => 'Production',
                'description' => 'Coordinates fulfillment and delivery of client work.',
            ],
            [
                'name' => 'Web Designer',
                'department' => 'Production',
                'description' => 'Designs and supports web deliverables.',
            ],
            [
                'name' => 'Video Editor',
                'department' => 'Production',
                'description' => 'Edits video assets and production deliverables.',
            ],
            [
                'name' => 'Writer',
                'department' => 'Production',
                'description' => 'Creates written content and copy deliverables.',
            ],
            [
                'name' => 'Graphic Designer',
                'department' => 'Production',
                'description' => 'Creates visual and graphic design deliverables.',
            ],
        ];

        $slugs = collect($roles)
            ->map(fn (array $role) => $role['slug'] ?? Str::slug($role['name']))
            ->all();

        foreach ($roles as $role) {
            $permissionKeys = $role['permissions'] ?? $this->defaultPermissions($role['name']);
            $savedRole = Role::updateOrCreate(
                ['slug' => $role['slug'] ?? Str::slug($role['name'])],
                [
                    'name' => $role['name'],
                    'department' => $role['department'],
                    'description' => $role['description'],
                    'permissions' => $permissionKeys,
                ]
            );

            if (class_exists(Permission::class) && \Illuminate\Support\Facades\Schema::hasTable('permissions')) {
                $savedRole->permissionRecords()
                    ->sync(Permission::whereIn('key', $permissionKeys)->pluck('id')->all());
            }
        }

        Role::whereNotIn('slug', $slugs)
            ->doesntHave('users')
            ->delete();
    }

    private function defaultPermissions(string $roleName): array
    {
        return match ($roleName) {
            'Admin' => [
                'view_all_leads',
                'view_my_leads',
                'view_verification_queue',
                'view_unassigned_leads',
                'view_returned_leads',
                'view_archived_leads',
                'view_assigned_leads',
                'view_sales',
                'view_sales_endorsement_form',
                'view_own_sales_endorsements',
                'view_all_sales_endorsements',
                'view_payment_records',
                'view_finance_clients',
                'view_contract_records',
                'create_leads',
                'edit_leads',
                'delete_leads',
                'archive_leads',
                'assign_leads',
                'verify_leads',
                'self_mine_work_leads',
                'send_returned_leads_back',
                'send_leads_to_verification',
                'move_verified_leads_to_ready',
                'move_sales_stage',
                'return_leads',
                'submit_sales_endorsement',
                'delete_sales_endorsements',
                'manage_payment_records',
                'delete_payment_records',
                'manage_contract_records',
                'view_reports',
                'manage_users',
                'manage_roles_permissions',
            ],
            'Lead Miner' => [
                'view_my_leads',
                'view_returned_leads',
                'view_archived_leads',
                'create_leads',
                'edit_leads',
                'archive_leads',
                'assign_leads',
                'send_returned_leads_back',
                'send_leads_to_verification',
            ],
            'Verifier' => [
                'view_verification_queue',
                'view_returned_leads',
                'verify_leads',
                'move_verified_leads_to_ready',
            ],
            'Branding Specialist', 'Team Leader', 'Trainee' => [
                'view_assigned_leads',
                'view_sales',
                'view_own_sales_endorsements',
                ...($roleName === 'Team Leader' ? ['view_all_sales_endorsements'] : []),
                'move_sales_stage',
                'return_leads',
                'submit_sales_endorsement',
            ],
            'Sales Director', 'Operation Manager' => [
                'view_assigned_leads',
                'view_sales',
                'view_own_sales_endorsements',
                'move_sales_stage',
                'return_leads',
                'submit_sales_endorsement',
                'view_reports',
            ],
            'Finance Officer' => [
                'view_reports',
                'view_payment_records',
                'view_finance_clients',
                'view_contract_records',
                'manage_payment_records',
                'delete_payment_records',
                'manage_contract_records',
            ],
            default => [],
        };
    }
}
