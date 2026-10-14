<?php

namespace App\Console\Commands;

use App\Models\CommissionAdjustment;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Support\CommissionDisputes;
use App\Support\SalesActivitySync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileDisputeCommissionsCommand extends Command
{
    protected $signature = 'commissions:reconcile-disputes
        {--agent= : Limit to an agent ID, including Frankie shares}
        {--apply : Save adjustments; without this flag, preview and roll back}';

    protected $description = 'Preview or reconcile existing disputed payments with missing commission deductions.';

    public function handle(): int
    {
        $agentId = $this->option('agent');
        if ($agentId !== null && (! ctype_digit((string) $agentId) || (int) $agentId < 1)) {
            $this->error('The agent option must be a positive user ID.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $payments = SalesPayment::with('endorsement')
                ->where('status', 'Dispute')
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('commission_adjustments')
                    ->whereColumn('commission_adjustments.sales_payment_id', 'sales_payments.id'))
                ->when($agentId, fn ($query) => $query->whereHas('endorsement', fn ($query) => $query
                    ->where(fn ($query) => $query->where('agent_id', $agentId)->orWhere('frankie_agent_id', $agentId))))
                ->orderBy('id')->lockForUpdate()->get();

            if ($payments->isEmpty()) {
                DB::rollBack();
                $this->info('No disputed payments with missing adjustments were found.');

                return self::SUCCESS;
            }

            // Restore every candidate before calculating any snapshot, so monthly
            // commission tiers and thresholds include all of the original sales.
            foreach ($payments as $payment) {
                if (! $payment->endorsement || ! $payment->sold_date) {
                    throw new \RuntimeException("Payment {$payment->id} has no endorsement or sold date. No changes were saved.");
                }
                $original = clone $payment;
                $original->status = 'Payment Success';
                if (! SalesActivity::where('sales_payment_id', $payment->id)->exists()) {
                    SalesActivitySync::sync($original);
                }
            }

            foreach ($payments as $payment) {
                $original = clone $payment;
                $original->status = 'Payment Success';
                CommissionDisputes::transition($original, 'Dispute');
            }

            $entries = CommissionAdjustment::with('agent')->whereIn('sales_payment_id', $payments->pluck('id'))->get();
            if ($entries->pluck('sales_payment_id')->unique()->count() !== $payments->count()) {
                throw new \RuntimeException('Some disputed payments could not be calculated. No changes were saved.');
            }

            $this->table(['Payment ID', 'Agent ID', 'Agent', 'Author', 'Original sale', 'Adjustment date', 'USD deduction'], $entries->map(fn ($entry) => [
                $entry->sales_payment_id,
                $entry->agent_id,
                trim(($entry->agent?->first_name ?? '') . ' ' . ($entry->agent?->last_name ?? '')),
                $entry->original_commission['author_name'] ?? '',
                $entry->original_commission['original_sold_date'] ?? '',
                $entry->effective_date->toDateString(),
                number_format((float) $entry->usd_amount, 2, '.', ''),
            ])->all());

            if ($this->option('apply')) {
                DB::commit();
                $this->info("Reconciled {$payments->count()} disputed payment(s). Payment statuses remain Dispute.");
            } else {
                DB::rollBack();
                $this->info('Preview only. No changes saved. Run again with --apply to record these deductions.');
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            DB::rollBack();
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
