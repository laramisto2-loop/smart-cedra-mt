<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('governorates')
            ->where('code', 'BEY')
            ->update(['code' => 'LB-BA']);
    }

    public function down(): void
    {
        DB::table('governorates')
            ->where('code', 'LB-BA')
            ->update(['code' => 'BEY']);
    }
};
