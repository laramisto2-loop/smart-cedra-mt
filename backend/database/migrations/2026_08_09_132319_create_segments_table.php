<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();

            $table->string('type', 20)
                ->default('static');

            $table->json('criteria')->nullable();

            $table->string('status', 20)
                ->default('active');

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'code',
            ]);

            $table->index([
                'tenant_id',
                'status',
                'type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
