<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('reported_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table
                ->foreignId('campaign_task_id')
                ->nullable()
                ->constrained('campaign_tasks')
                ->nullOnDelete();

            $table
                ->foreignId('area_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->foreignId('polling_center_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table
                ->foreignId('polling_station_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             * client_uuid makes retried offline submissions idempotent.
             * A device may safely resend the same incident without
             * creating duplicate records.
             */
            $table->uuid('client_uuid');
            $table->string('reference_code', 50);

            $table->string('title');
            $table->text('description');
            $table->string('category', 30)->default('general');
            $table->string('severity', 20)->default('medium');
            $table->string('status', 20)->default('submitted');
            $table->text('location_notes')->nullable();

            // DATETIME avoids MariaDB's legacy behavior of silently adding
            // ON UPDATE CURRENT_TIMESTAMP to the table's first TIMESTAMP.
            $table->dateTime('occurred_at');
            $table->timestamp('reported_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            /*
             * These values will support conflict detection when the
             * PWA synchronization layer is implemented.
             */
            $table->timestamp('client_updated_at')->nullable();
            $table->unsignedInteger('sync_version')->default(1);

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'client_uuid',
            ]);

            $table->unique([
                'tenant_id',
                'reference_code',
            ]);

            $table->index([
                'tenant_id',
                'status',
                'severity',
            ]);

            $table->index([
                'tenant_id',
                'assigned_to_user_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'reported_by_user_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'area_id',
                'status',
            ]);

            $table->index([
                'tenant_id',
                'polling_station_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
