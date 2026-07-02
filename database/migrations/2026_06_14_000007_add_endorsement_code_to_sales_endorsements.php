<?php

use App\Models\SalesEndorsement;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->string('endorsement_code')->nullable()->unique()->after('id');
        });

        SalesEndorsement::withTrashed()
            ->whereNull('endorsement_code')
            ->orderBy('id')
            ->get()
            ->each(function (SalesEndorsement $endorsement) {
                $endorsement->forceFill([
                    'endorsement_code' => SalesEndorsement::generateEndorsementCode($endorsement->created_at),
                ])->save();
            });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->dropUnique(['endorsement_code']);
            $table->dropColumn('endorsement_code');
        });
    }
};
