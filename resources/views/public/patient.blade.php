@extends('public.layout')
@section('title', 'Patient ' . $patient->patient_id)

@section('content')
    <div class="card">
        <h2>Patient</h2>
        <div class="grid">
            <div class="kv"><span>Name</span><span>{{ strtoupper($patient->full_name) }}</span></div>
            <div class="kv"><span>Patient ID</span><span>{{ $patient->patient_id }}</span></div>
            <div class="kv"><span>Age / Sex</span><span>{{ $patient->age }} {{ $patient->age_type ?: 'Years' }} / {{ $patient->gender }}</span></div>
            <div class="kv"><span>Referred by</span><span>{{ $patient->reference_dr ?: 'Self' }}</span></div>
        </div>
    </div>

    <div class="card">
        <h2>Reports</h2>
        @forelse($reports as $report)
            <div class="kv" style="justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f1f5f9;">
                <span style="min-width:0;">
                    <strong>Report #{{ $report->id }}</strong>
                    <span class="muted"> · {{ optional($report->report_released_on)->format('d M Y') }}</span>
                </span>
                <a class="btn" style="padding:6px 12px;font-size:13px;" href="{{ route('public.report', $report->public_token) }}">View</a>
            </div>
        @empty
            <p class="muted" style="margin:0;">No reports have been released yet. Please check back later.</p>
        @endforelse
    </div>

    <div class="card">
        <h2>Tests booked</h2>
        @if($appointments->isEmpty())
            <p class="muted" style="margin:0;">No tests booked.</p>
        @else
            <table class="stack">
                <thead><tr><th>Test</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($appointments as $a)
                        <tr>
                            <td class="t-name">{{ $a->test_name }}</td>
                            <td class="t-ref muted">{{ \Carbon\Carbon::parse($a->appointment_date)->format('d M Y') }}</td>
                            <td class="t-val">
                                <span class="pill {{ $a->status === 'Completed' ? 'pill-ok' : ($a->status === 'Cancelled' ? 'pill-muted' : 'pill-warn') }}">
                                    {{ $a->status === 'Pending' ? 'Result awaited' : $a->status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Bills</h2>
        @if($payments->isEmpty())
            <p class="muted" style="margin:0;">No bills.</p>
        @else
            <table class="stack">
                <thead><tr><th>Date</th><th>Net amount</th><th>Paid</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($payments as $bill)
                        <tr>
                            <td class="t-name">{{ \Carbon\Carbon::parse($bill->bill_date)->format('d M Y') }}</td>
                            <td class="t-val">₹{{ number_format($bill->net_amount, 2) }}</td>
                            <td class="t-ref muted">Paid ₹{{ number_format($bill->advance_paid, 2) }} · Balance ₹{{ number_format($bill->balance_due, 2) }}</td>
                            <td class="t-val">
                                <span class="pill {{ $bill->payment_status === 'Paid' ? 'pill-ok' : 'pill-warn' }}">{{ $bill->payment_status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div class="actions no-print">
            <button type="button" class="btn btn-outline" onclick="window.print()">Print</button>
        </div>
    </div>
@endsection
