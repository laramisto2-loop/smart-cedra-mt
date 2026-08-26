<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_sheets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('election_contest_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('polling_center_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('polling_station_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reference_code', 50);
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->text('reconciliation_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->unique([
                'tenant_id',
                'reference_code',
            ]);

            $table->unique([
                'election_contest_id',
                'polling_station_id',
            ]);

            $table->index([
                'tenant_id',
                'election_contest_id',
                'status',
            ], 'tally_tenant_contest_status_idx');

            $table->index([
                'tenant_id',
                'polling_center_id',
                'status',
            ], 'tally_tenant_center_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_sheets');
    }
};
