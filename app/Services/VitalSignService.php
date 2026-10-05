<?php

namespace App\Services;

class VitalSignService
{
    public const MEASURES = ['temperature', 'pulse', 'respiratory_rate', 'blood_pressure', 'spo2', 'weight', 'height'];

    public static function rules(): array
    {
        return [
            'temperature'      => 'nullable|numeric|between:10,120',
            'temp_unit'        => 'nullable|in:C,F',
            'pulse'            => 'nullable|integer|between:10,300',
            'respiratory_rate' => 'nullable|integer|between:2,100',
            'blood_pressure'   => ['nullable', 'string', 'max:20', 'regex:/^\d{2,3}\s*\/\s*\d{2,3}$/'],
            'spo2'             => 'nullable|integer|between:0,100',
            'weight'           => 'nullable|numeric|between:0.1,500',
            'height'           => 'nullable|numeric|between:10,300',
            'notes'            => 'nullable|string|max:1000',
        ];
    }

    /**
     * Fill defaults and derived values (BMI) on validated vital-sign data.
     */
    public static function prepare(array $data): array
    {
        $data['temp_unit'] = $data['temp_unit'] ?? 'F';

        if (!empty($data['weight']) && !empty($data['height'])) {
            $heightInMeters = $data['height'] / 100;
            $data['bmi'] = min(999.9, round($data['weight'] / ($heightInMeters * $heightInMeters), 1));
        } else {
            $data['bmi'] = null;
        }

        return $data;
    }

    public static function hasAnyMeasure(array $data): bool
    {
        foreach (self::MEASURES as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                return true;
            }
        }

        return false;
    }
}
