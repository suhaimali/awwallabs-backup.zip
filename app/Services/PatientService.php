<?php

namespace App\Services;

use App\Models\Patient;

class PatientService
{
    public const FIELDS = [
        'patient_id', 'first_name', 'last_name', 'gender', 'age', 'age_type', 'phone',
        'email', 'reference_dr', 'status', 'address', 'payment_method',
    ];

    public static function rules(?int $id = null): array
    {
        return [
            // On create the number is (re)generated server-side if it is blank or already taken.
            'patient_id'     => $id ? 'required|string|max:50|unique:patients,patient_id,' . $id : 'nullable|string|max:50',
            'first_name'     => 'required|string|max:100',
            'last_name'      => 'required|string|max:100',
            'gender'         => 'required|in:Male,Female,Other',
            'age'            => 'required|integer|min:0|max:150',
            'age_type'       => 'nullable|in:Years,Months,Days',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'reference_dr'   => 'nullable|string|max:150',
            'status'         => 'nullable|in:Active,Inactive',
            'address'        => 'nullable|string|max:500',
            'payment_method' => 'nullable|in:' . implode(',', BillingService::PAYMENT_METHODS),
            'advance_paid'   => 'nullable|numeric|min:0',
            'test_name'      => 'nullable|array',
            'test_name.*'    => 'nullable|string|max:255',
            'test_price.*'   => 'nullable|numeric|min:0',
            'test_discount.*' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Next human-readable patient number, e.g. 2026-0042.
     * Call inside a transaction so the row lock prevents duplicates.
     */
    public static function nextNumber(): string
    {
        $prefix = date('Y') . '-';
        $last = Patient::where('patient_id', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByRaw('CAST(SUBSTRING(patient_id, ?) AS UNSIGNED) DESC', [strlen($prefix) + 1])
            ->value('patient_id');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a patient record (no billing).
     */
    public static function create(array $data): Patient
    {
        $fields = collect($data)->only(self::FIELDS)->all();

        if (empty($fields['patient_id']) || Patient::where('patient_id', $fields['patient_id'])->exists()) {
            $fields['patient_id'] = self::nextNumber();
        }
        $fields['age_type'] = $fields['age_type'] ?? 'Years';
        $fields['status'] = $fields['status'] ?? 'Active';

        return Patient::create($fields);
    }
}
