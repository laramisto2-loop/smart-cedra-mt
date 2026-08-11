<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'contact_consents',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->foreignId('contact_id')
                    ->constrained('contacts')
                    ->cascadeOnDelete();

                $table->foreignId('recorded_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('channel', 20);
                $table->string('status', 20)
                    ->default('unknown');

                $table->string('source', 50)->nullable();
                $table->timestamp('consented_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique([
                    'contact_id',
                    'channel',
                ]);

                $table->index([
                    'tenant_id',
                    'channel',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_consents');
    }
};
