<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('contact_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            $table->foreignId('message_template_id')
                ->nullable()
                ->constrained('message_templates')
                ->nullOnDelete();

            $table->foreignId('contact_consent_id')
                ->nullable()
                ->constrained('contact_consents')
                ->nullOnDelete();

            $table->foreignId('sent_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->uuid('client_uuid');
            $table->string('reference_code', 50);
            $table->string('channel', 20);
            $table->string('recipient', 255);
            $table->string('template_code', 50)->nullable();
            $table->text('rendered_body');
            $table->json('variables')->nullable();

            $table->string('source', 30)->default('manual');
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 191)->nullable();
            $table->string('status', 30)->default('queued');

            $table->string('consent_status', 20);
            $table->timestamp('consent_checked_at');

            $table->text('suppression_reason')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'client_uuid'],
                'out_msgs_tenant_client_uuid_unique'
            );

            $table->unique(
                ['tenant_id', 'reference_code'],
                'out_msgs_tenant_reference_unique'
            );

            $table->unique(
                ['provider', 'provider_message_id'],
                'out_msgs_provider_message_unique'
            );

            $table->index(
                ['tenant_id', 'contact_id', 'created_at'],
                'out_msgs_tenant_contact_created_idx'
            );

            $table->index(
                ['tenant_id', 'status', 'scheduled_at'],
                'out_msgs_tenant_status_scheduled_idx'
            );

            $table->index(
                ['tenant_id', 'channel', 'status'],
                'out_msgs_tenant_channel_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
