<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $descriptions = [
            'view_verification_queue' => 'Can open Verification Queue and receive personal notifications for leads assigned to them for verification.',
            'verify_leads' => 'Can verify leads assigned to them and save verification scores. Admin can verify any queue lead.',
            'send_leads_to_verification' => 'Can send mined or repaired leads to Verification Queue. Leads are balanced across available verifier users.',
            'move_verified_leads_to_ready' => 'Can move assigned verified leads to Unassigned Leads or Returned Leads, updating sidebar queue counts.',
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
