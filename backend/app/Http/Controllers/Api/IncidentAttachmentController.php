<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentAttachmentRequest;
use App\Http\Resources\IncidentAttachmentResource;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class IncidentAttachmentController extends Controller
{
    public function store(
        StoreIncidentAttachmentRequest $request,
        Incident $incident
    ): JsonResponse {
        $clientUuid = $request->validated('client_uuid')
            ?: Str::uuid()->toString();

        $existing = IncidentAttachment::query()
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing !== null) {
            if ((int) $existing->incident_id !== (int) $incident->id) {
                throw new HttpException(
                    Response::HTTP_CONFLICT,
                    'This attachment identifier already belongs to another incident.'
                );
            }

            Gate::authorize(
                'manageAttachments',
                $incident
            );

            return (new IncidentAttachmentResource(
                $existing->load('uploader')
            ))->response();
        }

        $file = $request->file('file');
        $tenantId = app(TenantContext::class)->id();
        $extension = $file->guessExtension() ?: 'bin';
        $filename = "{$clientUuid}.{$extension}";
        $directory = "incidents/{$tenantId}/{$incident->id}";
        $disk = 'local';

        $checksum = hash_file(
            'sha256',
            $file->getRealPath()
        );

        $path = Storage::disk($disk)->putFileAs(
            $directory,
            $file,
            $filename
        );

        if ($path === false) {
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'The incident attachment could not be stored.'
            );
        }

        try {
            $attachment = IncidentAttachment::create([
                'incident_id' => $incident->id,
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

        return (new IncidentAttachmentResource(
            $attachment->load('uploader')
        ))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function download(
        IncidentAttachment $incidentAttachment
    ): StreamedResponse {
        $incident = $incidentAttachment->incident;

        Gate::authorize('view', $incident);

        abort_unless(
            Storage::disk($incidentAttachment->disk)->exists(
                $incidentAttachment->path
            ),
            Response::HTTP_NOT_FOUND
        );

        return Storage::disk($incidentAttachment->disk)->download(
            $incidentAttachment->path,
            $incidentAttachment->original_name,
            [
                'Content-Type' => $incidentAttachment->mime_type,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function destroy(
        IncidentAttachment $incidentAttachment
    ): Response {
        $incident = $incidentAttachment->incident;

        Gate::authorize(
            'manageAttachments',
            $incident
        );

        Storage::disk($incidentAttachment->disk)->delete(
            $incidentAttachment->path
        );

        $incidentAttachment->delete();

        return response()->noContent();
    }
}
