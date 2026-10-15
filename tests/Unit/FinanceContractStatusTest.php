<?php

namespace Tests\Unit;

use App\Http\Controllers\FinanceContractController;
use App\Models\SalesEndorsement;
use Tests\TestCase;

class FinanceContractStatusTest extends TestCase
{
    private function updates(SalesEndorsement $endorsement, string $status): array
    {
        return (new \ReflectionMethod(FinanceContractController::class, 'contractStatusUpdates'))
            ->invoke(new FinanceContractController, $endorsement, $status);
    }

    public function test_unsent_clears_status_dates_without_deleting_documents_or_signature_data(): void
    {
        $endorsement = new SalesEndorsement([
            'contract_status' => 'sent',
            'contract_sent_at' => now(),
            'contract_file_path' => 'contracts/original.pdf',
            'contract_signature_text' => 'Client Name',
        ]);

        $this->assertSame([
            'contract_status' => null,
            'contract_sent_at' => null,
            'contract_signed_at' => null,
        ], $this->updates($endorsement, 'unsent'));
        $this->assertSame('contracts/original.pdf', $endorsement->contract_file_path);
        $this->assertSame('Client Name', $endorsement->contract_signature_text);
    }

    public function test_mark_sent_sets_a_date_but_preserves_an_existing_sent_date(): void
    {
        $this->assertArrayHasKey('contract_sent_at', $this->updates(new SalesEndorsement, 'sent'));
        $this->assertSame(['contract_status' => 'sent'], $this->updates(new SalesEndorsement([
            'contract_sent_at' => now()->subDay(),
        ]), 'sent'));
    }

    public function test_mark_signed_sets_both_dates_for_an_unsent_record(): void
    {
        $updates = $this->updates(new SalesEndorsement, 'signed');

        $this->assertSame('signed', $updates['contract_status']);
        $this->assertArrayHasKey('contract_sent_at', $updates);
        $this->assertArrayHasKey('contract_signed_at', $updates);
    }
}
