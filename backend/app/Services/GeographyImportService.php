<?php

namespace App\Services;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use SplFileObject;

class GeographyImportService
{
    private const MAX_ROWS = 25000;

    private const PREVIEW_LIMIT = 200;

    private const ERROR_LIMIT = 200;

    /**
     * @var array<string, int|null>
     */
    private array $parentCache = [];

    /**
     * @var array<string, bool>
     */
    private array $existingCache = [];

    public function __construct(
        private readonly GeographyCsvService $csvService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(
        UploadedFile $file,
        string $type
    ): array {
        if (! $this->csvService->supports($type)) {
            throw new InvalidArgumentException(
                'Unsupported geography CSV type.'
            );
        }

        $this->parentCache = [];
        $this->existingCache = [];

        $csv = $this->openFile($file);
        $expectedHeaders = $this->csvService->headers($type);
        $actualHeaders = $this->readHeaders($csv);

        if ($actualHeaders !== $expectedHeaders) {
            throw ValidationException::withMessages([
                'file' => [
                    'The CSV columns do not match the selected template.',
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
        $seenKeys = [];
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
                $record,
                $type
            );

            $errors = array_merge_recursive(
                $errors,
                $this->validateRow($data, $type)
            );

            $uniqueKey = $this->uniqueKey($data, $type);

            if ($uniqueKey !== null) {
                if (isset($seenKeys[$uniqueKey])) {
                    $errors['_row'][] =
                        'This record is duplicated inside the CSV.';
                } else {
                    $seenKeys[$uniqueKey] = true;
                }
            }

            if ($errors !== []) {
                $status = 'invalid';
            } elseif ($this->recordExists($data, $type)) {
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
                    'The CSV does not contain any data rows.',
                ],
            ]);
        }

        return [
            'type' => $type,
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
        array $record,
        string $type
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
            }

            $data[$header] = $value === '' ? null : $value;
        }

        if (
            $type === 'areas'
            && $data['type'] === null
        ) {
            $data['type'] = 'locality';
        }

        return [$data, $errors];
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array<string, array<int, string>>
     */
    private function validateRow(
        array $data,
        string $type
    ): array {
        $validator = Validator::make(
            $data,
            $this->rules($type)
        );

        $errors = $validator->errors()->toArray();
        $parentColumn = $this->parentColumn($type);

        if (
            $parentColumn !== null
            && is_string($data[$parentColumn] ?? null)
            && $this->parentId(
                $type,
                $data[$parentColumn]
            ) === null
        ) {
            $errors[$parentColumn][] =
                'The referenced parent does not exist in this tenant.';
        }

        return $errors;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(string $type): array
    {
        return match ($type) {
            'governorates' => [
                'code' => ['required', 'string', 'max:50'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_ar' => ['required', 'string', 'max:255'],
            ],
            'districts' => [
                'governorate_code' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'code' => ['required', 'string', 'max:255'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_ar' => ['required', 'string', 'max:255'],
            ],
            'areas' => [
                'district_code' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'code' => ['required', 'string', 'max:255'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_ar' => ['required', 'string', 'max:255'],
                'type' => [
                    'required',
                    'string',
                    Rule::in([
                        'locality',
                        'city',
                        'town',
                        'village',
                        'municipality',
                        'neighbourhood',
                    ]),
                ],
                'latitude' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                ],
                'longitude' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                ],
            ],
            'polling-centers' => [
                'area_code' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'code' => ['required', 'string', 'max:255'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_ar' => ['required', 'string', 'max:255'],
                'address_en' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'address_ar' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'latitude' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                ],
                'longitude' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                ],
            ],
            'polling-stations' => [
                'polling_center_code' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'station_number' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'name_en' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'name_ar' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'room' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'registered_voters' => [
                    'nullable',
                    'integer',
                    'min:0',
                    'max:4294967295',
                ],
            ],
            default => throw new InvalidArgumentException(
                'Unsupported geography CSV type.'
            ),
        };
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function uniqueKey(
        array $data,
        string $type
    ): ?string {
        if ($type === 'polling-stations') {
            $parentCode = $data['polling_center_code'] ?? null;
            $stationNumber = $data['station_number'] ?? null;

            if (
                ! is_string($parentCode)
                || ! is_string($stationNumber)
            ) {
                return null;
            }

            return mb_strtolower(
                "{$type}:{$parentCode}:{$stationNumber}"
            );
        }

        $code = $data['code'] ?? null;

        if (! is_string($code)) {
            return null;
        }

        return mb_strtolower("{$type}:{$code}");
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function recordExists(
        array $data,
        string $type
    ): bool {
        if ($type === 'polling-stations') {
            $parentCode = $data['polling_center_code'];
            $parentId = $this->parentId(
                $type,
                $parentCode
            );

            if ($parentId === null) {
                return false;
            }

            $cacheKey = mb_strtolower(
                "{$type}:{$parentId}:{$data['station_number']}"
            );

            return $this->existingCache[$cacheKey] ??=
                PollingStation::query()
                    ->where('polling_center_id', $parentId)
                    ->where(
                        'station_number',
                        $data['station_number']
                    )
                    ->exists();
        }

        $model = match ($type) {
            'governorates' => Governorate::class,
            'districts' => District::class,
            'areas' => Area::class,
            'polling-centers' => PollingCenter::class,
            default => throw new InvalidArgumentException(
                'Unsupported geography CSV type.'
            ),
        };

        $cacheKey = mb_strtolower(
            "{$type}:{$data['code']}"
        );

        return $this->existingCache[$cacheKey] ??=
            $model::query()
                ->where('code', $data['code'])
                ->exists();
    }

    private function parentColumn(
        string $type
    ): ?string {
        return match ($type) {
            'districts' => 'governorate_code',
            'areas' => 'district_code',
            'polling-centers' => 'area_code',
            'polling-stations' => 'polling_center_code',
            default => null,
        };
    }

    private function parentId(
        string $type,
        string $code
    ): ?int {
        $model = match ($type) {
            'districts' => Governorate::class,
            'areas' => District::class,
            'polling-centers' => Area::class,
            'polling-stations' => PollingCenter::class,
            default => null,
        };

        if ($model === null) {
            return null;
        }

        $cacheKey = mb_strtolower(
            "{$type}:{$code}"
        );

        if (array_key_exists($cacheKey, $this->parentCache)) {
            return $this->parentCache[$cacheKey];
        }

        $id = $model::query()
            ->where('code', $code)
            ->value('id');

        $this->parentCache[$cacheKey] = $id === null
            ? null
            : (int) $id;

        return $this->parentCache[$cacheKey];
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
}
