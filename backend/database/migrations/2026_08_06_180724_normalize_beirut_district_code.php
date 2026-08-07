<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('districts')
            ->where('code', 'BEY-D')
            ->update(['code' => 'LB-BA-BEIRUT']);
    }

    public function down(): void
    {
        DB::table('districts')
            ->where('code', 'LB-BA-BEIRUT')
            ->update(['code' => 'BEY-D']);
    }
};
