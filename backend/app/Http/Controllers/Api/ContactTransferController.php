<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ContactCsvService;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactTransferController extends Controller
{
    public function template(
        ContactCsvService $csvService
    ): StreamedResponse {
        Gate::authorize('import', Contact::class);

        return $this->download(
            csvService: $csvService,
            template: true
        );
    }

    public function export(
        ContactCsvService $csvService
    ): StreamedResponse {
        Gate::authorize('export', Contact::class);

        return $this->download(
            csvService: $csvService,
            template: false
        );
    }

    private function download(
        ContactCsvService $csvService,
        bool $template
    ): StreamedResponse {
        return response()->streamDownload(
            function () use ($csvService, $template): void {
                $stream = fopen('php://output', 'wb');

                if ($stream === false) {
                    throw new RuntimeException(
                        'The CSV output stream could not be opened.'
                    );
                }

                fwrite($stream, "\xEF\xBB\xBF");

                fputcsv(
                    $stream,
                    $csvService->headers(),
                    ',',
                    '"',
                    ''
                );

                if (! $template) {
                    foreach ($csvService->rows() as $row) {
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
            $csvService->filename($template),
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
