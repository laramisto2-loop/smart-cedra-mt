<?php

namespace App\Services;

use App\Models\Contact;
use Generator;

class ContactCsvService
{
    /**
     * @var array<int, string>
     */
    public const HEADERS = [
        'reference_code',
        'first_name',
        'last_name',
        'name_ar',
        'phone',
        'email',
        'address',
        'area_code',
        'preferred_language',
        'preferred_channel',
        'status',
        'source',
        'notes',
    ];

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return self::HEADERS;
    }

    public function filename(bool $template = false): string
    {
        $suffix = $template ? 'template' : 'export';

        return "electoflow-contacts-{$suffix}.csv";
    }

    /**
     * @return Generator<int, array<int, string|null>>
     */
    public function rows(): Generator
    {
        $contacts = Contact::query()
            ->with('area:id,code')
            ->lazyById(500);

        foreach ($contacts as $contact) {
            yield [
                $contact->reference_code,
                $contact->first_name,
                $contact->last_name,
                $contact->name_ar,
                $contact->phone,
                $contact->email,
                $contact->address,
                $contact->area?->code,
                $contact->preferred_language,
                $contact->preferred_channel,
                $contact->status,
                $contact->source,
                $contact->notes,
            ];
        }
    }

    /**
     * Prevent spreadsheet programs from interpreting exported
     * user-controlled text as formulas.
     *
     * @param  array<int, string|null>  $row
     * @return array<int, string|null>
     */
    public function sanitizeRow(array $row): array
    {
        return array_map(function (?string $value): ?string {
            if ($value === null) {
                return null;
            }

            $trimmedValue = ltrim($value);

            $startsWithFormulaCharacter = preg_match(
                '/^[=+@]/',
                $trimmedValue
            ) === 1;

            $startsWithUnsafeDash = str_starts_with(
                $trimmedValue,
                '-'
            ) && ! is_numeric($trimmedValue);

            if (
                $startsWithFormulaCharacter
                || $startsWithUnsafeDash
            ) {
                return "'{$value}";
            }

            return $value;
        }, $row);
    }
}
