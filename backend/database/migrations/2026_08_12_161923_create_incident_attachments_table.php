<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_attachments', function (Blueprint $table) {
            $table->id();

            $table
                ->foreignId('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('incident_id')
                ->constrained()
                ->cascadeOnDelete();

            $table
                ->foreignId('uploaded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Attachments use a client UUID for safe offline retries.
             * Only file metadata is stored in the database.
             */
            $table->uuid('client_uuid');
            $table->string('disk', 50)->default('local');
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamps();

            $table->unique([
                'tenant_id',
                'client_uuid',
            ]);

            $table->index([
                'tenant_id',
                'incident_id',
            ]);

            $table->index([
                'tenant_id',
                'uploaded_by_user_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
    }
};
