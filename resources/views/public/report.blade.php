@extends('public.layout')
@section('title', 'Lab Report #' . $report->id)

@php
    $p = $report->patient;
    $flagClass = function ($flag) {
        return match (trim((string) $flag)) {
            '↑', 'H', 'High' => 'flag-hi',
            '↓', 'L', 'Low' => 'flag-lo',
            'C' => 'flag-c',
            default => '',
        };
    };
@endphp

@section('content')
    <div class="card">
        <h2>Patient</h2>
        <div class="grid">
            <div class="kv"><span>Name</span><span>{{ strtoupper($p->full_name) }}</span></div>
            <div class="kv"><span>Age / Sex</span><span>{{ $p->age }} {{ $p->age_type ?: 'Years' }} / {{ $p->gender }}</span></div>
            <div class="kv"><span>Patient ID</span><span>{{ $p->patient_id }}</span></div>
            <div class="kv"><span>Referred by</span><span>{{ $report->doctor_name ?: 'Self' }}</span></div>
            <div class="kv"><span>Sample received</span><span>{{ optional($report->sample_received_on)->format('d M Y, h:i A') }}</span></div>
            <div class="kv"><span>Report released</span><span>{{ optional($report->report_released_on)->format('d M Y, h:i A') }}</span></div>
            <div class="kv"><span>Report no.</span><span>#{{ $report->id }}@if($report->barcode) · {{ $report->barcode }}@endif</span></div>
            <div class="kv"><span>Status</span><span>
                <span class="pill {{ $report->status === 'Completed' ? 'pill-ok' : 'pill-warn' }}">{{ $report->status }}</span>
            </span></div>
        </div>
    </div>

    <div class="card">
        <h2>Results</h2>
        @if($report->items->isEmpty())
            <p class="muted">No results have been entered for this report yet.</p>
        @else
            <table class="stack">
                <thead>
                    <tr><th>Test</th><th>Result</th><th>Unit</th><th>Reference range</th></tr>
                </thead>
                <tbody>
                    @foreach($grouped as $category => $items)
                        <tr class="cat"><td colspan="4">{{ $category }}</td></tr>
                        @php $lastSub = null; @endphp
                        @foreach($items as $item)
                            @if($item->subcategory && $item->subcategory !== $lastSub)
                                <tr class="sub"><td colspan="4">{{ $item->subcategory }}</td></tr>
                                @php $lastSub = $item->subcategory; @endphp
                            @endif
                            @php
                                $cls = $flagClass($item->flag);
                                $ref = $item->normal_value !== null && $item->normal_value !== '' ? $item->normal_value : $item->biological_reference;
                            @endphp
                            <tr>
                                <td class="t-name">{{ $item->name }}</td>
                                <td class="t-val">
                                    @if($item->observed_value === null || $item->observed_value === '')
                                        <span class="muted">Pending</span>
                                    @else
                                        <span class="{{ $cls ? 'val-abn' : '' }}">{{ $item->observed_value }}</span>
                                        <span class="t-unit-inline muted">{{ $item->unit }}</span>
                                    @endif
                                    @if($item->flag)
                                        <span class="flag {{ $cls }}">{{ $item->flag }}</span>
                                    @endif
                                </td>
                                <td class="t-unit">{{ $item->unit }}</td>
                                <td class="t-ref ref">{{ $ref }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if($report->notes)
        <div class="card">
            <h2>Notes / Interpretation</h2>
            <div class="notes">{{ $report->notes }}</div>
        </div>
    @endif

    <div class="card">
        <div class="grid">
            <div class="kv"><span>Authorised by</span><span>{{ $report->signature->name ?? 'Lab In-Charge' }}</span></div>
            <div class="kv"><span>Last updated</span><span>{{ $report->updated_at->format('d M Y, h:i A') }}</span></div>
        </div>
        <div class="actions">
            <button type="button" class="btn" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                Print / Save PDF
            </button>
            @if($p->public_token)
                <a class="btn btn-outline" href="{{ route('public.patient', $p->public_token) }}">All visits &amp; bills</a>
            @endif
        </div>
    </div>
@endsection
