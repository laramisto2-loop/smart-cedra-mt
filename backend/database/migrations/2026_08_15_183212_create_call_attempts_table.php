<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('call_assignment_id')
                ->constrained('call_assignments')
                ->cascadeOnDelete();

            $table->foreignId('performed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('follow_up_task_id')
                ->nullable()
                ->constrained('campaign_tasks')
                ->nullOnDelete();

            $table->uuid('client_uuid');
            $table->string('reference_code', 50);
            $table->string('outcome', 30);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('follow_up_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'client_uuid'],
                'call_attempts_tenant_uuid_unique'
            );

            $table->unique(
                ['tenant_id', 'reference_code'],
                'call_attempts_tenant_reference_unique'
            );

            $table->index(
                ['tenant_id', 'call_assignment_id', 'attempted_at'],
                'call_attempts_assignment_time_idx'
            );

            $table->index(
                ['tenant_id', 'performed_by_user_id', 'attempted_at'],
                'call_attempts_agent_time_idx'
            );

            $table->index(
                ['tenant_id', 'outcome', 'attempted_at'],
                'call_attempts_outcome_time_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_attempts');
    }
};
// This records every call result, its duration and notes, the agent who performed it, retry-safe identity, and any follow-up task
