<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('governorate_id')
                ->constrained('governorates')
                ->cascadeOnDelete();

            $table->string('name_en');
            $table->string('name_ar');
            $table->string('code');

            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['governorate_id', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};

// Here, governorate_id connects each district to its parent governorate.
