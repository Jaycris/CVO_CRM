<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentBrandId = DB::table('brands')->insertOrIgnore([
            'imprint_name' => 'CreatiVision Outsourcing',
            'description' => 'Parent company account for employees who are not assigned to a specific brand or imprint.',
            'address' => null,
            'logo_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $creatiVisionBrandId = DB::table('brands')
            ->where('imprint_name', 'CreatiVision Outsourcing')
            ->value('id');

        if ($creatiVisionBrandId) {
            DB::table('users')
                ->whereNull('brand_id')
                ->update(['brand_id' => $creatiVisionBrandId]);
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('brand_id', function ($query) {
                $query->select('id')
                    ->from('brands')
                    ->where('imprint_name', 'CreatiVision Outsourcing');
            })
            ->update(['brand_id' => null]);

        DB::table('brands')
            ->where('imprint_name', 'CreatiVision Outsourcing')
            ->delete();
    }
};
