<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_queues', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignId('call_script_id')
                ->nullable()
                ->constrained('call_scripts')
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'code'],
                'call_queues_tenant_code_unique'
            );

            $table->index(
                ['tenant_id', 'status', 'priority'],
                'call_queues_tenant_status_priority_idx'
            );

            $table->index(
                ['tenant_id', 'call_script_id'],
                'call_queues_tenant_script_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_queues');
    }
};
// This table represents an organized calling campaign, such as “Volunteer confirmation calls,” and optionally connects it to an agent script
