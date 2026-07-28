<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AppSetting::set('records_per_page', AppSetting::DEFAULT_RECORDS_PER_PAGE);
    }

    public function down(): void
    {
        AppSetting::where('key', 'records_per_page')->delete();
    }
};
