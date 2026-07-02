<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_endorsement_id')->unique()->constrained('sales_endorsements')->cascadeOnDelete();
            $table->string('payment_method');
            $table->date('sold_date');
            $table->string('status');
            $table->timestamps();
        });

        $permissions = [
            'view_payment_records' => ['Finance Pages', 'View Payment Records', 'Can view sales payment records.'],
            'manage_payment_records' => ['Finance Actions', 'Manage Payment Records', 'Can update payment method, sold date, and payment status.'],
        ];

        foreach ($permissions as $key => [$group, $label, $description]) {
            DB::table('permissions')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label' => $label,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_keys($permissions))
            ->pluck('id');

        DB::table('roles')
            ->whereIn('name', ['Admin', 'Finance Officer'])
            ->pluck('id')
            ->each(function ($roleId) use ($permissionIds) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['view_payment_records', 'manage_payment_records'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('sales_payments');
    }
};
