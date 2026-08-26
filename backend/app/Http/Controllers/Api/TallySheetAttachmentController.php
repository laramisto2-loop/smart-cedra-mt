<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTallySheetAttachmentRequest;
use App\Http\Resources\TallySheetAttachmentResource;
use App\Models\TallySheet;
use App\Models\TallySheetAttachment;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class TallySheetAttachmentController extends Controller
{
    public function store(
        StoreTallySheetAttachmentRequest $request,
        TallySheet $tallySheet
    ): JsonResponse {
        $clientUuid = $request->validated('client_uuid')
            ?: Str::uuid()->toString();

        $existing = TallySheetAttachment::query()
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing !== null) {
            if ((int) $existing->tally_sheet_id !== (int) $tallySheet->id) {
                throw new HttpException(
                    Response::HTTP_CONFLICT,
                    'This attachment identifier already belongs to another tally sheet.'
                );
            }

            Gate::authorize('view', $existing);

            return (new TallySheetAttachmentResource(
                $existing->load('uploader')
            ))->response();
        }

        $file = $request->file('file');
        $tenantId = app(TenantContext::class)->id();
        $extension = $file->guessExtension() ?: 'bin';
        $filename = "{$clientUuid}.{$extension}";
        $directory = "tally-sheets/{$tenantId}/{$tallySheet->id}";
        $disk = 'local';
        $checksum = hash_file('sha256', $file->getRealPath());

        $path = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename
        );

        if ($path === false) {
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'The tally sheet attachment could not be stored.'
            );
        }

        try {
            $attachment = TallySheetAttachment::query()->create([
                'tally_sheet_id' => $tallySheet->id,
                'uploaded_by_user_id' => $request->user()->id,
                'client_uuid' => $clientUuid,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType()
                    ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => $checksum,
                'captured_at' => $request->validated('captured_at'),
                'client_updated_at' => $request->validated(
                    'client_updated_at'
                ),
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return (new TallySheetAttachmentResource(
            $attachment->load('uploader')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function download(
        TallySheetAttachment $tallySheetAttachment
    ): StreamedResponse {
        Gate::authorize('view', $tallySheetAttachment);

        abort_unless(
            Storage::disk($tallySheetAttachment->disk)->exists(
                $tallySheetAttachment->path
            ),
            Response::HTTP_NOT_FOUND
        );

        return Storage::disk($tallySheetAttachment->disk)->download(
            $tallySheetAttachment->path,
            $tallySheetAttachment->original_name,
            [
                'Content-Type' => $tallySheetAttachment->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function destroy(
        TallySheetAttachment $tallySheetAttachment
    ): Response {
        Gate::authorize('delete', $tallySheetAttachment);

        Storage::disk($tallySheetAttachment->disk)->delete(
            $tallySheetAttachment->path
        );

        $tallySheetAttachment->delete();

        return response()->noContent();
    }
}
