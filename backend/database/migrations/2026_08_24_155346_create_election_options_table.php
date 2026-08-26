<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('election_contest_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('code', 50);
            $table->string('name');
            $table->string('option_type', 20)->default('candidate');
            $table->unsignedSmallInteger('ballot_order')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique([
                'election_contest_id',
                'code',
            ]);

            $table->unique([
                'election_contest_id',
                'ballot_order',
            ]);

            $table->index([
                'tenant_id',
                'election_contest_id',
                'is_active',
            ], 'option_tenant_contest_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_options');
    }
};
