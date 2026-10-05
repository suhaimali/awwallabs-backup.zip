<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TestReport;

/**
 * Pages opened by scanning the QR code on a printed report or invoice.
 * Access is by an unguessable 40-character token; no login is needed.
 */
class PublicRecordController extends Controller
{
    public function report(string $token)
    {
        $report = TestReport::with(['patient', 'items', 'signature'])
            ->where('public_token', $token)
            ->firstOrFail();

        // Keep entry order (sort_order) within each department.
        $grouped = $report->items->sortBy('sort_order')->groupBy(fn ($item) => strtoupper($item->category ?: 'GENERAL'));

        return response()
            ->view('public.report', compact('report', 'grouped'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }

    public function patient(string $token)
    {
        $patient = Patient::where('public_token', $token)->firstOrFail();

        $appointments = $patient->appointments()->orderByDesc('appointment_date')->orderByDesc('id')->get();
        $payments = $patient->payments()->orderByDesc('bill_date')->orderByDesc('id')->get();
        $reports = $patient->testReports()->latest('report_released_on')->get();
        $reports->each->ensurePublicToken();

        return response()
            ->view('public.patient', compact('patient', 'appointments', 'payments', 'reports'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
