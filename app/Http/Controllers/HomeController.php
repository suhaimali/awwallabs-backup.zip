<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    private function dashboardStats(): array
    {
        $today = today();

        return [
            'totalPatients' => \App\Models\Patient::whereDate('created_at', $today)->count(),
            'totalCompleted' => \App\Models\TestReport::whereDate('created_at', $today)->count(),
            'totalAppointments' => \App\Models\Appointment::whereDate('created_at', $today)->count(),
            // Patients who still have booked tests without results.
            'pendingReports' => \App\Models\Appointment::where('status', 'Pending')->distinct()->count('patient_id'),
        ];
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $stats = $this->dashboardStats();

        // Work queue: patients waiting for results, oldest first.
        $worklist = \App\Models\Patient::query()
            ->whereHas('appointments', fn ($q) => $q->where('status', 'Pending'))
            ->withCount(['appointments as pending_tests_count' => fn ($q) => $q->where('status', 'Pending')])
            ->withMin(['appointments as waiting_since' => fn ($q) => $q->where('status', 'Pending')], 'created_at')
            ->orderBy('waiting_since')
            ->limit(10)
            ->get();

        return view('dashboard', $stats + ['worklist' => $worklist]);
    }

    public function getDashboardStats(\Illuminate\Http\Request $request)
    {
        return response()->json($this->dashboardStats());
    }

    public function testManagement()
    {
        $tests = \App\Models\LabTest::latest()->get();
        return view('tests', compact('tests'));
    }

    public function importCsv(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'master_csv' => 'required|file|mimes:csv,txt',
            'details_csv' => 'required|file|mimes:csv,txt',
        ]);

        $masterCsv = $request->file('master_csv');
        $detailsCsv = $request->file('details_csv');

        // Move the uploaded files to the data directory, overwriting the existing ones
        $masterCsv->move(base_path('data'), 'tbl_lab_test_master.csv');
        $detailsCsv->move(base_path('data'), 'tbl_lab_test_details_master.csv');

        // Run the seeder
        \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'CsvDataSeeder'
        ]);

        return redirect()->route('lab-tests.index')->with('success', 'CSV data imported successfully! Seeder executed.');
    }

    public function appointments(\Illuminate\Http\Request $request)
    {
        $appointments = \App\Models\Appointment::with(['patient'])->latest()->get();
        
        if ($request->ajax()) {
            return response()->json($appointments);
        }

        $patients = \App\Models\Patient::all(); 
        $tests = \App\Models\LabTest::all();
        $units = \App\Models\Unit::orderBy('name')->get();
        return view('appointments', compact('appointments', 'patients', 'tests', 'units'));
    }

    //

    public function toggleSidebar(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        $user->sidebar_collapsed = !$user->sidebar_collapsed;
        $user->save();

        return response()->json(['success' => true, 'collapsed' => $user->sidebar_collapsed]);
    }

    public function reports(\Illuminate\Http\Request $request)
    {
        $reports = \App\Models\TestReport::with('patient')->latest()->get();
        $patients = \App\Models\Patient::orderBy('first_name')->get();
        $tests = \App\Models\LabTest::with(['parameter', 'referenceIntervals'])->orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get();
        $subCategories = \App\Models\SubCategory::orderBy('name')->get();
        $units = \App\Models\Unit::orderBy('name')->get();
        $templates = \App\Models\ResultTemplate::orderBy('name')->get();
        $referenceTemplates = \App\Models\ReferenceTemplate::orderBy('name')->get();
        $flagTemplates = \App\Models\FlagTemplate::orderBy('name')->get();
        $signatures = \App\Models\ReportSignature::orderBy('name')->get();
        $reportTemplates = \App\Models\ReportTemplate::with('items')->orderBy('name')->get();

        // Coming from the visit workflow: open "Generate Report" pre-filled with the patient's pending tests.
        $prefill = null;
        if ($request->filled('create_for')) {
            $patient = \App\Models\Patient::find($request->integer('create_for'));
            if ($patient) {
                $pending = $patient->appointments()->where('status', 'Pending')->orderBy('id')->get();
                $prefill = [
                    'patient_id' => $patient->id,
                    'doctor_name' => $pending->first()->doctor_name ?? ($patient->reference_dr ?: 'Self'),
                    'tests' => $pending->pluck('test_name')->values(),
                    'rows' => app(\App\Services\ReportBuilder::class)->forTestNames($pending->pluck('test_name')->all(), $patient),
                ];
            }
        }

        $openReportId = $request->integer('open') ?: null;
        $catalog = app(\App\Services\ReportBuilder::class)->catalog();

        return view('reports', compact('reports', 'patients', 'tests', 'categories', 'subCategories', 'units', 'templates', 'referenceTemplates', 'flagTemplates', 'signatures', 'reportTemplates', 'prefill', 'openReportId', 'catalog'));
    }


    public function reportSignatures()
    {
        $signatures = \App\Models\ReportSignature::latest()->get();
        return view('report_signatures', compact('signatures'));
    }

    public function storeReportSignature(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'signature_image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
            'pin' => 'required|string|min:4|max:20',
        ]);

        $imageFile = $request->file('signature_image');
        $base64Image = 'data:' . $imageFile->getMimeType() . ';base64,' . base64_encode(file_get_contents($imageFile->getRealPath()));

        \App\Models\ReportSignature::create([
            'name' => $validated['name'],
            'image_data' => $base64Image,
            'pin_hash' => \Illuminate\Support\Facades\Hash::make($validated['pin']),
        ]);

        return redirect()->route('report-signatures.index')->with('success', 'Signature added successfully.');
    }

    public function updateReportSignature(\Illuminate\Http\Request $request, $id)
    {
        $signature = \App\Models\ReportSignature::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'pin' => 'nullable|string|min:4|max:20',
        ]);

        $payload = ['name' => $validated['name']];

        if ($request->hasFile('signature_image')) {
            $imageFile = $request->file('signature_image');
            $payload['image_data'] = 'data:' . $imageFile->getMimeType() . ';base64,' . base64_encode(file_get_contents($imageFile->getRealPath()));
        }

        if (!empty($validated['pin'])) {
            $payload['pin_hash'] = \Illuminate\Support\Facades\Hash::make($validated['pin']);
        }

        $signature->update($payload);

        return redirect()->route('report-signatures.index')->with('success', 'Signature updated successfully.');
    }

    public function deleteReportSignature($id)
    {
        $signature = \App\Models\ReportSignature::findOrFail($id);
        $signature->delete();

        return response()->json(['success' => 'Signature deleted successfully.']);
    }

    public function reportSignatureImage($id)
    {
        $signature = \App\Models\ReportSignature::findOrFail($id);
        
        if (!$signature->image_data || !str_contains($signature->image_data, ';base64,')) {
            abort(404);
        }

        $parts = explode(';base64,', $signature->image_data);
        if (count($parts) < 2) {
            abort(404);
        }

        $mime = str_replace('data:', '', $parts[0]);
        $imageData = base64_decode($parts[1]);

        return response($imageData)->header('Content-Type', $mime);
    }

    private function reportRules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'doctor_name' => 'required|string|max:255',
            'sample_received_on' => 'required|date',
            'report_released_on' => 'required|date',
            'notes' => 'nullable|string|max:5000',
            'status' => 'nullable|in:Pending,In Progress,Completed,Cancelled',
            'report_signature_id' => 'nullable|exists:report_signatures,id',
            'signature_pin' => 'required_with:report_signature_id|nullable|string',
            'test_name' => 'nullable|array',
            'test_name.*' => 'nullable|string|max:255',
            'observed_value.*' => 'nullable|string|max:255',
            'lab_test_id.*' => 'nullable|integer',
            'report_template_id.*' => 'nullable|integer',
            'panel_name.*' => 'nullable|string|max:255',
            'panel_notes' => 'nullable|array',
            'panel_notes.*' => 'nullable|string|max:5000',
        ];
    }

    private function panelNotes(\Illuminate\Http\Request $request): ?array
    {
        $notes = collect($request->input('panel_notes', []))
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n, $k) => $n !== '' && trim((string) $k) !== '');

        return $notes->isEmpty() ? null : $notes->all();
    }

    public function storeReport(\Illuminate\Http\Request $request)
    {
        $request->validate($this->reportRules());

        $patient = \App\Models\Patient::findOrFail($request->patient_id);
        $items = $this->buildReportItems($request, $patient);
        $signatureId = $this->verifiedSignatureId($request);

        $report = DB::transaction(function () use ($request, $items, $signatureId) {
            $report = \App\Models\TestReport::create([
                'patient_id' => $request->patient_id,
                'doctor_name' => $request->doctor_name,
                'sample_received_on' => $request->sample_received_on,
                'report_released_on' => $request->report_released_on,
                'barcode' => now()->format('ymd') . random_int(1000, 9999),
                'status' => $request->status ?? 'Completed',
                'notes' => $request->notes,
                'panel_notes' => $this->panelNotes($request),
                'report_signature_id' => $signatureId,
            ]);

            $report->items()->createMany($items);
            $this->auditReport($report, 'created', null, $this->reportSnapshot($report->fresh(['patient', 'items', 'signature'])));

            // The booked tests now have results.
            $testNames = collect($items)->pluck('name')->filter()->all();
            if (!empty($testNames)) {
                \App\Models\Appointment::where('patient_id', $request->patient_id)
                    ->whereIn('test_name', $testNames)
                    ->where('status', 'Pending')
                    ->update(['status' => 'Completed']);
            }

            return $report;
        });

        return response()->json([
            'success' => 'Test report generated successfully!',
            'id' => $report->id,
            'public_url' => $report->public_url,
        ]);
    }

    public function updateReport(\Illuminate\Http\Request $request, $id)
    {
        $report = \App\Models\TestReport::findOrFail($id);
        $request->validate($this->reportRules());

        $oldData = $this->reportSnapshot($report->load(['patient', 'items', 'signature']));
        $patient = \App\Models\Patient::findOrFail($request->patient_id);
        $items = $this->buildReportItems($request, $patient);
        $signatureId = $this->verifiedSignatureId($request);

        DB::transaction(function () use ($report, $request, $items, $signatureId, $oldData) {
            $report->update([
                'patient_id' => $request->patient_id,
                'doctor_name' => $request->doctor_name,
                'sample_received_on' => $request->sample_received_on,
                'report_released_on' => $request->report_released_on,
                'status' => $request->status ?? $report->status ?? 'Completed',
                'notes' => $request->notes,
                'panel_notes' => $this->panelNotes($request),
                'report_signature_id' => $signatureId,
            ]);
            $report->items()->delete();
            $report->items()->createMany($items);
            $this->auditReport($report, 'updated', $oldData, $this->reportSnapshot($report->fresh(['patient', 'items', 'signature'])));
        });

        return response()->json(['success' => 'Test report updated successfully!', 'id' => $report->id]);
    }

    public function getReport($id)
    {
        $report = \App\Models\TestReport::with(['patient', 'items', 'signature'])->findOrFail($id);
        $report->ensurePublicToken();
        $report->patient?->ensurePublicToken();

        $payload = $report->toArray();
        $payload['results'] = $report->items->map(fn ($item) => $this->serializeReportItem($item))->values();
        $payload['panel_notes'] = $report->panel_notes ?: (object) [];
        $payload['signature'] = $this->serializeReportSignature($report->signature);

        $doctor = \App\Models\Doctor::where('name', $report->doctor_name)->first();
        $payload['doctor_qualification'] = $doctor ? $doctor->qualification : null;

        return response()->json($payload);
    }

    public function deleteReport($id)
    {
        $report = \App\Models\TestReport::with(['patient', 'items', 'signature'])->findOrFail($id);
        $this->auditReport($report, 'deleted', $this->reportSnapshot($report), null);
        $report->delete();
        return response()->json(['success' => 'Report deleted successfully!']);
    }


    public function patients()
    {
        $patients = \App\Models\Patient::withCount(['appointments as pending_tests_count' => fn ($q) => $q->where('status', 'Pending')])
            ->latest()->get();
        $labTests = \App\Models\LabTest::orderBy('name')->get(['id', 'name', 'price']);
        $nextPatientId = \App\Services\PatientService::nextNumber();

        return view('patients', compact('patients', 'labTests', 'nextPatientId'));
    }

    /**
     * Payments listing page
     */
    public function payments(\Illuminate\Http\Request $request)
    {
        $payments = \App\Models\Payment::with('patient')->withCount('appointments')->latest('id')->get();
        $patients = \App\Models\Patient::orderBy('first_name')->get();

        if ($request->ajax()) {
            return response()->json($payments);
        }

        $active = $payments->where('payment_status', '!=', 'Refunded');
        $stats = [
            'billed' => $active->sum('net_amount'),
            'collected' => $active->sum('advance_paid'),
            'outstanding' => $active->sum(fn ($p) => max(0, (float) $p->balance_due)),
            'counts' => $payments->countBy('payment_status'),
        ];
        $paymentMethods = \App\Services\BillingService::PAYMENT_METHODS;

        return view('payments', compact('payments', 'patients', 'stats', 'paymentMethods'));
    }

    private function paymentRules(): array
    {
        return [
            'patient_id'     => 'required|exists:patients,id',
            'total_amount'   => 'required|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0|lte:total_amount',
            'advance_paid'   => 'nullable|numeric|min:0',
            // Paid / Partial / Unpaid are worked out from the amounts; only Refunded is set by hand.
            'payment_status' => 'nullable|in:Paid,Partial,Unpaid,Refunded',
            'payment_method' => 'required|in:' . implode(',', \App\Services\BillingService::PAYMENT_METHODS),
            'bill_date'      => 'required|date',
            'remarks'        => 'nullable|string|max:500',
        ];
    }

    /** Derive net, balance and status; reject paying more than the bill. */
    private function preparePayment(array $validated): array
    {
        $validated['discount'] = round((float) ($validated['discount'] ?? 0), 2);
        $validated['advance_paid'] = round((float) ($validated['advance_paid'] ?? 0), 2);
        $validated['net_amount'] = round($validated['total_amount'] - $validated['discount'], 2);
        $validated['balance_due'] = round($validated['net_amount'] - $validated['advance_paid'], 2);

        if ($validated['balance_due'] < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'advance_paid' => 'Amount paid (₹' . number_format($validated['advance_paid'], 2) . ') is more than the net amount (₹' . number_format($validated['net_amount'], 2) . ').',
            ]);
        }

        $validated['payment_status'] = \App\Services\BillingService::status(
            $validated['advance_paid'],
            $validated['balance_due'],
            ($validated['payment_status'] ?? null) === 'Refunded' ? 'Refunded' : null
        );

        return $validated;
    }

    public function storePayment(\Illuminate\Http\Request $request)
    {
        $validated = $this->preparePayment($request->validate($this->paymentRules()));

        \App\Models\Payment::create($validated);

        return response()->json(['success' => 'Payment record created successfully!']);
    }

    /** Record that the remaining balance of a bill has been received. */
    public function settlePayment(\Illuminate\Http\Request $request, \App\Services\BillingService $billing, $id)
    {
        $payment = \App\Models\Payment::findOrFail($id);
        $request->validate(['payment_method' => 'nullable|in:' . implode(',', \App\Services\BillingService::PAYMENT_METHODS)]);

        DB::transaction(function () use ($payment, $request, $billing) {
            $appointments = $payment->appointments()->orderBy('id')->get();
            if ($appointments->isNotEmpty()) {
                $billing->distributeAdvance($appointments, (float) $appointments->sum(fn ($a) => $a->test_price - $a->discount));
                $billing->recalculatePayment($payment);
                $billing->recalculatePatient($payment->patient);
            } else {
                $payment->forceFill([
                    'advance_paid' => $payment->net_amount,
                    'balance_due' => 0,
                    'payment_status' => 'Paid',
                ])->save();
            }
            if ($request->filled('payment_method')) {
                $payment->update(['payment_method' => $request->payment_method]);
            }
        });

        return response()->json(['success' => 'Balance received. Bill #' . $payment->id . ' is now fully paid.']);
    }

    public function getPayment($id)
    {
        $payment = \App\Models\Payment::with('patient')->withCount('appointments')->findOrFail($id);
        return response()->json($payment);
    }

    public function updatePayment(\Illuminate\Http\Request $request, \App\Services\BillingService $billing, $id)
    {
        $payment = \App\Models\Payment::findOrFail($id);
        $input = $request->validate($this->paymentRules());

        // Bills generated from booked tests take their amounts from the tests.
        if ($payment->appointments()->exists()) {
            $input['total_amount'] = (float) $payment->appointments()->sum('test_price');
            $input['discount'] = (float) $payment->appointments()->sum('discount');
            $input['patient_id'] = $payment->patient_id;
        }
        $validated = $this->preparePayment($input);

        DB::transaction(function () use ($payment, $validated, $billing) {
            $payment->update($validated);

            // A bill generated from booked tests: amounts come from the tests, the
            // amount received is spread back over them so patient balances stay right.
            $appointments = $payment->appointments()->orderBy('id')->get();
            if ($appointments->isNotEmpty()) {
                $billing->distributeAdvance($appointments, (float) $validated['advance_paid']);
                $billing->recalculatePayment($payment);
                $billing->recalculatePatient($payment->patient);
            }
        });

        return response()->json(['success' => 'Payment record updated successfully!']);
    }

    public function deletePayment($id)
    {
        $payment = \App\Models\Payment::findOrFail($id);

        if ($payment->appointments()->exists()) {
            return response()->json([
                'error' => 'This bill belongs to booked tests. Remove the tests from the patient (Patients → Edit) and the bill is removed automatically.',
            ], 409);
        }

        $payment->delete();

        return response()->json(['success' => 'Payment record deleted successfully!']);
    }

    public function storePatient(\Illuminate\Http\Request $request, \App\Services\BillingService $billing)
    {
        $validated = $request->validate(\App\Services\PatientService::rules());

        $rows = \App\Services\BillingService::rowsFromRequest(
            $request->input('test_name', []),
            $request->input('test_price', []),
            $request->input('test_discount', [])
        );

        $patient = DB::transaction(function () use ($validated, $rows, $billing) {
            $patient = \App\Services\PatientService::create($validated);

            if (!empty($rows)) {
                $billing->createBill(
                    $patient,
                    $rows,
                    (float) ($validated['advance_paid'] ?? 0),
                    $validated['payment_method'] ?? null,
                    'Auto-generated from Add Patient'
                );
            }

            return $patient;
        });

        return response()->json([
            'success' => 'Patient added successfully! ID: ' . $patient->patient_id,
            'patient' => $patient->fresh(),
        ]);
    }

    public function getPatient($id)
    {
        $patient = \App\Models\Patient::with(['appointments' => fn ($q) => $q->orderBy('id')])->findOrFail($id);
        $patient->ensurePublicToken();

        return response()->json($patient);
    }

    public function updatePatient(\Illuminate\Http\Request $request, \App\Services\BillingService $billing, $id)
    {
        $patient = \App\Models\Patient::findOrFail($id);
        $validated = $request->validate(\App\Services\PatientService::rules((int) $id));

        $rows = \App\Services\BillingService::rowsFromRequest(
            $request->input('test_name', []),
            $request->input('test_price', []),
            $request->input('test_discount', []),
            $request->input('appointment_id', [])
        );

        DB::transaction(function () use ($patient, $validated, $rows, $billing) {
            $patient->update(collect($validated)->only(\App\Services\PatientService::FIELDS)->all());

            $existing = $patient->appointments()->get()->keyBy('id');
            $touchedPayments = $existing->pluck('payment_id')->filter()->unique()->values();
            $keep = [];
            $unbilled = [];

            foreach ($rows as $row) {
                $appointment = $row['appointment_id'] ? $existing->get($row['appointment_id']) : null;
                if ($appointment) {
                    $appointment->update([
                        'test_name' => $row['name'],
                        'test_price' => $row['price'],
                        'discount' => $row['discount'],
                        'total_amount' => $row['price'],
                    ]);
                } else {
                    $appointment = \App\Models\Appointment::create([
                        'patient_id' => $patient->id,
                        'doctor_name' => $patient->reference_dr ?: 'Self',
                        'test_name' => $row['name'],
                        'test_price' => $row['price'],
                        'discount' => $row['discount'],
                        'balance' => $row['price'] - $row['discount'],
                        'total_amount' => $row['price'],
                        'appointment_date' => now()->toDateString(),
                        'appointment_time' => now()->format('H:i'),
                        'status' => 'Pending',
                    ]);
                }
                if (!$appointment->payment_id) {
                    $unbilled[] = $appointment->id;
                }
                $keep[] = $appointment->id;
            }

            // Tests removed from the edit list
            \App\Models\Appointment::where('patient_id', $patient->id)->whereNotIn('id', $keep ?: [0])->delete();

            // New (or legacy, unlinked) tests go on the patient's latest bill
            if (!empty($unbilled)) {
                $payment = \App\Models\Payment::where('patient_id', $patient->id)
                    ->whereIn('id', $touchedPayments->all() ?: [0])->latest('id')->first()
                    ?? \App\Models\Payment::where('patient_id', $patient->id)->latest('id')->first()
                    ?? \App\Models\Payment::create([
                        'patient_id' => $patient->id,
                        'payment_status' => 'Unpaid',
                        'payment_method' => $validated['payment_method'] ?? 'Cash',
                        'bill_date' => now()->toDateString(),
                        'remarks' => 'Auto-generated from Update Patient',
                    ]);
                \App\Models\Appointment::whereIn('id', $unbilled)->update(['payment_id' => $payment->id]);
                $touchedPayments->push($payment->id);
            }

            // The edit form carries one "amount paid" for the whole patient.
            $billing->distributeAdvance(
                $patient->appointments()->orderBy('id')->get(),
                (float) ($validated['advance_paid'] ?? 0)
            );

            if (!empty($validated['payment_method'])) {
                \App\Models\Payment::whereIn('id', $touchedPayments->all() ?: [0])
                    ->update(['payment_method' => $validated['payment_method']]);
            }

            $billing->syncPayments($patient, $touchedPayments);
        });

        return response()->json(['success' => 'Patient updated successfully!']);
    }

    public function deletePatient($id)
    {
        $patient = \App\Models\Patient::findOrFail($id);

        DB::transaction(function () use ($patient) {
            $reports = \App\Models\TestReport::withTrashed()->where('patient_id', $patient->id)->get();
            foreach ($reports as $report) {
                $report->items()->delete();
                $report->forceDelete();
            }
            \App\Models\Appointment::where('patient_id', $patient->id)->delete();
            \App\Models\Payment::where('patient_id', $patient->id)->delete();
            \App\Models\VitalSign::where('patient_id', $patient->id)->delete();
            $patient->delete();
        });

        return response()->json(['success' => 'Patient and all associated records deleted successfully!']);
    }

    // Appointment CRUD Methods
    private function appointmentRules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'doctor_name' => 'nullable|string|max:150',
            'test_name' => 'required|string|max:255',
            'test_price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0|lte:test_price',
            'advance_paid' => 'nullable|numeric|min:0',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required|date_format:H:i,H:i:s',
            'status' => 'required|in:Pending,Completed,Cancelled',
            'reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'nullable|in:' . implode(',', \App\Services\BillingService::PAYMENT_METHODS),
        ];
    }

    public function storeAppointment(\Illuminate\Http\Request $request, \App\Services\BillingService $billing)
    {
        $validated = $request->validate($this->appointmentRules());

        DB::transaction(function () use ($validated, $billing) {
            $patient = \App\Models\Patient::findOrFail($validated['patient_id']);
            $payment = $billing->createBill(
                $patient,
                [[
                    'name' => $validated['test_name'],
                    'price' => (float) $validated['test_price'],
                    'discount' => (float) ($validated['discount'] ?? 0),
                ]],
                (float) ($validated['advance_paid'] ?? 0),
                $validated['payment_method'] ?? null,
                'Auto-generated from Appointment Booking',
                $validated['doctor_name'] ?? null,
                $validated['appointment_date']
            );

            $payment->appointments()->update(collect($validated)->only([
                'appointment_time', 'status', 'reason', 'notes',
            ])->all());
        });

        return response()->json(['success' => 'Booking saved successfully!']);
    }

    public function getAppointment($id)
    {
        $appointment = \App\Models\Appointment::with('patient')->findOrFail($id);
        return response()->json($appointment);
    }

    public function updateAppointment(\Illuminate\Http\Request $request, \App\Services\BillingService $billing, $id)
    {
        $appointment = \App\Models\Appointment::findOrFail($id);
        $validated = $request->validate($this->appointmentRules());

        DB::transaction(function () use ($appointment, $validated, $billing) {
            $oldPatientId = $appointment->patient_id;
            $oldPaymentId = $appointment->payment_id;

            $appointment->update([
                'patient_id' => $validated['patient_id'],
                'doctor_name' => $validated['doctor_name'] ?? 'Self',
                'test_name' => $validated['test_name'],
                'test_price' => $validated['test_price'],
                'discount' => $validated['discount'] ?? 0,
                'total_amount' => $validated['test_price'],
                'appointment_date' => $validated['appointment_date'],
                'appointment_time' => $validated['appointment_time'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $billing->distributeAdvance(collect([$appointment]), (float) ($validated['advance_paid'] ?? 0));

            if ($appointment->payment_id && $oldPatientId == $validated['patient_id']) {
                $billing->recalculatePayment($appointment->payment);
            } else {
                // Unbilled legacy booking, or moved to another patient: give it its own bill.
                $payment = \App\Models\Payment::create([
                    'patient_id' => $validated['patient_id'],
                    'payment_status' => 'Unpaid',
                    'payment_method' => $validated['payment_method'] ?? 'Cash',
                    'bill_date' => $validated['appointment_date'],
                    'remarks' => 'Auto-generated from Update Appointment',
                ]);
                $appointment->update(['payment_id' => $payment->id]);
                $billing->recalculatePayment($payment);
            }

            $billing->syncPayments(\App\Models\Patient::findOrFail($validated['patient_id']), [$appointment->payment_id]);
            if ($oldPatientId != $validated['patient_id'] || $oldPaymentId != $appointment->payment_id) {
                $billing->syncPayments(\App\Models\Patient::findOrFail($oldPatientId), [$oldPaymentId]);
            }
        });

        return response()->json(['success' => 'Booking updated successfully!']);
    }

    public function deleteAppointment(\App\Services\BillingService $billing, $id)
    {
        $appointment = \App\Models\Appointment::findOrFail($id);

        DB::transaction(function () use ($appointment, $billing) {
            $patient = $appointment->patient;
            $paymentId = $appointment->payment_id;
            $appointment->delete();
            if ($patient) {
                $billing->syncPayments($patient, [$paymentId]);
            }
        });

        return response()->json(['success' => 'Booking deleted successfully!']);
    }

    public function getDoctorSuggestions()
    {
        $doctors = \App\Models\Doctor::orderBy('name')->get();
        return response()->json($doctors);
    }

    public function storeDoctor(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'qualification' => 'nullable',
            'phone' => 'nullable',
            'email' => 'nullable|email'
        ]);

        $doctor = \App\Models\Doctor::create($validated);
        return response()->json(['success' => 'Doctor added successfully!', 'doctor' => $doctor]);
    }

    public function updateDoctor(\Illuminate\Http\Request $request, $id)
    {
        $doctor = \App\Models\Doctor::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required',
            'qualification' => 'nullable',
            'phone' => 'nullable',
            'email' => 'nullable|email'
        ]);

        // Optional: Update patients table if name changed
        if ($doctor->name !== $validated['name']) {
            \App\Models\Patient::where('reference_dr', $doctor->name)
                               ->update(['reference_dr' => $validated['name']]);
        }

        $doctor->update($validated);
        return response()->json(['success' => 'Doctor updated successfully!', 'doctor' => $doctor]);
    }

    public function deleteDoctor($id)
    {
        $doctor = \App\Models\Doctor::findOrFail($id);
        // Optional: nullify reference_dr in patients? 
        // \App\Models\Patient::where('reference_dr', $doctor->name)->update(['reference_dr' => null]);
        $doctor->delete();
        return response()->json(['success' => 'Doctor deleted successfully!']);
    }

    // ==========================================
    // PAYMENT MANAGEMENT
    // ==========================================

    public function dailyCollection(\Illuminate\Http\Request $request)
    {
        $date = $request->input('date', date('Y-m-d'));
        
        $payments = \App\Models\Payment::with('patient')
            ->whereDate('bill_date', $date)
            ->get();

        // Cash tally = money actually received (not the billed amount); refunds excluded.
        $received = $payments->where('payment_status', '!=', 'Refunded');
        $totalCollection = $received->sum('advance_paid');
        $totalTransactions = $payments->count();

        // Group by payment method
        $methods = collect();
        foreach ($received->groupBy('payment_method') as $method => $group) {
            $methods->push([
                'method' => $method ?: 'Unknown',
                'amount' => $group->sum('advance_paid'),
                'count' => $group->count()
            ]);
        }

        return view('daily_collection', compact(
            'date', 'payments', 'totalCollection', 'totalTransactions', 'methods'
        ));
    }

    public function incomeReport(\Illuminate\Http\Request $request)
    {
        $query = \App\Models\Payment::with('patient')->orderBy('bill_date', 'desc');

        $startDate = $request->input('start_date', date('Y-m-d'));
        $endDate = $request->input('end_date', date('Y-m-d'));

        $query->whereDate('bill_date', '>=', $startDate)
              ->whereDate('bill_date', '<=', $endDate);

        $payments = $query->get();
        
        $totalIncome = $payments->sum('net_amount');
        $totalTransactions = $payments->count();
        $averageTransaction = $totalTransactions > 0 ? $totalIncome / $totalTransactions : 0;

        // Daily trend
        $dailyTrend = $payments->groupBy('bill_date')->map(function ($row) {
            $income = $row->sum('net_amount');
            $transactions = $row->count();
            return [
                'date' => $row->first()->bill_date,
                'income' => $income,
                'transactions' => $transactions,
                'average' => $transactions > 0 ? $income / $transactions : 0,
            ];
        })->values()->take(30);

        // Payment method breakdown
        $methods = $payments->groupBy('payment_method')->map(function ($row) {
            return [
                'method' => $row->first()->payment_method ?: 'Unknown',
                'amount' => $row->sum('net_amount'),
                'count' => $row->count()
            ];
        })->values();

        // Recent transactions
        $recentTransactions = $payments;

        return view('income_report', compact(
            'totalIncome', 'totalTransactions', 'averageTransaction', 
            'dailyTrend', 'methods', 'recentTransactions'
        ));
    }

    public function unlockIncomeReport(\Illuminate\Http\Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $expected = config('services.income_report.password');
        if (!$expected || !hash_equals($expected, $request->password)) {
            return response()->json(['message' => 'Invalid password.'], 422);
        }

        $request->session()->put('income_report_unlocked', true);

        return response()->json([
            'success' => true,
            'redirect' => route('income-report'),
        ]);
    }

    public function unlockDailyCollection(\Illuminate\Http\Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $expected = config('services.income_report.password');
        if (!$expected || !hash_equals($expected, $request->password)) {
            return response()->json(['message' => 'Invalid password.'], 422);
        }

        $request->session()->put('daily_collection_unlocked', true);

        return response()->json([
            'success' => true,
            'redirect' => route('daily-collection'),
        ]);
    }

    public function verifyAdminPassword(\Illuminate\Http\Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $expected = config('services.income_report.password');
        if (!$expected || !hash_equals($expected, $request->password)) {
            return response()->json(['message' => 'Invalid password.'], 422);
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function updateReportStatus(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,In Progress,Completed,Cancelled',
        ]);

        $report = \App\Models\TestReport::findOrFail($id);
        $report->update(['status' => $request->status]);
        return response()->json(['success' => 'Report status updated live!']);
    }



    // ==========================================
    // LAB TEST MANAGEMENT
    // ==========================================

    public function storeLabTest(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'price' => 'nullable|numeric',
            'payment_method' => 'nullable',
            'description' => 'nullable',
        ]);

        $validated['price'] = $validated['price'] ?? 0;

        $test = \App\Models\LabTest::create($validated);

        return response()->json(['success' => 'Laboratory test registered successfully!', 'test' => $test]);
    }

    private function parameterRules(): array
    {
        return [
            'unit' => 'nullable|string|max:100',
            'male_reference' => 'nullable|string|max:1000',
            'female_reference' => 'nullable|string|max:1000',
            'male_min' => 'nullable|numeric',
            'male_max' => 'nullable|numeric',
            'female_min' => 'nullable|numeric',
            'female_max' => 'nullable|numeric',
            'critical_low' => 'nullable|numeric',
            'critical_high' => 'nullable|numeric',
            'biological_reference' => 'nullable|string|max:2000',
            'is_immunoassay' => 'nullable|boolean',
        ];
    }

    public function quickStoreTest(\Illuminate\Http\Request $request)
    {
        $validatedTest = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string'
        ]);
        $paramData = $request->validate($this->parameterRules());
        $validatedTest['price'] = $validatedTest['price'] ?? 0;

        $test = DB::transaction(function () use ($validatedTest, $paramData) {
            $test = \App\Models\LabTest::create($validatedTest);
            \App\Models\TestParameter::create($paramData + ['lab_test_id' => $test->id]);
            return $test;
        });

        return response()->json(['success' => 'Parameter created successfully!', 'test' => $test]);
    }

    public function updateLabTest(\Illuminate\Http\Request $request, $id)
    {
        $test = \App\Models\LabTest::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required',
            'price' => 'required|numeric',
            'payment_method' => 'nullable',
            'description' => 'nullable',
        ]);

        $test->update($validated);

        return response()->json(['success' => 'Laboratory test updated successfully!', 'test' => $test]);
    }

    public function quickUpdateTest(\Illuminate\Http\Request $request, $id)
    {
        $test = \App\Models\LabTest::findOrFail($id);
        
        $validatedTest = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string'
        ]);
        $paramData = $request->validate($this->parameterRules());
        $validatedTest['price'] = $validatedTest['price'] ?? 0;

        $test->update($validatedTest);

        $parameter = \App\Models\TestParameter::updateOrCreate(
            ['lab_test_id' => $test->id],
            $paramData
        );

        return response()->json(['success' => 'Parameter updated successfully!', 'test' => $test]);
    }

    public function apiTests()
    {
        $tests = \App\Models\LabTest::with(['parameter', 'referenceIntervals'])->get();
        return response()->json($tests);
    }

    public function deleteLabTest($id)
    {
        $test = \App\Models\LabTest::findOrFail($id);

        if (\App\Models\TestReportItem::where('lab_test_id', $test->id)->exists()) {
            return response()->json(['error' => 'Cannot delete test that is used in existing reports.'], 409);
        }

        $test->delete();

        return response()->json(['success' => 'Laboratory test removed successfully!']);
    }

    // ==========================================
    // CATEGORY MANAGEMENT
    // ==========================================

    public function categories(\Illuminate\Http\Request $request)
    {
        $categories = \App\Models\Category::latest()->get();
        return view('categories', compact('categories'));
    }

    public function apiCategories()
    {
        return response()->json(\App\Models\Category::orderBy('name')->get());
    }

    public function storeCategory(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|unique:categories',
            'description' => 'nullable',
        ]);

        $category = \App\Models\Category::create($validated);

        return response()->json(['success' => 'Category added successfully!', 'category' => $category]);
    }

    public function updateCategory(\Illuminate\Http\Request $request, $id)
    {
        $category = \App\Models\Category::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|unique:categories,name,' . $id,
            'description' => 'nullable',
        ]);

        $category->update($validated);

        return response()->json(['success' => 'Category updated successfully!']);
    }

    public function deleteCategory($id)
    {
        $category = \App\Models\Category::findOrFail($id);
        $category->delete();

        return response()->json(['success' => 'Category deleted successfully!']);
    }

    // ==========================================
    // SUB-CATEGORY MANAGEMENT
    // ==========================================

    public function subCategories(\Illuminate\Http\Request $request)
    {
        $subCategories = \App\Models\SubCategory::with('category')->latest()->get();
        $categories = \App\Models\Category::orderBy('name')->get();
        return view('sub_categories', compact('subCategories', 'categories'));
    }

    public function apiSubCategories()
    {
        return response()->json(\App\Models\SubCategory::with('category')->orderBy('name')->get());
    }

    public function storeSubCategory(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required',
            'description' => 'nullable',
        ]);

        $subCategory = \App\Models\SubCategory::create($validated);

        return response()->json(['success' => 'Sub-Category added successfully!', 'subCategory' => $subCategory]);
    }

    public function updateSubCategory(\Illuminate\Http\Request $request, $id)
    {
        $subCategory = \App\Models\SubCategory::findOrFail($id);
        
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required',
            'description' => 'nullable',
        ]);

        $subCategory->update($validated);

        return response()->json(['success' => 'Sub-Category updated successfully!']);
    }

    public function deleteSubCategory($id)
    {
        $subCategory = \App\Models\SubCategory::findOrFail($id);
        $subCategory->delete();

        return response()->json(['success' => 'Sub-Category deleted successfully!']);
    }

    public function masterData()
    {
        $units = \App\Models\Unit::orderBy('name')->get();
        $templates = \App\Models\ResultTemplate::orderBy('name')->get();
        $referenceTemplates = \App\Models\ReferenceTemplate::orderBy('name')->get();
        $flagTemplates = \App\Models\FlagTemplate::orderBy('name')->get();
        return view('master_data', compact('units', 'templates', 'referenceTemplates', 'flagTemplates'));
    }

    public function apiUnits()
    {
        $units = \App\Models\Unit::orderBy('name')->get();
        return response()->json($units);
    }

    public function storeUnit(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate(['name' => 'required|unique:units']);
        $unit = \App\Models\Unit::create($validated);
        return response()->json(['success' => 'Unit added successfully!', 'unit' => $unit]);
    }

    public function updateUnit(\Illuminate\Http\Request $request, $id)
    {
        $validated = $request->validate(['name' => 'required|unique:units,name,'.$id]);
        $unit = \App\Models\Unit::findOrFail($id);
        $unit->update($validated);
        return response()->json(['success' => 'Unit updated!', 'unit' => $unit]);
    }

    public function deleteUnit($id)
    {
        \App\Models\Unit::destroy($id);
        return response()->json(['success' => 'Unit removed!']);
    }

    public function storeResultTemplate(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate(['name' => 'required|unique:result_templates']);
        \App\Models\ResultTemplate::create($validated);
        return response()->json(['success' => 'Result template added!']);
    }

    public function updateResultTemplate(\Illuminate\Http\Request $request, $id)
    {
        $validated = $request->validate(['name' => 'required|unique:result_templates,name,'.$id]);
        $template = \App\Models\ResultTemplate::findOrFail($id);
        $template->update($validated);
        return response()->json(['success' => 'Template updated!']);
    }

    public function deleteResultTemplate($id)
    {
        \App\Models\ResultTemplate::destroy($id);
        return response()->json(['success' => 'Template removed!']);
    }

    public function storeReferenceTemplate(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate(['name' => 'required|unique:reference_templates']);
        \App\Models\ReferenceTemplate::create($validated);
        return response()->json(['success' => 'Reference template added!']);
    }

    public function updateReferenceTemplate(\Illuminate\Http\Request $request, $id)
    {
        $validated = $request->validate(['name' => 'required|unique:reference_templates,name,'.$id]);
        $template = \App\Models\ReferenceTemplate::findOrFail($id);
        $template->update($validated);
        return response()->json(['success' => 'Template updated!']);
    }

    public function deleteReferenceTemplate($id)
    {
        \App\Models\ReferenceTemplate::destroy($id);
        return response()->json(['success' => 'Template removed!']);
    }

    public function storeFlagTemplate(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate(['name' => 'required|unique:flag_templates']);
        \App\Models\FlagTemplate::create($validated);
        return response()->json(['success' => 'Flag template added!']);
    }

    public function updateFlagTemplate(\Illuminate\Http\Request $request, $id)
    {
        $validated = $request->validate(['name' => 'required|unique:flag_templates,name,'.$id]);
        $template = \App\Models\FlagTemplate::findOrFail($id);
        $template->update($validated);
        return response()->json(['success' => 'Template updated!']);
    }

    public function deleteFlagTemplate($id)
    {
        \App\Models\FlagTemplate::destroy($id);
        return response()->json(['success' => 'Template removed!']);
    }

    public function apiReferenceTemplates()
    {
        return response()->json(\App\Models\ReferenceTemplate::orderBy('name')->get());
    }

    public function apiResultTemplates()
    {
        return response()->json(\App\Models\ResultTemplate::orderBy('name')->get());
    }

    public function apiFlagTemplates()
    {
        return response()->json(\App\Models\FlagTemplate::orderBy('name')->get());
    }

    public function testParameters()
    {
        $tests = \App\Models\LabTest::with(['parameter', 'referenceIntervals'])->orderBy('name')->get();
        $units = \App\Models\Unit::orderBy('name')->get();
        return view('test_parameters', compact('tests', 'units'));
    }

    public function storeTestParameter(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'lab_test_id' => 'required|exists:lab_tests,id',
            'unit' => 'nullable',
            'male_reference' => 'nullable',
            'female_reference' => 'nullable',
            'male_min' => 'nullable|numeric',
            'male_max' => 'nullable|numeric',
            'female_min' => 'nullable|numeric',
            'female_max' => 'nullable|numeric',
            'critical_low' => 'nullable|numeric',
            'critical_high' => 'nullable|numeric',
            'biological_reference' => 'nullable',
            'is_immunoassay' => 'nullable|boolean',
        ]);

        $parameter = \App\Models\TestParameter::updateOrCreate(
            ['lab_test_id' => $validated['lab_test_id']],
            $validated
        );

        \App\Services\ReferenceRangeService::syncDefaultIntervals($parameter);

        return response()->json(['success' => 'Parameters updated successfully!']);
    }

    private function buildReportItems(\Illuminate\Http\Request $request, \App\Models\Patient $patient): array
    {
        $names = $request->input('test_name', []);
        $ids = collect($request->input('lab_test_id', []))->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v);

        // Prefer the exact parameter id (names repeat across panels); fall back to the name.
        $byId = \App\Models\LabTest::with(['parameter', 'referenceIntervals'])->whereIn('id', $ids->unique())->get()->keyBy('id');
        $cleanNames = collect($names)->map(fn ($n) => trim((string) $n))->filter()->unique()->values();
        $byName = \App\Models\LabTest::with(['parameter', 'referenceIntervals'])->whereIn('name', $cleanNames)->get()->keyBy('name');

        $templateIds = collect($request->input('report_template_id', []))->filter(fn ($v) => is_numeric($v))->unique();
        $formulas = \App\Models\ReportTemplateItem::whereIn('report_template_id', $templateIds)
            ->whereNotNull('formula')->where('formula', '!=', '')->get()
            ->keyBy(fn ($i) => $i->report_template_id . ':' . $i->lab_test_id);
        $codes = \App\Models\ReportTemplateItem::whereIn('report_template_id', $templateIds)
            ->whereNotNull('code')->get()
            ->keyBy(fn ($i) => $i->report_template_id . ':' . $i->lab_test_id);

        $items = [];
        $sort = 0;
        foreach ($names as $key => $name) {
            $name = trim((string) $name);
            // Skip empty rows — results are optional
            if ($name === '') {
                continue;
            }

            $labTestId = $request->input("lab_test_id.$key");
            $labTest = (is_numeric($labTestId) ? $byId->get((int) $labTestId) : null) ?? $byName->get($name);
            $parameter = $labTest?->parameter;
            $reference = \App\Services\ReferenceRangeService::resolve($labTest, $patient);
            $observed = $request->input("observed_value.$key");
            $observed = $observed === null ? null : trim((string) $observed);
            $templateId = $request->input("report_template_id.$key");
            $templateId = is_numeric($templateId) ? (int) $templateId : null;

            $items[] = [
                'lab_test_id' => $labTest?->id,
                'report_template_id' => $templateId,
                'category' => trim((string) $request->input("test_category.$key", '')) ?: 'General',
                'subcategory' => $request->input("test_subcategory.$key") ?: null,
                'panel_name' => $request->input("panel_name.$key") ?: null,
                'name' => $name,
                'observed_value' => $observed === '' ? null : $observed,
                'unit' => $request->input("test_unit.$key") ?? $parameter?->unit ?? '',
                // What the user saw on screen wins; otherwise the patient-specific range.
                'normal_value' => $request->input("normal_value.$key") ?: ($reference['text'] ?? ($request->input("biological_reference.$key") ?? '')),
                'biological_reference' => $request->input("biological_reference.$key") ?: ($reference['text'] ?? ''),
                'flag' => $request->input("test_flag.$key") ?? '',
                'sort_order' => $sort++,
                '_parameter' => $parameter,
                '_reference' => $reference,
                '_code' => $codes->get($templateId . ':' . $labTest?->id)?->code,
                '_formula' => $formulas->get($templateId . ':' . $labTest?->id)?->formula,
            ];
        }

        // Calculated parameters left blank: work them out from the other results of the same panel.
        foreach ($items as &$item) {
            if ($item['observed_value'] === null && $item['_formula']) {
                $values = collect($items)
                    ->filter(fn ($o) => $o['report_template_id'] === $item['report_template_id'] && $o['_code'])
                    ->mapWithKeys(fn ($o) => [strtoupper($o['_code']) => $o['observed_value']])->all();
                try {
                    $value = \App\Services\FormulaService::evaluate($item['_formula'], $values);
                    if ($value !== null) {
                        $item['observed_value'] = (string) round($value, 2);
                    }
                } catch (\InvalidArgumentException) {
                    // A broken formula never blocks saving the report.
                }
            }
        }
        unset($item);

        return array_map(function ($item) use ($patient) {
            $flag = \App\Services\ReferenceRangeService::flag($item['observed_value'], $item['_parameter'], $item['_reference'], $patient->gender);
            if ($flag !== null) {
                $item['flag'] = $flag;
            }
            unset($item['_parameter'], $item['_reference'], $item['_code'], $item['_formula']);
            return $item;
        }, $items);
    }



    private function serializeReportItem(\App\Models\TestReportItem $item): array
    {
        return [
            'category' => $item->category,
            'subcategory' => $item->subcategory,
            'name' => $item->name,
            'observed_value' => $item->observed_value,
            'unit' => $item->unit,
            'normal_value' => $item->normal_value,
            'biological_reference' => $item->biological_reference,
            'flag' => $item->flag,
            'lab_test_id' => $item->lab_test_id,
            'report_template_id' => $item->report_template_id,
            'panel_name' => $item->panel_name,
        ];
    }

    private function verifiedSignatureId(\Illuminate\Http\Request $request): ?int
    {
        if (!$request->filled('report_signature_id')) {
            return null;
        }

        $signature = \App\Models\ReportSignature::findOrFail($request->report_signature_id);
        if (!\Illuminate\Support\Facades\Hash::check((string) $request->signature_pin, $signature->pin_hash)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'signature_pin' => 'Invalid signature PIN.',
            ]);
        }

        return $signature->id;
    }

    private function serializeReportSignature(?\App\Models\ReportSignature $signature): ?array
    {
        if (!$signature) {
            return null;
        }

        return [
            'id' => $signature->id,
            'name' => $signature->name,
            'image_url' => route('report-signatures.image', $signature->id),
            'image_data' => $signature->image_data,
        ];
    }

    private function reportSnapshot(\App\Models\TestReport $report): array
    {
        if (!$report->relationLoaded('items')) {
            $report->load('items');
        }
        if (!$report->relationLoaded('signature')) {
            $report->load('signature');
        }

        return [
            'report' => $report->only([
                'id',
                'patient_id',
                'doctor_name',
                'sample_received_on',
                'report_released_on',
                'barcode',
                'status',
                'notes',
                'report_signature_id',
            ]),
            'signature' => $report->signature ? $report->signature->only(['id', 'name', 'image_data']) : null,
            'results' => $report->items->map(fn ($item) => $this->serializeReportItem($item))->values()->all(),
        ];
    }

    private function auditReport(\App\Models\TestReport $report, string $action, ?array $oldData, ?array $newData): void
    {
        \App\Models\TestReportAudit::create([
            'test_report_id' => $report->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
        ]);
    }


    public function getReferenceIntervals($id)
    {
        return response()->json(\App\Models\ReferenceInterval::where('lab_test_id', $id)->get());
    }

    public function storeReferenceInterval(\Illuminate\Http\Request $request, $id)
    {
        $request->validate([
            'gender' => 'required|string',
            'age_min' => 'nullable|numeric',
            'age_max' => 'nullable|numeric',
            'age_type' => 'nullable|string',
            'reference_text' => 'nullable|string',
            'min_value' => 'nullable|numeric',
            'max_value' => 'nullable|numeric',
        ]);

        $data = $request->only(['gender', 'age_min', 'age_max', 'age_type', 'reference_text', 'min_value', 'max_value']);
        // Database requires age_min to be non-null. Default to 0.
        if (empty($data['age_min'])) {
            $data['age_min'] = 0;
        }

        \App\Models\LabTest::findOrFail($id);

        if ($request->filled('interval_id')) {
            $interval = \App\Models\ReferenceInterval::where('lab_test_id', $id)->findOrFail($request->interval_id);
            $interval->update($data);
        } else {
            \App\Models\ReferenceInterval::create(array_merge($data, ['lab_test_id' => $id]));
        }

        return response()->json(['success' => 'Interval added successfully']);
    }

    public function deleteReferenceInterval($id)
    {
        $interval = \App\Models\ReferenceInterval::findOrFail($id);
        $interval->delete();

        return response()->json(['success' => 'Interval deleted successfully']);
    }


    // ── VITAL SIGNS METHODS ──

    public function vitalSigns()
    {
        $vitalSigns = \App\Models\VitalSign::with('patient')->latest()->get();
        $patients = \App\Models\Patient::orderBy('first_name')->get();
        return view('vital_signs', compact('vitalSigns', 'patients'));
    }

    public function storeVitalSign(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ] + \App\Services\VitalSignService::rules());

        $validated = \App\Services\VitalSignService::prepare($validated);

        \App\Models\VitalSign::create($validated);

        return response()->json(['success' => 'Vital Signs recorded successfully!']);
    }

    public function getVitalSign($id)
    {
        $vitalSign = \App\Models\VitalSign::with('patient')->findOrFail($id);
        return response()->json($vitalSign);
    }

    public function updateVitalSign(\Illuminate\Http\Request $request, $id)
    {
        $vitalSign = \App\Models\VitalSign::findOrFail($id);

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ] + \App\Services\VitalSignService::rules());

        $validated = \App\Services\VitalSignService::prepare($validated);

        $vitalSign->update($validated);

        return response()->json(['success' => 'Vital Signs updated successfully!']);
    }

    public function deleteVitalSign($id)
    {
        $vitalSign = \App\Models\VitalSign::findOrFail($id);
        $vitalSign->delete();

        return response()->json(['success' => 'Vital Signs deleted successfully!']);
    }
    // ==========================================
    // ACCOUNTS & PURCHASE MANAGEMENT
    // ==========================================

    public function productsIndex()
    {
        $products = \App\Models\Product::orderBy('name')->get();
        return view('products', compact('products'));
    }

    public function storeProduct(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'unit' => 'nullable',
            'unit_price' => 'nullable|numeric',
            'stock_quantity' => 'nullable|integer',
        ]);

        if (empty($validated['unit'])) $validated['unit'] = 'pcs';
        if (empty($validated['unit_price'])) $validated['unit_price'] = 0;
        if (empty($validated['stock_quantity'])) $validated['stock_quantity'] = 0;

        $product = \App\Models\Product::create($validated);

        return response()->json(['success' => 'Product added successfully!', 'product' => $product]);
    }

    public function updateProduct(\Illuminate\Http\Request $request, $id)
    {
        $product = \App\Models\Product::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'unit' => 'nullable',
            'unit_price' => 'nullable|numeric',
            'stock_quantity' => 'nullable|integer',
        ]);

        if (empty($validated['unit'])) $validated['unit'] = 'pcs';
        if (empty($validated['unit_price'])) $validated['unit_price'] = 0;
        if (empty($validated['stock_quantity'])) $validated['stock_quantity'] = 0;

        $product->update($validated);

        return response()->json(['success' => 'Product updated successfully!']);
    }

    public function deleteProduct($id)
    {
        $product = \App\Models\Product::findOrFail($id);
        
        // Prevent deletion if linked to purchases
        if (\App\Models\PurchaseItem::where('product_id', $product->id)->exists()) {
            return response()->json(['error' => 'Cannot delete product linked to purchase receipts.'], 409);
        }

        $product->delete();
        return response()->json(['success' => 'Product deleted successfully!']);
    }

    public function purchasesIndex()
    {
        $receipts = \App\Models\PurchaseReceipt::with('items.product')->orderBy('purchase_date', 'desc')->get();
        $products = \App\Models\Product::orderBy('name')->get();
        return view('purchases', compact('receipts', 'products'));
    }

    public function storePurchase(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'vendor_name' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|string', // JSON string of items
        ]);

        $items = json_decode($request->items, true);
        if (!is_array($items) || empty($items)) {
            return response()->json(['error' => 'At least one valid item is required.'], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make(['items' => $items], [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid item format: ' . $validator->errors()->first()], 422);
        }

        $totalAmount = 0;
        foreach ($items as $item) {
            $totalAmount += $item['quantity'] * $item['unit_price'];
        }

        DB::transaction(function () use ($request, $items, $totalAmount) {
            $receipt = \App\Models\PurchaseReceipt::create([
                'receipt_no' => 'PR-' . strtoupper(uniqid()),
                'vendor_name' => $request->vendor_name,
                'purchase_date' => $request->purchase_date,
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
            ]);

            foreach ($items as $item) {
                \App\Models\PurchaseItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);

                // Update product stock automatically
                \App\Models\Product::whereKey($item['product_id'])->increment('stock_quantity', $item['quantity']);
            }
        });

        return response()->json(['success' => 'Purchase Receipt created successfully!']);
    }

    public function apiProducts()
    {
        return response()->json(\App\Models\Product::orderBy('name')->get());
    }

    // ==========================================
    // REPORT TEMPLATES MANAGEMENT
    // ==========================================

    public function templatesIndex()
    {
        $templates = \App\Models\ReportTemplate::with('items')->orderBy('name')->get();
        $labTests = \App\Models\LabTest::with(['parameter', 'referenceIntervals'])->orderBy('name')->get();
        $units = \App\Models\Unit::orderBy('name')->get();
        $referenceTemplates = \App\Models\ReferenceTemplate::orderBy('name')->get();
        return view('templates', compact('templates', 'labTests', 'units', 'referenceTemplates'));
    }

    public function storeTemplate(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:report_templates,name',
            'description' => 'nullable|string',
            'test_name' => 'required|array',
            'test_name.*' => 'required|string',
        ]);

        $template = \App\Models\ReportTemplate::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $items = [];
        foreach ($request->test_name as $i => $name) {
            $items[] = [
                'lab_test_id' => $request->lab_test_id[$i] ?? null,
                'category' => $request->test_category[$i] ?? 'General',
                'subcategory' => $request->test_subcategory[$i] ?? null,
                'name' => $name,
                'unit' => $request->test_unit[$i] ?? null,
                'normal_value' => $request->normal_value[$i] ?? null,
                'biological_reference' => $request->biological_reference[$i] ?? null,
                'sort_order' => $i,
            ];
        }

        $template->items()->createMany($items);

        return response()->json(['success' => 'Template created successfully!']);
    }

    public function getTemplate($id)
    {
        $template = \App\Models\ReportTemplate::with('items')->findOrFail($id);
        return response()->json($template);
    }

    public function updateTemplate(\Illuminate\Http\Request $request, $id)
    {
        $template = \App\Models\ReportTemplate::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:report_templates,name,' . $id,
            'description' => 'nullable|string',
            'test_name' => 'required|array',
            'test_name.*' => 'required|string',
        ]);

        $template->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        $template->items()->delete();

        $items = [];
        foreach ($request->test_name as $i => $name) {
            $items[] = [
                'lab_test_id' => $request->lab_test_id[$i] ?? null,
                'category' => $request->test_category[$i] ?? 'General',
                'subcategory' => $request->test_subcategory[$i] ?? null,
                'name' => $name,
                'unit' => $request->test_unit[$i] ?? null,
                'normal_value' => $request->normal_value[$i] ?? null,
                'biological_reference' => $request->biological_reference[$i] ?? null,
                'sort_order' => $i,
            ];
        }

        $template->items()->createMany($items);

        return response()->json(['success' => 'Template updated successfully!']);
    }

    public function deleteTemplate($id)
    {
        $template = \App\Models\ReportTemplate::findOrFail($id);
        $template->delete();

        return response()->json(['success' => 'Template deleted successfully!']);
    }
}


