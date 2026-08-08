<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportGeographyRequest;
use App\Http\Requests\PreviewGeographyImportRequest;
use App\Models\Governorate;
use App\Services\GeographyCsvService;
use App\Services\GeographyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeographyTransferController extends Controller
{
    public function template(
        GeographyCsvService $csvService,
        string $type
    ): StreamedResponse {
        $this->authorizeDownload($csvService, $type);

        return $this->download(
            csvService: $csvService,
            type: $type,
            template: true
        );
    }

    public function export(
        GeographyCsvService $csvService,
        string $type
    ): StreamedResponse {
        $this->authorizeDownload($csvService, $type);

        return $this->download(
            csvService: $csvService,
            type: $type,
            template: false
        );
    }

    public function preview(
        PreviewGeographyImportRequest $request,
        GeographyCsvService $csvService,
        GeographyImportService $importService,
        string $type
    ): JsonResponse {
        abort_unless($csvService->supports($type), 404);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json([
            'data' => $importService->preview(
                $file,
                $type
            ),
        ]);
    }

    private function authorizeDownload(
        GeographyCsvService $csvService,
        string $type
    ): void {
        abort_unless($csvService->supports($type), 404);

        Gate::authorize('viewAny', Governorate::class);
    }

    public function import(
        ImportGeographyRequest $request,
        GeographyCsvService $csvService,
        GeographyImportService $importService,
        string $type
    ): JsonResponse {
        abort_unless($csvService->supports($type), 404);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json([
            'data' => $importService->import(
                $file,
                $type
            ),
        ]);
    }

    private function download(
        GeographyCsvService $csvService,
        string $type,
        bool $template
    ): StreamedResponse {
        return response()->streamDownload(
            function () use (
                $csvService,
                $type,
                $template
            ): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    throw new RuntimeException(
                        'The CSV output stream could not be opened.'
                    );
                }

                fwrite($stream, "\xEF\xBB\xBF");

                fputcsv(
                    $stream,
                    $csvService->headers($type),
                    ',',
                    '"',
                    ''
                );

                if (! $template) {
                    foreach ($csvService->rows($type) as $row) {
                        fputcsv(
                            $stream,
                            $csvService->sanitizeRow($row),
                            ',',
                            '"',
                            ''
                        );
                    }
                }

                fclose($stream);
            },
            $csvService->filename($type, $template),
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
