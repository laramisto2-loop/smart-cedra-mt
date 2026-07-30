<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 50);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'tenant_id',
                'created_at',
            ]);

            $table->index([
                'tenant_id',
                'action',
            ]);

            $table->index([
                'auditable_type',
                'auditable_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
