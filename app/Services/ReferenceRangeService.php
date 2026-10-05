<?php

namespace App\Services;

use App\Models\LabTest;
use App\Models\Patient;
use App\Models\ReferenceInterval;
use App\Models\TestParameter;

/**
 * Patient-specific reference ranges and result flags.
 * Used both while entering results (live flags) and when a report is saved.
 */
class ReferenceRangeService
{
    /**
     * @return array{text: ?string, min: mixed, max: mixed}|array{}
     */
    public static function resolve(?LabTest $labTest, ?Patient $patient): array
    {
        if (!$labTest) {
            return [];
        }

        $age = (int) ($patient?->age ?? 0);
        $gender = strtolower((string) ($patient?->gender ?? ''));
        $ageType = strtolower((string) ($patient?->age_type ?: 'Years'));

        $interval = $labTest->referenceIntervals
            ->filter(function ($interval) use ($age, $gender, $ageType) {
                $intervalGender = strtolower((string) $interval->gender);
                $genderMatches = !$interval->gender || $intervalGender === $gender || $intervalGender === 'any' || $gender === '';
                $minMatches = $interval->age_min === null || $age >= (int) $interval->age_min;
                $maxMatches = $interval->age_max === null || $age <= (int) $interval->age_max;
                $ageTypeMatches = !$interval->age_type || strtolower($interval->age_type) === $ageType || strtolower($interval->age_type) === 'any';
                return $genderMatches && $minMatches && $maxMatches && $ageTypeMatches;
            })
            // Most specific first: an exact gender match, then the narrowest age band.
            ->sortBy(fn ($interval) => [
                strtolower((string) $interval->gender) === $gender ? 0 : 1,
                ($interval->age_max ?? 200) - ($interval->age_min ?? 0),
            ])
            ->first();

        if ($interval) {
            return [
                'text' => $interval->reference_text,
                'min' => $interval->min_value,
                'max' => $interval->max_value,
            ];
        }

        $parameter = $labTest->parameter;
        if (!$parameter) {
            return [];
        }

        $maleRef = $parameter->male_reference;
        $femaleRef = $parameter->female_reference;

        if ($gender === '' && $maleRef && $femaleRef && $maleRef !== $femaleRef) {
            $text = "Male: {$maleRef}\nFemale: {$femaleRef}";
        } else {
            $text = $gender === 'female' ? ($femaleRef ?: $parameter->biological_reference) : ($maleRef ?: $parameter->biological_reference);
        }

        $isFemale = $gender === 'female';

        return [
            'text' => $text,
            'min' => $isFemale ? $parameter->female_min : $parameter->male_min,
            'max' => $isFemale ? $parameter->female_max : $parameter->male_max,
        ];
    }

    /**
     * Flag for a result: ↑ high, ↓ low, C critical, +/-, +, - for immunoassays,
     * '' when within range, null when it cannot be judged (non-numeric / no range).
     */
    public static function flag(mixed $observedValue, ?TestParameter $parameter, array $reference, ?string $gender): ?string
    {
        if (!is_numeric($observedValue)) {
            return null;
        }

        $value = (float) $observedValue;

        if ($parameter) {
            if ($parameter->critical_low !== null && $parameter->critical_low !== '' && $value <= (float) $parameter->critical_low) {
                return 'C';
            }
            if ($parameter->critical_high !== null && $parameter->critical_high !== '' && $value >= (float) $parameter->critical_high) {
                return 'C';
            }
            if ($parameter->is_immunoassay) {
                if ($value < 0.9) {
                    return '-';
                }
                return $value <= 1.1 ? '+/-' : '+';
            }
        }

        $min = $reference['min'] ?? null;
        $max = $reference['max'] ?? null;

        if ($min === null && $max === null && $parameter) {
            $isFemale = strtolower((string) $gender) === 'female';
            $min = $isFemale ? $parameter->female_min : $parameter->male_min;
            $max = $isFemale ? $parameter->female_max : $parameter->male_max;
        }

        if ($min !== null && $min !== '' && $value < (float) $min) {
            return '↓';
        }
        if ($max !== null && $max !== '' && $value > (float) $max) {
            return '↑';
        }

        return ($min !== null || $max !== null) ? '' : null;
    }

    /** Keep the all-ages male/female interval in step with the parameter's simple ranges. */
    public static function syncDefaultIntervals(TestParameter $parameter): void
    {
        foreach (['Male', 'Female'] as $gender) {
            $isFemale = $gender === 'Female';
            ReferenceInterval::updateOrCreate(
                ['lab_test_id' => $parameter->lab_test_id, 'gender' => $gender, 'age_min' => 0, 'age_max' => 200],
                [
                    'reference_text' => $isFemale ? $parameter->female_reference : $parameter->male_reference,
                    'min_value' => $isFemale ? $parameter->female_min : $parameter->male_min,
                    'max_value' => $isFemale ? $parameter->female_max : $parameter->male_max,
                ]
            );
        }
    }
}
