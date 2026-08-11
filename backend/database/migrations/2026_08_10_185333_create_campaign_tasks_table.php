<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_tasks', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('contact_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->foreignId('area_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('pending');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamps();

            $table->index([
                'tenant_id',
                'status',
                'due_at',
            ]);

            $table->index([
                'tenant_id',
                'assigned_to_user_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'contact_id',
            ]);

            $table->index([
                'tenant_id',
                'area_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_tasks');
    }
};
