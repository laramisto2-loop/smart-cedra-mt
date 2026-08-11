<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'contact_segment',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('contact_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('segment_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('added_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('added_at')
                    ->useCurrent();

                $table->timestamps();

                $table->unique([
                    'segment_id',
                    'contact_id',
                ]);

                $table->index([
                    'tenant_id',
                    'contact_id',
                ]);

                $table->index([
                    'tenant_id',
                    'segment_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_segment');
    }
};
