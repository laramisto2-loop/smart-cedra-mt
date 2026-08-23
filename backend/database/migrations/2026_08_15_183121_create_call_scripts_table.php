<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_scripts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->string('language_code', 10)->default('en');
            $table->text('description')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('draft');
            $table->timestamp('activated_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'code'],
                'call_scripts_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'status'],
                'call_scripts_tenant_status_idx'
            );

            $table->index(
                ['tenant_id', 'created_by_user_id'],
                'call_scripts_tenant_creator_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_scripts');
    }
};
// This table stores the reusable instructions that call-center agents follow. Scripts begin as draft, can become active, and are isolated by tenant
