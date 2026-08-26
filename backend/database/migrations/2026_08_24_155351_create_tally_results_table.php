<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_results', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('tally_submission_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('election_option_id')
                ->constrained();

            $table->unsignedInteger('votes');
            $table->timestamps();

            $table->unique([
                'tally_submission_id',
                'election_option_id',
            ]);

            $table->index([
                'tenant_id',
                'election_option_id',
            ], 'result_tenant_option_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_results');
    }
};
