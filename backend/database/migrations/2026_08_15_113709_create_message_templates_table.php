<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
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
            $table->string('channel', 20);
            $table->string('provider', 30)->nullable();
            $table->string('provider_template_name', 191)->nullable();
            $table->string('language_code', 10)->default('en');
            $table->string('category', 30)->default('marketing');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'code'],
                'msg_templates_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'channel', 'status'],
                'msg_templates_tenant_channel_status_idx'
            );

            $table->index(
                ['tenant_id', 'created_by_user_id'],
                'msg_templates_tenant_creator_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
