<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnout_snapshots', function (Blueprint $table) {
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

            /*
             * A polling center is required when the snapshot is
             * submitted. The nullable database column preserves the
             * historical aggregate if geography is later removed.
             */
            $table
                ->foreignId('polling_center_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             * A null station represents a center-level aggregate.
             * When present, the station must belong to the selected
             * polling center and active tenant.
             */
            $table
                ->foreignId('polling_station_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /*
             * client_uuid makes retried offline submissions
             * idempotent. The same device report may be safely sent
             * again without creating a duplicate snapshot.
             */
            $table->uuid('client_uuid');
            $table->string('reference_code', 50);

            /*
             * Only aggregate counts are stored. No voter identity,
             * ballot choice, or individual participation record is
             * permitted in this table.
             */
            $table->unsignedInteger('registered_voters')->nullable();
            $table->unsignedInteger('turnout_count');

            $table->string('source', 20)->default('field');
            $table->text('notes')->nullable();

            /*
             * captured_at is the time reported by the field device.
             * received_at is the server receipt time and supports
             * offline synchronization auditing.
             */
            $table->timestamp('captured_at');
            $table->timestamp('received_at')->useCurrent();

            $table->timestamps();

            $table->unique([
                'tenant_id',
                'client_uuid',
            ]);

            $table->unique([
                'tenant_id',
                'reference_code',
            ]);

            $table->index(
                ['tenant_id', 'polling_center_id', 'captured_at'],
                'turnout_tenant_center_captured_idx'
            );

            $table->index(
                ['tenant_id', 'polling_station_id', 'captured_at'],
                'turnout_tenant_station_captured_idx'
            );

            $table->index(
                ['tenant_id', 'reported_by_user_id', 'captured_at'],
                'turnout_tenant_reporter_captured_idx'
            );

            $table->index(
                ['tenant_id', 'captured_at'],
                'turnout_tenant_captured_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnout_snapshots');
    }
};
