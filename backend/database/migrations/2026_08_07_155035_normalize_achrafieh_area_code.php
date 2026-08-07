<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('areas')
            ->where('code', 'ACH')
            ->update([
                'code' => 'LB-BA-BEIRUT-ACHRAFIEH',
            ]);
    }

    public function down(): void
    {
        DB::table('areas')
            ->where('code', 'LB-BA-BEIRUT-ACHRAFIEH')
            ->update([
                'code' => 'ACH',
            ]);
    }
};
