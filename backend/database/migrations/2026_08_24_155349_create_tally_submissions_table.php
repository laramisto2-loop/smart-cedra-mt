<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('tally_sheet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('entered_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->uuid('client_uuid');
            $table->string('reference_code', 50);
            $table->unsignedTinyInteger('entry_number');
            $table->string('status', 20)->default('draft');

            $table->unsignedInteger('registered_voters')->nullable();
            $table->unsignedInteger('ballots_cast')->nullable();
            $table->unsignedInteger('valid_ballots')->nullable();
            $table->unsignedInteger('invalid_ballots')->nullable();
            $table->unsignedInteger('blank_ballots')->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
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

            $table->unique([
                'tally_sheet_id',
                'entry_number',
            ]);

            $table->index([
                'tenant_id',
                'entered_by_user_id',
                'status',
            ], 'submission_tenant_user_status_idx');
        });

        Schema::table('tally_sheets', function (Blueprint $table) {
            $table->foreignId('approved_submission_id')
                ->nullable()
                ->after('approved_by_user_id')
                ->constrained('tally_submissions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tally_sheets', function (Blueprint $table) {
            $table->dropForeign([
                'approved_submission_id',
            ]);

            $table->dropColumn('approved_submission_id');
        });

        Schema::dropIfExists('tally_submissions');
    }
};
