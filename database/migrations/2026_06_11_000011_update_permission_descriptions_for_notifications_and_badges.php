<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $descriptions = [
            'view_verification_queue' => 'Can open Verification Queue and receive personal verifier notifications when leads are sent there.',
            'view_unassigned_leads' => 'Can open Unassigned Leads and see live sidebar badge counts for newly ready-to-assign leads.',
            'view_returned_leads' => 'Can open Returned Leads and see live sidebar badge counts for newly returned or re-verified returned leads.',
            'view_archived_leads' => 'Can open Archived Leads and see live sidebar badge counts for newly archived leads.',
            'view_assigned_leads' => 'Can open Assigned Leads and receive personal notifications for leads assigned or returned to the user.',
            'send_leads_to_verification' => 'Can send mined or repaired leads to Verification Queue and trigger verifier notifications.',
            'move_verified_leads_to_ready' => 'Can move verified leads to Unassigned Leads or Returned Leads, updating sidebar queue counts.',
            'send_returned_leads_back' => 'Can send repaired returned leads back to the assigned sales agent and trigger a personal agent notification.',
            'return_leads' => 'Can return assigned leads with notes. Returned Leads updates through sidebar badge counts.',
            'assign_leads' => 'Can assign ready leads to Sales department users and trigger personal assigned-lead notifications.',
        ];

        foreach ($descriptions as $key => $description) {
            DB::table('permissions')
                ->where('key', $key)
                ->update([
                    'description' => $description,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Description-only migration. No safe rollback needed.
    }
};
