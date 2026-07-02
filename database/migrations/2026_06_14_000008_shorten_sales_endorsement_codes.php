<?php

use App\Models\SalesEndorsement;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        SalesEndorsement::withTrashed()
            ->whereNotNull('endorsement_code')
            ->orderBy('id')
            ->get()
            ->each(function (SalesEndorsement $endorsement) {
                $code = (string) $endorsement->endorsement_code;

                if (! preg_match('/^SE-\d{6}-\d{6}$/', $code) && ! preg_match('/^SE\d{9}$/', $code)) {
                    return;
                }

                $endorsement->forceFill([
                    'endorsement_code' => SalesEndorsement::generateEndorsementCode($endorsement->created_at),
                ])->save();
            });
    }

    public function down(): void
    {
        SalesEndorsement::withTrashed()
            ->whereNotNull('endorsement_code')
            ->orderBy('id')
            ->get()
            ->each(function (SalesEndorsement $endorsement) {
                $code = (string) $endorsement->endorsement_code;

                if (! preg_match('/^SE\d{7}$/', $code)) {
                    return;
                }

                $yearPart = substr($code, 2, 4);
                $randomPart = substr($code, 6);

                $endorsement->forceFill([
                    'endorsement_code' => 'SE-' . $yearPart . '01-' . str_pad($randomPart, 6, '0', STR_PAD_LEFT),
                ])->save();
            });
    }
};
