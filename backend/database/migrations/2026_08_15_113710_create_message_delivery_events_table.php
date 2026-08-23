<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'message_delivery_events',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->cascadeOnDelete();

                $table->foreignId('outbound_message_id')
                    ->constrained('outbound_messages')
                    ->cascadeOnDelete();

                $table->string('provider', 30)->nullable();
                $table->string('provider_event_id', 191)->nullable();
                $table->string('event_type', 50);
                $table->string('status', 30)->nullable();

                // Store only sanitized provider metadata here.
                $table->json('metadata')->nullable();

                $table->timestamp('occurred_at');
                $table->timestamp('received_at')->useCurrent();

                $table->timestamps();

                $table->unique(
                    ['provider', 'provider_event_id'],
                    'msg_events_provider_event_unique'
                );

                $table->index(
                    [
                        'tenant_id',
                        'outbound_message_id',
                        'occurred_at',
                    ],
                    'msg_events_tenant_message_occurred_idx'
                );

                $table->index(
                    ['tenant_id', 'status', 'occurred_at'],
                    'msg_events_tenant_status_occurred_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('message_delivery_events');
    }
};
