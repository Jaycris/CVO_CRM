<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('has_frankie')->default(false);
            $table->string('frankie_agent_name')->nullable();
            $table->string('contact_number');
            $table->string('email');
            $table->string('street_name');
            $table->string('city_state');
            $table->string('zip_code', 50);
            $table->string('book_title');
            $table->string('isbn');
            $table->string('services');
            $table->decimal('amount', 12, 2);
            $table->string('payment');
            $table->text('remarks');
            $table->timestamps();
        });

        $permissions = [
            'view_sales_endorsement_form' => ['Sales Pages', 'View Sales Endorsement Form', 'Can open the sales endorsement form page.'],
            'submit_sales_endorsement' => ['Sales Actions', 'Can Submit a Form', 'Can submit sales endorsement forms.'],
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
            ->pluck('id', 'key');

        DB::table('roles')
            ->where('name', 'Admin')
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
            ->whereIn('key', ['view_sales_endorsement_form', 'submit_sales_endorsement'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('sales_endorsements');
    }
};
