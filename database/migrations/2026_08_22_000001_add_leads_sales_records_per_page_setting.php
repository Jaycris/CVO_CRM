<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AppSetting::set('leads_sales_records_per_page', AppSetting::DEFAULT_LEADS_SALES_RECORDS_PER_PAGE);
    }

    public function down(): void
    {
        AppSetting::where('key', 'leads_sales_records_per_page')->delete();
    }
};
