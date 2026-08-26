<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'tally_sheet_attachments',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('tally_sheet_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('uploaded_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->uuid('client_uuid');
                $table->string('disk', 50)->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 150);
                $table->unsignedBigInteger('size_bytes');
                $table->char('checksum_sha256', 64);
                $table->timestamp('captured_at')->nullable();
                $table->timestamp('client_updated_at')->nullable();
                $table->timestamps();

                $table->unique([
                    'tenant_id',
                    'client_uuid',
                ]);

                $table->unique([
                    'disk',
                    'path',
                ]);

                $table->index([
                    'tenant_id',
                    'tally_sheet_id',
                ], 'attachment_tenant_tally_idx');

                $table->index([
                    'tenant_id',
                    'checksum_sha256',
                ], 'attachment_tenant_checksum_idx');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_sheet_attachments');
    }
};
