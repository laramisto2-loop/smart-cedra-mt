<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_stations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('polling_center_id')
                ->constrained('polling_centers')
                ->cascadeOnDelete();

            $table->string('station_number');
            $table->string('name_en')->nullable();
            $table->string('name_ar')->nullable();
            $table->string('room')->nullable();
            $table->unsignedInteger('registered_voters')->nullable();

            $table->timestamps();

            $table->unique([
                'polling_center_id',
                'station_number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_stations');
    }
};
