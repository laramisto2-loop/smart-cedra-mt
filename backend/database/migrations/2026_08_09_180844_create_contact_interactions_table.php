<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_interactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('contact_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('recorded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('channel', 20);
            $table->string('direction', 20)->default('outbound');
            $table->string('outcome', 30)->nullable();
            $table->string('subject')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('occurred_at');

            $table->string('consent_status_snapshot', 20)->nullable();
            $table->timestamp('consent_checked_at')->nullable();

            $table->timestamps();

            $table->index([
                'tenant_id',
                'contact_id',
                'occurred_at',
            ]);

            $table->index([
                'tenant_id',
                'channel',
                'occurred_at',
            ]);

            $table->index([
                'tenant_id',
                'recorded_by_user_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_interactions');
    }
};
