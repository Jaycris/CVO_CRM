<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Brand;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesEndorsement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesEndorsementExportTest extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing(): array
    {
        return [
            '--path' => array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => basename($path) !== '2026_09_25_000003_add_monthly_period_to_reward_unlocks.php')),
            '--realpath' => true,
        ];
    }

    private function viewer(string $roleName, Brand $brand, string $department = 'Finance'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['slug' => str_replace(' ', '-', strtolower($roleName)), 'department' => $department]);
        $permission = Permission::where('key', 'view_all_sales_endorsements')->firstOrFail();
        $role->permissionRecords()->syncWithoutDetaching([$permission->id]);

        return User::factory()->create(['role_id' => $role->id, 'brand_id' => $brand->id, 'department' => $department]);
    }

    private function record(Brand $brand, User $agent, string $submitted, array $overrides = []): SalesEndorsement
    {
        $record = SalesEndorsement::create(array_replace([
            'brand_id' => $brand->id, 'agent_id' => $agent->id, 'author_name' => 'Export Author',
            'contact_number' => '5550100', 'email' => 'author@example.test', 'street_name' => 'Test Street',
            'city_state' => 'Test City', 'zip_code' => '00100', 'book_title' => 'Test Book',
            'isbn' => '0012345678', 'services' => 'Test Service', 'amount' => 1000,
            'amount_to_be_paid' => 1000, 'payment' => 'Installment Payment', 'remarks' => 'Test remarks',
        ], $overrides));
        $record->forceFill(['created_at' => $submitted])->save();

        return $record;
    }

    private function csv(array $query): array
    {
        $response = $this->get(route('sales.endorsements.export', $query))->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $file = fopen('php://memory', 'r+');
        fwrite($file, substr($content, 3));
        rewind($file);
        $headers = fgetcsv($file);
        $rows = [];
        while (($row = fgetcsv($file)) !== false) $rows[] = array_combine($headers, $row);
        fclose($file);

        return $rows;
    }

    public function test_admin_export_includes_both_submitted_dates_all_pages_and_form_details(): void
    {
        $brand = Brand::create(['imprint_name' => 'Export Brand']);
        $admin = $this->viewer('Admin', $brand, 'Admin');
        $this->actingAs($admin);
        AppSetting::set('leads_sales_records_per_page', 10);
        $first = $this->record($brand, $admin, '2026-10-01 00:00:00', [
            'author_name' => 'José Author', 'book_title' => "A \"quoted\" book, volume one\nSecond line",
        ]);
        $last = $this->record($brand, $admin, '2026-10-31 23:59:59');
        $expectedCodes = [$first->endorsement_code, $last->endorsement_code];
        for ($index = 0; $index < 9; $index++) {
            $expectedCodes[] = $this->record($brand, $admin, '2026-10-15 12:00:00')->endorsement_code;
        }
        $this->record($brand, $admin, '2026-09-30 23:59:59');
        $this->record($brand, $admin, '2026-11-01 00:00:00');
        $deleted = $this->record($brand, $admin, '2026-10-15 12:00:00');
        $deleted->delete();

        $this->get(route('sales.endorsements.index'))->assertOk()->assertSee('Export Sales Endorsements (CSV)')
            ->assertViewHas('endorsements', fn ($rows) => $rows->hasMorePages());
        $rows = $this->csv(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame($expectedCodes, array_column($rows, 'SE ID'));
        $this->assertSame('José Author', $rows[0]['Author']);
        $this->assertSame($first->book_title, $rows[0]['Book Title']);
        $this->assertSame('1000.00', $rows[0]['Contract Amount (USD)']);
        $this->assertSame('Installment Payment', $rows[0]['Payment Type']);
        $this->assertSame('author@example.test', $rows[0]['Email']);
        $this->assertSame('00100', $rows[0]['ZIP Code']);
        $this->assertSame('0012345678', $rows[0]['ISBN']);
        $this->assertSame('Test remarks', $rows[0]['Remarks']);
    }

    public function test_finance_export_matches_brand_access_and_search_and_parent_finance_can_access_all_brands(): void
    {
        $brand = Brand::create(['imprint_name' => 'Finance Export Brand']);
        $other = Brand::create(['imprint_name' => 'Other Export Brand']);
        $finance = $this->viewer('Finance Officer', $brand);
        $first = $this->record($brand, $finance, '2026-10-10 12:00:00', ['author_name' => 'Matching Author']);
        $this->record($brand, $finance, '2026-10-10 13:00:00', ['author_name' => 'Different Author']);
        $this->record($other, $finance, '2026-10-10 14:00:00', ['author_name' => 'Matching Other Brand']);
        $this->actingAs($finance)->get(route('sales.endorsements.index'))->assertOk()->assertSee('Export Sales Endorsements (CSV)');
        $query = ['from' => '2026-10-10', 'to' => '2026-10-10', 'search' => 'Matching'];
        $rows = $this->csv($query);
        $this->assertSame([$first->endorsement_code], array_column($rows, 'SE ID'));

        $parent = Brand::firstOrCreate(['imprint_name' => 'CreatiVision Outsourcing']);
        $finance->update(['brand_id' => $parent->id]);
        $this->actingAs($finance->fresh());
        $this->assertCount(2, $this->csv($query));
    }

    public function test_other_roles_cannot_export_even_with_full_endorsement_view_access(): void
    {
        $brand = Brand::create(['imprint_name' => 'Restricted Export Brand']);
        foreach (['Sales Agent', 'General Manager'] as $role) {
            $viewer = $this->viewer($role, $brand, 'Sales');
            $this->actingAs($viewer)->get(route('sales.endorsements.index'))->assertOk()->assertDontSee('Export Sales Endorsements (CSV)');
            $this->get(route('sales.endorsements.export', ['from' => '2026-10-01', 'to' => '2026-10-31']))->assertForbidden();
        }
    }

    public function test_invalid_missing_and_reversed_dates_are_rejected_and_empty_ranges_return_headers_only(): void
    {
        $brand = Brand::create(['imprint_name' => 'Validation Export Brand']);
        $this->actingAs($this->viewer('Admin', $brand, 'Admin'));
        foreach ([
            [[], ['from', 'to']],
            [['from' => '2026-10-01'], ['to']],
            [['from' => '2026-10-31', 'to' => '2026-10-01'], ['to']],
            [['from' => '2026-02-30', 'to' => '2026-10-31'], ['from']],
        ] as [$query, $fields]) {
            $this->getJson(route('sales.endorsements.export', $query))->assertUnprocessable()->assertJsonValidationErrors($fields);
        }
        $this->assertSame([], $this->csv(['from' => '2026-10-01', 'to' => '2026-10-31']));
    }

    public function test_csv_formula_text_is_not_exported_as_an_executable_formula(): void
    {
        $brand = Brand::create(['imprint_name' => 'Formula Export Brand']);
        $admin = $this->viewer('Admin', $brand, 'Admin');
        $this->record($brand, $admin, '2026-10-10 12:00:00', [
            'author_name' => '=1+1', 'remarks' => "\t@SUM(1,2)", 'contact_number' => '+15550100',
        ]);
        $this->actingAs($admin);
        $rows = $this->csv(['from' => '2026-10-10', 'to' => '2026-10-10']);
        $this->assertSame("'=1+1", $rows[0]['Author']);
        $this->assertSame("'\t@SUM(1,2)", $rows[0]['Remarks']);
        $this->assertSame("'+15550100", $rows[0]['Contact Number']);
    }
}
