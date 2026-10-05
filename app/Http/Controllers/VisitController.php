<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\ReportTemplate;
use App\Models\VitalSign;
use App\Services\BillingService;
use App\Services\PatientService;
use App\Services\VitalSignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One guided flow for a lab visit:
 * patient (new or returning) -> tests & billing -> vitals -> confirm -> results -> report.
 */
class VisitController extends Controller
{
    public function create(Request $request)
    {
        $tests = LabTest::orderBy('name')->get(['id', 'name', 'price']);
        $doctors = Doctor::orderBy('name')->get(['id', 'name', 'qualification']);
        // A package is billed as ONE line (its billing test) and expands into parameters at result entry.
        $packages = LabTest::whereNotNull('report_template_id')
            ->whereHas('panel', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get(['id', 'name', 'price', 'report_template_id'])
            ->unique(fn ($t) => mb_strtolower(trim($t->name)))->values();
        $panelSizes = \App\Models\ReportTemplateItem::where('item_type', 'parameter')
            ->whereIn('report_template_id', $packages->pluck('report_template_id'))
            ->selectRaw('report_template_id, COUNT(*) c')->groupBy('report_template_id')->pluck('c', 'report_template_id');

        $patient = $request->filled('patient') ? Patient::find($request->integer('patient')) : null;

        return view('visits.create', [
            'tests' => $tests,
            'doctors' => $doctors,
            'packages' => $packages,
            'panelSizes' => $panelSizes,
            'preselected' => $patient ? $this->patientSummary($patient) : null,
            'paymentMethods' => BillingService::PAYMENT_METHODS,
        ]);
    }

    public function searchPatients(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
        $patients = Patient::query()
            ->where(function ($w) use ($like) {
                $w->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('patient_id', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->latest('id')
            ->limit(15)
            ->get();

        return response()->json($patients->map(fn ($p) => $this->patientSummary($p))->values());
    }

    public function store(Request $request, BillingService $billing)
    {
        $isNew = $request->input('patient_mode') === 'new';

        $rules = [
            'patient_mode' => 'required|in:new,existing',
            'existing_patient_id' => 'required_if:patient_mode,existing|nullable|integer|exists:patients,id',
            'doctor_name' => 'nullable|string|max:150',
            'tests' => 'required|array|min:1',
            'tests.*.name' => 'required|string|max:255',
            'tests.*.price' => 'required|numeric|min:0',
            'tests.*.discount' => 'nullable|numeric|min:0',
            'advance_paid' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:' . implode(',', BillingService::PAYMENT_METHODS),
            'vitals' => 'nullable|array',
        ];
        if ($isNew) {
            $patientRules = collect(PatientService::rules())
                ->only(['first_name', 'last_name', 'gender', 'age', 'age_type', 'phone', 'email', 'address'])
                ->mapWithKeys(fn ($rule, $key) => ["patient.$key" => $rule]);
            $rules += $patientRules->all();
        }
        foreach (VitalSignService::rules() as $key => $rule) {
            $rules["vitals.$key"] = $rule;
        }

        $validated = $request->validate($rules, [
            'tests.required' => 'Add at least one test to this visit.',
        ], [
            'patient.first_name' => 'first name',
            'patient.last_name' => 'last name',
            'patient.gender' => 'gender',
            'patient.age' => 'age',
            'patient.phone' => 'phone',
            'patient.email' => 'email',
        ]);

        $net = collect($validated['tests'])->sum(fn ($t) => max(0, (float) $t['price'] - min((float) ($t['discount'] ?? 0), (float) $t['price'])));
        if ((float) ($validated['advance_paid'] ?? 0) > round($net, 2) + 0.001) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'advance_paid' => 'Amount received (₹' . number_format((float) $validated['advance_paid'], 2) . ') is more than the bill (₹' . number_format($net, 2) . ').',
            ]);
        }

        $rows = BillingService::rowsFromRequest(
            array_column($validated['tests'], 'name'),
            array_column($validated['tests'], 'price'),
            array_map(fn ($t) => $t['discount'] ?? 0, $validated['tests'])
        );

        $result = DB::transaction(function () use ($validated, $isNew, $rows, $billing) {
            $doctor = $validated['doctor_name'] ?? null;

            if ($isNew) {
                $patient = PatientService::create($validated['patient'] + [
                    'reference_dr' => $doctor,
                    'payment_method' => $validated['payment_method'],
                ]);
            } else {
                $patient = Patient::lockForUpdate()->findOrFail($validated['existing_patient_id']);
                $patient->update(array_filter([
                    'reference_dr' => $doctor,
                    'payment_method' => $validated['payment_method'],
                ]));
            }

            $payment = $billing->createBill(
                $patient,
                $rows,
                (float) ($validated['advance_paid'] ?? 0),
                $validated['payment_method'],
                'New visit',
                $doctor ?: ($patient->reference_dr ?: 'Self')
            );

            $vitals = $validated['vitals'] ?? [];
            if (VitalSignService::hasAnyMeasure($vitals)) {
                VitalSign::create(VitalSignService::prepare($vitals) + ['patient_id' => $patient->id]);
            }

            return [$patient->fresh(), $payment];
        });

        [$patient, $payment] = $result;
        $patient->ensurePublicToken();

        return response()->json([
            'success' => 'Visit registered for ' . $patient->full_name . ' (ID ' . $patient->patient_id . ').',
            'patient' => $this->patientSummary($patient),
            'payment' => $payment->only(['id', 'total_amount', 'discount', 'net_amount', 'advance_paid', 'balance_due', 'payment_status', 'payment_method']),
            'links' => [
                'results' => route('reports', ['create_for' => $patient->id]),
                'patient_card' => $patient->public_url,
                'new_visit' => route('visits.create'),
            ],
        ]);
    }

    private function patientSummary(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'patient_id' => $patient->patient_id,
            'name' => $patient->full_name,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'age_type' => $patient->age_type,
            'phone' => $patient->phone,
            'reference_dr' => $patient->reference_dr,
            'payment_method' => $patient->payment_method,
            'pending_tests' => $patient->appointments()->where('status', 'Pending')->count(),
            'balance' => (float) $patient->balance,
        ];
    }
}
