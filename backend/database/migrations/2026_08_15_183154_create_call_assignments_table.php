<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('call_queue_id')
                ->constrained('call_queues')
                ->cascadeOnDelete();

            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();

            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('assigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status', 20)->default('pending');
            $table->string('priority', 20)->default('normal');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'call_queue_id', 'contact_id'],
                'call_assignments_queue_contact_unique'
            );

            $table->index(
                ['tenant_id', 'assigned_to_user_id', 'status'],
                'call_assignments_agent_status_idx'
            );

            $table->index(
                ['tenant_id', 'call_queue_id', 'status'],
                'call_assignments_queue_status_idx'
            );

            $table->index(
                ['tenant_id', 'scheduled_for'],
                'call_assignments_schedule_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_assignments');
    }
};
// This gives each contact one assignment per call queue and tracks who owns it, its priority, schedule, and progress
