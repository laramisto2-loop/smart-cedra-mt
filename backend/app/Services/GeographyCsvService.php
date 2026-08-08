<?php

namespace App\Services;

use App\Models\Area;
use App\Models\District;
use App\Models\Governorate;
use App\Models\PollingCenter;
use App\Models\PollingStation;
use Generator;
use InvalidArgumentException;

class GeographyCsvService
{
    /**
     * @var array<int, string>
     */
    public const TYPES = [
        'governorates',
        'districts',
        'areas',
        'polling-centers',
        'polling-stations',
    ];

    public function supports(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    /**
     * @return array<int, string>
     */
    public function headers(string $type): array
    {
        return match ($type) {
            'governorates' => [
                'code',
                'name_en',
                'name_ar',
            ],
            'districts' => [
                'governorate_code',
                'code',
                'name_en',
                'name_ar',
            ],
            'areas' => [
                'district_code',
                'code',
                'name_en',
                'name_ar',
                'type',
                'latitude',
                'longitude',
            ],
            'polling-centers' => [
                'area_code',
                'code',
                'name_en',
                'name_ar',
                'address_en',
                'address_ar',
                'latitude',
                'longitude',
            ],
            'polling-stations' => [
                'polling_center_code',
                'station_number',
                'name_en',
                'name_ar',
                'room',
                'registered_voters',
            ],
            default => throw new InvalidArgumentException(
                'Unsupported geography CSV type.'
            ),
        };
    }

    public function filename(
        string $type,
        bool $template = false
    ): string {
        $suffix = $template ? '-template' : '-export';

        return "electoflow-{$type}{$suffix}.csv";
    }

    /**
     * @return iterable<int, array<int, int|float|string|null>>
     */
    public function rows(string $type): iterable
    {
        return match ($type) {
            'governorates' => $this->governorateRows(),
            'districts' => $this->districtRows(),
            'areas' => $this->areaRows(),
            'polling-centers' => $this->pollingCenterRows(),
            'polling-stations' => $this->pollingStationRows(),
            default => throw new InvalidArgumentException(
                'Unsupported geography CSV type.'
            ),
        };
    }

    /**
     * Prevent spreadsheet programs from interpreting exported
     * user-controlled text as a formula.
     *
     * @param  array<int, int|float|string|null>  $row
     * @return array<int, int|float|string|null>
     */
    public function sanitizeRow(array $row): array
    {
        return array_map(function ($value) {
            if (! is_string($value)) {
                return $value;
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

    /**
     * @return Generator<int, array<int, string>>
     */
    private function governorateRows(): Generator
    {
        foreach (
            Governorate::query()->lazyById(500) as $governorate
        ) {
            yield [
                $governorate->code,
                $governorate->name_en,
                $governorate->name_ar,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    private function districtRows(): Generator
    {
        $districts = District::query()
            ->with('governorate:id,code')
            ->lazyById(500);

        foreach ($districts as $district) {
            yield [
                $district->governorate->code,
                $district->code,
                $district->name_en,
                $district->name_ar,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, float|string|null>>
     */
    private function areaRows(): Generator
    {
        $areas = Area::query()
            ->with('district:id,code')
            ->lazyById(500);

        foreach ($areas as $area) {
            yield [
                $area->district->code,
                $area->code,
                $area->name_en,
                $area->name_ar,
                $area->type,
                $area->latitude,
                $area->longitude,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, float|string|null>>
     */
    private function pollingCenterRows(): Generator
    {
        $pollingCenters = PollingCenter::query()
            ->with('area:id,code')
            ->lazyById(500);

        foreach ($pollingCenters as $pollingCenter) {
            yield [
                $pollingCenter->area->code,
                $pollingCenter->code,
                $pollingCenter->name_en,
                $pollingCenter->name_ar,
                $pollingCenter->address_en,
                $pollingCenter->address_ar,
                $pollingCenter->latitude,
                $pollingCenter->longitude,
            ];
        }
    }

    /**
     * @return Generator<int, array<int, int|string|null>>
     */
    private function pollingStationRows(): Generator
    {
        $pollingStations = PollingStation::query()
            ->with('pollingCenter:id,code')
            ->lazyById(500);

        foreach ($pollingStations as $pollingStation) {
            yield [
                $pollingStation->pollingCenter->code,
                $pollingStation->station_number,
                $pollingStation->name_en,
                $pollingStation->name_ar,
                $pollingStation->room,
                $pollingStation->registered_voters,
            ];
        }
    }
}
