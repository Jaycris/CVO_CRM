<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        'view_agent_statements' => [
            'label' => 'View Own Agent Statement',
            'description' => 'Can open Agent Statements and view only their own monthly sales and commission statement.',
            'down_label' => 'View Agent Statements',
            'down_description' => 'Can view their own monthly sales and commission statement.',
        ],
        'view_all_agent_statements' => [
            'label' => 'View All Agent Statements',
            'description' => 'Can view every agent statement, including private commission totals, limited by brand access.',
            'down_label' => 'View All Agent Statements',
            'down_description' => 'Can view monthly sales and commission statements for all agents allowed by brand access.',
        ],
        'view_all_agent_mtd_directory' => [
            'label' => 'View All Agent MTD Directory and Commission Totals',
            'description' => 'Can view every agent row in Sales Dashboard MTD and see private commission totals for all visible agents.',
            'down_label' => 'View All Agent MTD Directory',
            'down_description' => 'Can see every agent row in the Sales Dashboard MTD Agent MTD Directory.',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $key => $permission) {
            DB::table('permissions')
                ->where('key', $key)
                ->update([
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        foreach ($this->permissions as $key => $permission) {
            DB::table('permissions')
                ->where('key', $key)
                ->update([
                    'label' => $permission['down_label'],
                    'description' => $permission['down_description'],
                    'updated_at' => now(),
                ]);
        }
    }
};
