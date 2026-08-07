<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('polling_centers')
            ->where('code', 'ACH-PC-01')
            ->update([
                'code' => 'LB-BA-BEIRUT-ACHRAFIEH-PUBLIC-SCHOOL',
            ]);
    }

    public function down(): void
    {
        DB::table('polling_centers')
            ->where(
                'code',
                'LB-BA-BEIRUT-ACHRAFIEH-PUBLIC-SCHOOL'
            )
            ->update([
                'code' => 'ACH-PC-01',
            ]);
    }
};
