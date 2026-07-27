<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polling_centers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('area_id')
                ->constrained('areas')
                ->cascadeOnDelete();

            $table->string('name_en');
            $table->string('name_ar');
            $table->string('code');

            $table->string('address_en')->nullable();
            $table->string('address_ar')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['area_id', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polling_centers');
    }
};
