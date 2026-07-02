<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('sales_payment_id')->unique()->constrained('sales_payments')->cascadeOnDelete();
            $table->foreignId('sales_endorsement_id')->nullable()->constrained('sales_endorsements')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_miner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('activity_type')->default('payment_success');
            $table->string('endorsement_code')->nullable();
            $table->string('author_name')->nullable();
            $table->string('book_title')->nullable();
            $table->string('service_name')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->date('sold_date')->nullable();
            $table->timestamps();
        });

        DB::table('permissions')->updateOrInsert(
            ['key' => 'view_sales_activity'],
            [
                'group' => 'Finance Pages',
                'label' => 'View Sales Activity',
                'description' => 'Can view automatic successful-payment activity used for future reports.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'view_sales_activity')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Admin', 'Finance Officer'])
            ->pluck('id');

        if ($permissionId) {
            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $successfulPayments = DB::table('sales_payments')
            ->join('sales_endorsements', 'sales_payments.sales_endorsement_id', '=', 'sales_endorsements.id')
            ->leftJoin('leads', 'sales_endorsements.lead_id', '=', 'leads.id')
            ->leftJoin('services', 'sales_endorsements.service_id', '=', 'services.id')
            ->where('sales_payments.status', 'Payment Success')
            ->whereNull('sales_payments.deleted_at')
            ->select([
                'sales_payments.id as sales_payment_id',
                'sales_payments.brand_id as payment_brand_id',
                'sales_payments.payment_method',
                'sales_payments.status as payment_status',
                'sales_payments.sold_date',
                'sales_endorsements.id as sales_endorsement_id',
                'sales_endorsements.brand_id as endorsement_brand_id',
                'sales_endorsements.lead_id',
                'sales_endorsements.agent_id',
                'sales_endorsements.service_id',
                'sales_endorsements.endorsement_code',
                'sales_endorsements.author_name',
                'sales_endorsements.book_title',
                'sales_endorsements.services',
                'sales_endorsements.amount',
                'leads.created_by as lead_miner_id',
                'leads.verified_by as verifier_id',
                'services.name as service_name',
            ])
            ->get();

        foreach ($successfulPayments as $payment) {
            DB::table('sales_activities')->updateOrInsert(
                ['sales_payment_id' => $payment->sales_payment_id],
                [
                    'brand_id' => $payment->payment_brand_id ?? $payment->endorsement_brand_id,
                    'sales_endorsement_id' => $payment->sales_endorsement_id,
                    'lead_id' => $payment->lead_id,
                    'agent_id' => $payment->agent_id,
                    'lead_miner_id' => $payment->lead_miner_id,
                    'verifier_id' => $payment->verifier_id,
                    'service_id' => $payment->service_id,
                    'activity_type' => 'payment_success',
                    'endorsement_code' => $payment->endorsement_code,
                    'author_name' => $payment->author_name,
                    'book_title' => $payment->book_title,
                    'service_name' => $payment->service_name ?? $payment->services,
                    'amount' => $payment->amount ?? 0,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'sold_date' => $payment->sold_date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'view_sales_activity')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('sales_activities');
    }
};
