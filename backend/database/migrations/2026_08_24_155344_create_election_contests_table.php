<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_contests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('activated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('election_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'tenant_id',
                'code',
            ]);

            $table->index([
                'tenant_id',
                'status',
                'election_date',
            ], 'contest_tenant_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_contests');
    }
};
