<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Contact;
use App\Models\ContactConsent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SplFileObject;

class ContactImportService
{
    private const MAX_ROWS = 25000;

    private const PREVIEW_LIMIT = 200;

    private const ERROR_LIMIT = 200;

    /**
     * @var array<string, int|null>
     */
    private array $areaCache = [];

    /**
     * @var array<string, bool>
     */
    private array $existingCache = [];

    public function __construct(
        private readonly ContactCsvService $csvService
    ) {}

    /**
     * Validate and classify an uploaded CSV without writing records.
     *
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $file): array
    {
        $this->areaCache = [];
        $this->existingCache = [];

        $csv = $this->openFile($file);
        $expectedHeaders = $this->csvService->headers();
        $actualHeaders = $this->readHeaders($csv);

        if ($actualHeaders !== $expectedHeaders) {
            throw ValidationException::withMessages([
                'file' => [
                    'The CSV columns do not match the contact template.',
                    'Expected: '.implode(', ', $expectedHeaders),
                ],
            ]);
        }

        $summary = [
            'total' => 0,
            'create' => 0,
            'update' => 0,
            'invalid' => 0,
        ];

        $previewRows = [];
        $errorRows = [];
        $seenReferenceCodes = [];
        $line = 1;

        while (! $csv->eof()) {
            $record = $csv->fgetcsv();
            $line++;

            if (
                $record === false
                || $this->isBlankRow($record)
            ) {
                continue;
            }

            $summary['total']++;

            if ($summary['total'] > self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => [
                        'The CSV may contain no more than '
                        .self::MAX_ROWS.' data rows.',
                    ],
                ]);
            }

            [$data, $errors] = $this->mapRecord(
                $expectedHeaders,
                $record
            );

            $errors = array_merge_recursive(
                $errors,
                $this->validateRow($data)
            );

            $referenceCode = $data['reference_code'] ?? null;

            if (is_string($referenceCode)) {
                $uniqueKey = mb_strtolower($referenceCode);

                if (isset($seenReferenceCodes[$uniqueKey])) {
                    $errors['_row'][] =
                        'This reference code is duplicated inside the CSV.';
                } else {
                    $seenReferenceCodes[$uniqueKey] = true;
                }
            }

            if ($errors !== []) {
                $status = 'invalid';
            } elseif ($this->recordExists($data)) {
                $status = 'update';
            } else {
                $status = 'create';
            }

            $summary[$status]++;

            $result = [
                'line' => $line,
                'status' => $status,
                'data' => $data,
                'errors' => $errors,
            ];

            if (count($previewRows) < self::PREVIEW_LIMIT) {
                $previewRows[] = $result;
            }

            if (
                $status === 'invalid'
                && count($errorRows) < self::ERROR_LIMIT
            ) {
                $errorRows[] = $result;
            }
        }

        if ($summary['total'] === 0) {
            throw ValidationException::withMessages([
                'file' => [
                    'The CSV does not contain any contact rows.',
                ],
            ]);
        }

        return [
            'type' => 'contacts',
            'filename' => $file->getClientOriginalName(),
            'headers' => $expectedHeaders,
            'summary' => $summary,
            'rows' => $previewRows,
            'error_rows' => $errorRows,
            'truncated' => (
                $summary['total'] > count($previewRows)
            ),
        ];
    }

    /**
     * Validate and persist every row in one transaction.
     *
     * @return array<string, mixed>
     */
    public function import(UploadedFile $file): array
    {
        return DB::transaction(function () use ($file): array {
            $preview = $this->preview($file);

            if ($preview['summary']['invalid'] > 0) {
                throw ValidationException::withMessages([
                    'file' => [
                        'The import was cancelled because the CSV contains invalid rows.',
                    ],
                ]);
            }

            $csv = $this->openFile($file);
            $headers = $this->readHeaders($csv);
            $created = 0;
            $updated = 0;

            while (! $csv->eof()) {
                $record = $csv->fgetcsv();

                if (
                    $record === false
                    || $this->isBlankRow($record)
                ) {
                    continue;
                }

                [$data] = $this->mapRecord(
                    $headers,
                    $record
                );

                if ($this->persistRow($data)) {
                    $created++;
                } else {
                    $updated++;
                }
            }

            return [
                'type' => 'contacts',
                'filename' => $file->getClientOriginalName(),
                'summary' => [
                    'total' => $created + $updated,
                    'created' => $created,
                    'updated' => $updated,
                ],
            ];
        });
    }

    private function openFile(
        UploadedFile $file
    ): SplFileObject {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => [
                    'The uploaded CSV could not be opened.',
                ],
            ]);
        }

        $csv = new SplFileObject($path, 'rb');
        $csv->setCsvControl(',', '"', '');

        return $csv;
    }

    /**
     * @return array<int, string>
     */
    private function readHeaders(
        SplFileObject $csv
    ): array {
        $headers = $csv->fgetcsv();

        if (
            $headers === false
            || $this->isBlankRow($headers)
        ) {
            throw ValidationException::withMessages([
                'file' => [
                    'The CSV header row is missing.',
                ],
            ]);
        }

        return array_map(
            function ($header, $index): string {
                $value = trim((string) $header);

                if ($index === 0) {
                    $value = preg_replace(
                        '/^\xEF\xBB\xBF/',
                        '',
                        $value
                    ) ?? $value;
                }

                return $value;
            },
            $headers,
            array_keys($headers)
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string|null>  $record
     * @return array{
     *     0: array<string, string|null>,
     *     1: array<string, array<int, string>>
     * }
     */
    private function mapRecord(
        array $headers,
        array $record
    ): array {
        $errors = [];

        if (count($record) !== count($headers)) {
            $errors['_row'][] = sprintf(
                'Expected %d columns but found %d.',
                count($headers),
                count($record)
            );
        }

        $record = array_slice(
            array_pad($record, count($headers), null),
            0,
            count($headers)
        );

        $data = [];

        foreach ($headers as $index => $header) {
            $value = $record[$index];

            if (is_string($value)) {
                $value = trim($value);
                $value = $this->restoreSpreadsheetValue($value);
            }

            $data[$header] = $value === '' ? null : $value;
        }

        if ($data['preferred_language'] === null) {
            $data['preferred_language'] = 'en';
        }

        if ($data['status'] === null) {
            $data['status'] = 'active';
        }

        return [$data, $errors];
    }

    private function restoreSpreadsheetValue(
        string $value
    ): string {
        if (! str_starts_with($value, "'")) {
            return $value;
        }

        $candidate = substr($value, 1);
        $trimmedCandidate = ltrim($candidate);

        $startsWithFormulaCharacter = preg_match(
            '/^[=+@]/',
            $trimmedCandidate
        ) === 1;

        $startsWithUnsafeDash = str_starts_with(
            $trimmedCandidate,
            '-'
        ) && ! is_numeric($trimmedCandidate);

        if (
            $startsWithFormulaCharacter
            || $startsWithUnsafeDash
        ) {
            return $candidate;
        }

        return $value;
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, array<int, string>>
     */
    private function validateRow(array $data): array
    {
        $validator = Validator::make(
            $data,
            $this->rules()
        );

        $errors = $validator->errors()->toArray();
        $areaCode = $data['area_code'] ?? null;

        if (
            is_string($areaCode)
            && $this->areaId($areaCode) === null
        ) {
            $errors['area_code'][] =
                'The referenced area does not exist in this tenant.';
        }

        return $errors;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'reference_code' => [
                'required',
                'string',
                'max:50',
            ],
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],
            'last_name' => [
                'required',
                'string',
                'max:255',
            ],
            'name_ar' => [
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'area_code' => [
                'nullable',
                'string',
                'max:50',
            ],
            'preferred_language' => [
                'required',
                'string',
                Rule::in([
                    'en',
                    'ar',
                ]),
            ],
            'preferred_channel' => [
                'nullable',
                'string',
                Rule::in(ContactConsent::CHANNELS),
            ],
            'status' => [
                'required',
                'string',
                Rule::in(Contact::STATUSES),
            ],
            'source' => [
                'nullable',
                'string',
                'max:50',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function recordExists(array $data): bool
    {
        $referenceCode = $data['reference_code'] ?? null;

        if (! is_string($referenceCode)) {
            return false;
        }

        $cacheKey = mb_strtolower($referenceCode);

        return $this->existingCache[$cacheKey] ??=
            Contact::query()
                ->where('reference_code', $referenceCode)
                ->exists();
    }

    private function areaId(string $code): ?int
    {
        $cacheKey = mb_strtolower($code);

        if (array_key_exists($cacheKey, $this->areaCache)) {
            return $this->areaCache[$cacheKey];
        }

        $id = Area::query()
            ->where('code', $code)
            ->value('id');

        $this->areaCache[$cacheKey] = $id === null
            ? null
            : (int) $id;

        return $this->areaCache[$cacheKey];
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function persistRow(array $data): bool
    {
        $contact = Contact::query()->firstOrNew([
            'reference_code' => $data['reference_code'],
        ]);

        $created = ! $contact->exists;

        $areaCode = $data['area_code'];

        $contact->fill([
            'area_id' => $areaCode === null
                ? null
                : $this->areaId($areaCode),
            'reference_code' => $data['reference_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name_ar' => $data['name_ar'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'preferred_language' => $data['preferred_language'],
            'preferred_channel' => $data['preferred_channel'],
            'status' => $data['status'],
            'source' => $data['source'],
            'notes' => $data['notes'],
        ]);

        if ($created) {
            $userId = Auth::id();

            if ($userId !== null) {
                $contact->created_by_user_id = (int) $userId;
            }
        }

        $contact->save();

        return $created;
    }
}
