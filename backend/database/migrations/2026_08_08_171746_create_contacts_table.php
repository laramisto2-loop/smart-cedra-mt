<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('area_id')
                ->nullable()
                ->constrained('areas')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reference_code', 50);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('name_ar')->nullable();

            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();

            $table->string('preferred_language', 5)
                ->default('en');

            $table->string('preferred_channel', 20)
                ->nullable();

            $table->string('status', 20)
                ->default('active');

            $table->string('source', 50)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'reference_code',
            ]);

            $table->index([
                'tenant_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'last_name',
                'first_name',
            ]);

            $table->index([
                'tenant_id',
                'phone',
            ]);

            $table->index([
                'tenant_id',
                'email',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
