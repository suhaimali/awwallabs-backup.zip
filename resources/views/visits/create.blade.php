@extends('layouts.app')
@section('title', ' | New Visit')
@section('page-title', 'New Visit')

@push('styles')
<style>
    .visit-wrap { max-width: 1040px; margin: 0 auto; }

    /* Stepper */
    .vstepper { display: flex; gap: 8px; margin-bottom: 18px; }
    .vstep { flex: 1; display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: var(--white); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-muted); font-weight: 600; font-size: 13px; min-width: 0; }
    .vstep .num { width: 26px; height: 26px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
    .vstep .lbl { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .vstep.active { border-color: var(--primary); color: var(--primary); }
    .vstep.active .num { background: var(--primary); color: #fff; }
    .vstep.done { color: #059669; border-color: #a7f3d0; }
    .vstep.done .num { background: #d1fae5; color: #059669; }
    @media (max-width: 640px) {
        .vstepper { gap: 6px; }
        .vstep { padding: 8px; justify-content: center; }
        .vstep .lbl { display: none; }
        .vstep.active .lbl { display: inline; }
        .vstep.active { flex: 2.4; }
    }

    .vpanel { display: none; }
    .vpanel.active { display: block; }
    .vsection-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
    .vsection-sub { font-size: 12.5px; color: var(--text-muted); margin-bottom: 16px; }

    .seg { display: inline-flex; background: #f1f5f9; border-radius: 10px; padding: 4px; margin-bottom: 16px; width: 100%; max-width: 420px; }
    .seg button { flex: 1; border: 0; background: transparent; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 13px; color: var(--text-muted); }
    .seg button.on { background: var(--white); color: var(--primary); box-shadow: 0 1px 4px rgba(0,0,0,0.08); }

    .vform label { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
    .vform .form-control, .vform .form-select { border: 1.5px solid #cbd5e1; border-radius: 9px; font-size: 14px; padding: 9px 12px; }
    .vform .form-control:focus, .vform .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(26,86,219,.1); }
    .vform .is-invalid { border-color: #dc2626 !important; }

    .search-box { position: relative; }
    .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
    .search-box input { padding-left: 36px !important; }

    .result-list { border: 1.5px solid var(--border-color); border-radius: 10px; margin-top: 8px; max-height: 300px; overflow-y: auto; background: var(--white); }
    .result-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; cursor: pointer; }
    .result-item:last-child { border-bottom: 0; }
    .result-item:hover, .result-item:focus { background: var(--primary-light); outline: none; }
    .result-item .meta { font-size: 12px; color: var(--text-muted); }
    .result-empty { padding: 14px; color: var(--text-muted); font-size: 13px; text-align: center; }

    .chosen-patient { display: flex; align-items: center; gap: 14px; padding: 14px; border: 1.5px solid #a7f3d0; background: #f0fdf4; border-radius: 12px; }
    .chosen-patient .avatar { width: 44px; height: 44px; border-radius: 50%; background: #d1fae5; color: #059669; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }

    .tests-table { width: 100%; border-collapse: collapse; }
    .tests-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); font-weight: 700; padding: 8px; border-bottom: 1.5px solid var(--border-color); }
    .tests-table td { padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .tests-table input { max-width: 120px; }
    .tests-empty { padding: 22px; text-align: center; color: var(--text-muted); font-size: 13px; border: 1.5px dashed var(--border-color); border-radius: 10px; }
    @media (max-width: 640px) {
        .tests-table thead { display: none; }
        .tests-table tr { display: grid; grid-template-columns: 1fr 1fr auto; gap: 6px 10px; padding: 10px 0; border-bottom: 1px solid var(--border-color); }
        .tests-table td { border: 0; padding: 0; }
        .tests-table td.tname { grid-column: 1 / -1; font-weight: 600; }
        .tests-table td[data-label]::before { content: attr(data-label); display: block; font-size: 10.5px; color: var(--text-muted); text-transform: uppercase; font-weight: 700; }
        .tests-table input { max-width: none; }
        .tests-table td.tdel { align-self: end; }
    }

    .totals { background: #f8fafc; border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; }
    .totals .row-line { display: flex; justify-content: space-between; font-size: 14px; padding: 3px 0; }
    .totals .row-line.big { font-size: 17px; font-weight: 800; border-top: 1px dashed #cbd5e1; margin-top: 6px; padding-top: 8px; }
    .totals .due { color: #ea580c; }
    .totals .paid { color: #059669; }

    .vfooter { display: flex; justify-content: space-between; gap: 10px; margin-top: 18px; }
    @media (max-width: 640px) {
        .vfooter { position: sticky; bottom: 0; background: var(--bg-main); padding: 12px 0; margin: 12px 0 0; z-index: 5; }
        .vfooter button { flex: 1; justify-content: center; }
    }

    .review-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 768px) { .review-grid { grid-template-columns: 1fr; } }
    .review-box { border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; background: var(--white); }
    .review-box h6 { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); font-weight: 700; margin-bottom: 10px; display: flex; justify-content: space-between; }
    .review-box h6 a { text-transform: none; letter-spacing: 0; font-weight: 600; }
    .review-line { display: flex; justify-content: space-between; gap: 10px; font-size: 13.5px; padding: 2px 0; }
    .review-line span:first-child { color: var(--text-muted); }

    .done-card { text-align: center; padding: 30px 20px; }
    .done-icon { width: 64px; height: 64px; border-radius: 50%; background: #d1fae5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 14px; }
    .done-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 18px; }
    @media (max-width: 640px) { .done-actions a, .done-actions button { width: 100%; justify-content: center; } }
    .flow-hint { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; font-size: 12px; color: var(--text-muted); margin-top: 16px; }
    .flow-hint span { background: #f1f5f9; padding: 4px 10px; border-radius: 20px; }
    .flow-hint span.on { background: var(--primary-light); color: var(--primary); font-weight: 600; }
</style>
@endpush

@section('content')
<div class="visit-wrap">
    <div class="page-header-aw">
        <div class="page-title-aw">
            <div class="title-icon"><i class="fa fa-route"></i></div>
            <div>
                <div>New Visit</div>
                <div style="font-size:13px;font-weight:400;color:var(--text-muted);margin-top:2px;">Register → bill → vitals → results → report, in one flow</div>
            </div>
        </div>
    </div>

    <div class="vstepper" id="vstepper">
        <div class="vstep active" data-step="1"><span class="num">1</span><span class="lbl">Patient</span></div>
        <div class="vstep" data-step="2"><span class="num">2</span><span class="lbl">Tests &amp; Billing</span></div>
        <div class="vstep" data-step="3"><span class="num">3</span><span class="lbl">Vitals</span></div>
        <div class="vstep" data-step="4"><span class="num">4</span><span class="lbl">Confirm</span></div>
    </div>

    <form id="visit-form" class="aw-card vform" novalidate autocomplete="off">
        <div class="aw-card-body">

            {{-- STEP 1: PATIENT --}}
            <section class="vpanel active" data-step="1">
                <div class="vsection-title">Who is the patient?</div>
                <div class="vsection-sub">Find a returning patient or register a new one.</div>

                <div class="seg" role="tablist">
                    <button type="button" class="on" data-mode="existing" id="mode-existing"><i class="fa fa-magnifying-glass me-1"></i> Returning</button>
                    <button type="button" data-mode="new" id="mode-new"><i class="fa fa-user-plus me-1"></i> New patient</button>
                </div>

                <div id="existing-block">
                    <div id="patient-picker">
                        <div class="search-box">
                            <i class="fa fa-search"></i>
                            <input type="search" class="form-control" id="patient-search" placeholder="Search by name, phone or patient ID" aria-label="Search patients">
                        </div>
                        <div class="result-list" id="patient-results" style="display:none;"></div>
                    </div>
                    <div id="chosen-patient" class="chosen-patient mt-2" style="display:none;">
                        <div class="avatar" id="cp-avatar"></div>
                        <div class="flex-grow-1 min-w-0">
                            <div style="font-weight:700;" id="cp-name"></div>
                            <div class="meta" style="font-size:12.5px;color:var(--text-muted);" id="cp-meta"></div>
                        </div>
                        <button type="button" class="btn-aw-outline btn-aw-sm" id="cp-change">Change</button>
                    </div>
                </div>

                <div id="new-block" style="display:none;">
                    <div class="row g-3">
                        <div class="col-md-6"><label for="p-first">First name *</label><input class="form-control" id="p-first" name="first_name" maxlength="100"></div>
                        <div class="col-md-6"><label for="p-last">Last name *</label><input class="form-control" id="p-last" name="last_name" maxlength="100"></div>
                        <div class="col-6 col-md-3"><label for="p-gender">Gender *</label>
                            <select class="form-select" id="p-gender" name="gender"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select>
                        </div>
                        <div class="col-6 col-md-3"><label for="p-age">Age *</label>
                            <div class="input-group flex-nowrap">
                                <input type="number" min="0" max="150" class="form-control" id="p-age" name="age" inputmode="numeric">
                                <select class="form-select" id="p-age-type" name="age_type" style="max-width:96px;" aria-label="Age unit"><option>Years</option><option>Months</option><option>Days</option></select>
                            </div>
                        </div>
                        <div class="col-md-6"><label for="p-phone">Phone</label><input type="tel" class="form-control" id="p-phone" name="phone" maxlength="20" inputmode="tel"></div>
                        <div class="col-md-6"><label for="p-email">Email</label><input type="email" class="form-control" id="p-email" name="email" maxlength="255"></div>
                        <div class="col-md-6"><label for="p-address">Address</label><input class="form-control" id="p-address" name="address" maxlength="500"></div>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label for="v-doctor">Referring doctor</label>
                        <input class="form-control" id="v-doctor" list="doctor-list" maxlength="150" placeholder="Self">
                        <datalist id="doctor-list">
                            <option value="Self">
                            @foreach($doctors as $d)
                                <option value="{{ $d->name }}">{{ $d->qualification }}</option>
                            @endforeach
                        </datalist>
                    </div>
                </div>
            </section>

            {{-- STEP 2: TESTS & BILLING --}}
            <section class="vpanel" data-step="2">
                <div class="vsection-title">Tests &amp; billing</div>
                <div class="vsection-sub">Add tests one by one or load a package.</div>

                <div class="row g-2 mb-2">
                    <div class="col-md-8">
                        <div class="search-box">
                            <i class="fa fa-flask"></i>
                            <input type="search" class="form-control" id="test-search" placeholder="Search {{ number_format($tests->count()) }} tests…" aria-label="Search tests">
                        </div>
                        <div class="result-list" id="test-results" style="display:none;"></div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" id="package-select" aria-label="Add a package">
                            <option value="">+ Add a package…</option>
                            @foreach($packages as $pkg)
                                <option value="{{ $pkg->id }}">{{ $pkg->name }} ({{ $pkg->items->count() }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="tests-empty" class="tests-empty mt-3"><i class="fa fa-vial me-1"></i> No tests added yet.</div>
                <table class="tests-table mt-2" id="tests-table" style="display:none;">
                    <thead><tr><th>Test</th><th style="width:130px;">Price (₹)</th><th style="width:130px;">Discount (₹)</th><th style="width:44px;"></th></tr></thead>
                    <tbody></tbody>
                </table>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="row g-3">
                            <div class="col-6">
                                <label for="v-method">Payment mode *</label>
                                <select class="form-select" id="v-method">
                                    @foreach($paymentMethods as $m)
                                        <option value="{{ $m }}">{{ $m }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="v-paid">Amount received (₹)</label>
                                <div class="input-group">
                                    <input type="number" min="0" step="0.01" class="form-control" id="v-paid" value="0" inputmode="decimal">
                                    <button type="button" class="btn btn-outline-success" id="btn-pay-full" title="Paid in full">Full</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="totals">
                            <div class="row-line"><span>Subtotal</span><span>₹<span id="t-sub">0.00</span></span></div>
                            <div class="row-line"><span>Discount</span><span>− ₹<span id="t-disc">0.00</span></span></div>
                            <div class="row-line"><span>Net payable</span><strong>₹<span id="t-net">0.00</span></strong></div>
                            <div class="row-line"><span>Received</span><span>₹<span id="t-paid">0.00</span></span></div>
                            <div class="row-line big"><span>Balance</span><span id="t-bal-wrap" class="paid">₹<span id="t-bal">0.00</span></span></div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- STEP 3: VITALS --}}
            <section class="vpanel" data-step="3">
                <div class="vsection-title">Vital signs <span class="badge-aw" style="font-size:11px;">optional</span></div>
                <div class="vsection-sub">Leave blank to skip. BMI is calculated automatically.</div>
                <div class="row g-3">
                    <div class="col-6 col-md-3"><label for="vt-temp">Temperature</label>
                        <div class="input-group flex-nowrap"><input type="number" step="0.1" class="form-control" id="vt-temp" inputmode="decimal">
                            <select class="form-select" id="vt-temp-unit" style="max-width:70px;" aria-label="Temperature unit"><option value="F">°F</option><option value="C">°C</option></select></div>
                    </div>
                    <div class="col-6 col-md-3"><label for="vt-pulse">Pulse (bpm)</label><input type="number" class="form-control" id="vt-pulse" inputmode="numeric"></div>
                    <div class="col-6 col-md-3"><label for="vt-bp">BP (mmHg)</label><input class="form-control" id="vt-bp" placeholder="120/80"></div>
                    <div class="col-6 col-md-3"><label for="vt-spo2">SpO₂ (%)</label><input type="number" class="form-control" id="vt-spo2" inputmode="numeric"></div>
                    <div class="col-6 col-md-3"><label for="vt-rr">Resp. rate</label><input type="number" class="form-control" id="vt-rr" inputmode="numeric"></div>
                    <div class="col-6 col-md-3"><label for="vt-weight">Weight (kg)</label><input type="number" step="0.1" class="form-control" id="vt-weight" inputmode="decimal"></div>
                    <div class="col-6 col-md-3"><label for="vt-height">Height (cm)</label><input type="number" step="0.1" class="form-control" id="vt-height" inputmode="decimal"></div>
                    <div class="col-6 col-md-3"><label>BMI</label><div class="form-control" id="vt-bmi" style="background:#f8fafc;">—</div></div>
                    <div class="col-12"><label for="vt-notes">Notes</label><input class="form-control" id="vt-notes" maxlength="1000"></div>
                </div>
            </section>

            {{-- STEP 4: CONFIRM --}}
            <section class="vpanel" data-step="4">
                <div class="vsection-title">Confirm visit</div>
                <div class="vsection-sub">Check the details, then register. You'll go straight to result entry next.</div>
                <div class="review-grid">
                    <div class="review-box"><h6>Patient <a href="#" data-goto="1">Edit</a></h6><div id="rv-patient"></div></div>
                    <div class="review-box"><h6>Billing <a href="#" data-goto="2">Edit</a></h6><div id="rv-bill"></div></div>
                    <div class="review-box" style="grid-column:1/-1;"><h6>Tests <a href="#" data-goto="2">Edit</a></h6><div id="rv-tests"></div></div>
                    <div class="review-box" style="grid-column:1/-1;"><h6>Vitals <a href="#" data-goto="3">Edit</a></h6><div id="rv-vitals"></div></div>
                </div>
            </section>

            {{-- DONE --}}
            <section class="vpanel" data-step="5">
                <div class="done-card">
                    <div class="done-icon"><i class="fa fa-check"></i></div>
                    <h4 style="font-weight:800;" id="done-title">Visit registered</h4>
                    <div style="color:var(--text-muted);" id="done-sub"></div>
                    <div class="done-actions">
                        <a href="#" class="btn-aw-primary" id="done-results"><i class="fa fa-vial-circle-check"></i> Enter results now</a>
                        <a href="#" class="btn-aw-outline" id="done-card" target="_blank" rel="noopener"><i class="fa fa-qrcode"></i> Patient card</a>
                        <a href="{{ route('visits.create') }}" class="btn-aw-outline"><i class="fa fa-plus"></i> Another visit</a>
                    </div>
                    <div class="flow-hint">
                        <span>✓ Registered</span><span>✓ Billed</span><span class="on">Results</span><span>Report + QR</span>
                    </div>
                </div>
            </section>

            <div class="vfooter" id="vfooter">
                <button type="button" class="btn-aw-outline" id="btn-back" style="visibility:hidden;"><i class="fa fa-arrow-left"></i> Back</button>
                <button type="button" class="btn-aw-primary" id="btn-next">Next <i class="fa fa-arrow-right"></i></button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const CATALOG = @json($tests->map(fn ($t) => ['n' => $t->name, 'p' => (float) $t->price])->values());
    const PACKAGES = @json($packages->mapWithKeys(fn ($p) => [$p->id => $p->items->pluck('name')->values()]));
    const PRESELECTED = @json($preselected);
    const priceByName = new Map(CATALOG.map(t => [t.n.toLowerCase(), t.p]));

    const state = { step: 1, mode: 'existing', patient: null, tests: [] };
    const money = v => (Math.round((parseFloat(v) || 0) * 100) / 100).toFixed(2);
    const esc = s => $('<div>').text(s == null ? '' : String(s)).html();

    // ── Steps ────────────────────────────────────────────
    function go(step) {
        state.step = step;
        $('.vpanel').removeClass('active').filter(`[data-step="${step}"]`).addClass('active');
        $('.vstep').each(function () {
            const n = +$(this).data('step');
            $(this).toggleClass('active', n === step).toggleClass('done', n < step || step === 5);
        });
        $('#btn-back').css('visibility', step > 1 && step < 5 ? 'visible' : 'hidden');
        $('#vfooter').toggle(step < 5);
        $('#btn-next').html(step === 4 ? '<i class="fa fa-check"></i> Register visit' : (step === 3 ? 'Review <i class="fa fa-arrow-right"></i>' : 'Next <i class="fa fa-arrow-right"></i>'));
        if (step === 4) renderReview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function fail(msg, $field) {
        Swal.fire({ icon: 'warning', title: 'Check this step', text: msg, confirmButtonColor: '#1a56db' });
        if ($field) $field.addClass('is-invalid').trigger('focus');
        return false;
    }

    function validateStep(step) {
        $('.is-invalid').removeClass('is-invalid');
        if (step === 1) {
            if (state.mode === 'existing') {
                return state.patient ? true : fail('Search and pick a patient, or switch to "New patient".', $('#patient-search'));
            }
            if (!$('#p-first').val().trim()) return fail('First name is required.', $('#p-first'));
            if (!$('#p-last').val().trim()) return fail('Last name is required.', $('#p-last'));
            const age = $('#p-age').val();
            if (age === '' || +age < 0 || +age > 150) return fail('Enter a valid age (0–150).', $('#p-age'));
            const email = $('#p-email').val().trim();
            if (email && !/^\S+@\S+\.\S+$/.test(email)) return fail('Email address looks invalid.', $('#p-email'));
            return true;
        }
        if (step === 2) {
            if (!state.tests.length) return fail('Add at least one test.', $('#test-search'));
            const bad = state.tests.findIndex(t => t.discount > t.price);
            if (bad >= 0) return fail(`Discount on "${state.tests[bad].name}" is more than its price.`);
            return true;
        }
        if (step === 3) {
            const bp = $('#vt-bp').val().trim();
            if (bp && !/^\d{2,3}\s*\/\s*\d{2,3}$/.test(bp)) return fail('Blood pressure should look like 120/80.', $('#vt-bp'));
            return true;
        }
        return true;
    }

    $('#btn-next').on('click', function () {
        if (state.step < 4) {
            if (validateStep(state.step)) go(state.step + 1);
        } else {
            submit();
        }
    });
    $('#btn-back').on('click', () => go(Math.max(1, state.step - 1)));
    $(document).on('click', '[data-goto]', function (e) { e.preventDefault(); go(+$(this).data('goto')); });

    // ── Step 1: patient ──────────────────────────────────
    $('.seg button').on('click', function () {
        state.mode = $(this).data('mode');
        $('.seg button').removeClass('on');
        $(this).addClass('on');
        $('#existing-block').toggle(state.mode === 'existing');
        $('#new-block').toggle(state.mode === 'new');
        if (state.mode === 'new') $('#p-first').trigger('focus');
    });

    let searchTimer = null, searchXhr = null;
    $('#patient-search').on('input', function () {
        const q = $(this).val().trim();
        clearTimeout(searchTimer);
        if (q.length < 2) { $('#patient-results').hide().empty(); return; }
        searchTimer = setTimeout(() => {
            if (searchXhr) searchXhr.abort();
            searchXhr = $.getJSON(@json(route('visits.patient-search')), { q }).done(rows => {
                const $list = $('#patient-results').empty().show();
                if (!rows.length) {
                    $list.append($('<div class="result-empty">').html('No match. <a href="#" id="switch-new">Register as new patient</a>'));
                    return;
                }
                rows.forEach(p => {
                    const $item = $('<div class="result-item" tabindex="0">').data('p', p);
                    const $left = $('<div class="min-w-0">');
                    $left.append($('<div style="font-weight:600;">').text(p.name));
                    $left.append($('<div class="meta">').text(`${p.patient_id} · ${p.gender} · ${p.age} ${p.age_type || ''}${p.phone ? ' · ' + p.phone : ''}`));
                    const $right = $('<div class="text-end meta">');
                    if (p.pending_tests) $right.append($('<span class="badge-aw badge-orange">').text(`${p.pending_tests} pending`));
                    $item.append($left, $right);
                    $list.append($item);
                });
            });
        }, 250);
    });
    $(document).on('click', '#switch-new', function (e) { e.preventDefault(); $('#mode-new').trigger('click'); });
    $(document).on('click keydown', '.result-item', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter') return;
        if ($(this).closest('#patient-results').length) choosePatient($(this).data('p'));
    });

    function choosePatient(p) {
        state.patient = p;
        $('#patient-picker').hide();
        $('#cp-avatar').text(p.name.split(' ').map(s => s.charAt(0)).join('').slice(0, 2).toUpperCase());
        $('#cp-name').text(p.name);
        let meta = `${p.patient_id} · ${p.gender} · ${p.age} ${p.age_type || ''}`;
        if (p.phone) meta += ` · ${p.phone}`;
        if (p.balance > 0) meta += ` · Previous balance ₹${money(p.balance)}`;
        $('#cp-meta').text(meta);
        $('#chosen-patient').show();
        if (p.reference_dr && !$('#v-doctor').val()) $('#v-doctor').val(p.reference_dr);
        if (p.payment_method) $('#v-method').val(p.payment_method);
    }
    $('#cp-change').on('click', function () {
        state.patient = null;
        $('#chosen-patient').hide();
        $('#patient-picker').show();
        $('#patient-search').val('').trigger('focus');
        $('#patient-results').hide().empty();
    });

    // ── Step 2: tests ────────────────────────────────────
    $('#test-search').on('input', function () {
        const q = $(this).val().trim().toLowerCase();
        const $list = $('#test-results').empty();
        if (!q) { $list.hide(); return; }
        const starts = [], contains = [];
        for (const t of CATALOG) {
            const n = t.n.toLowerCase();
            if (n.startsWith(q)) starts.push(t); else if (n.includes(q)) contains.push(t);
            if (starts.length >= 40) break;
        }
        const matches = starts.concat(contains).slice(0, 40);
        $list.show();
        if (!matches.length) {
            const $custom = $('<div class="result-item" tabindex="0">').data('t', { n: $(this).val().trim(), p: 0 });
            $custom.append($('<div>').html('<i class="fa fa-plus me-1"></i> Add custom test “<b></b>”').find('b').text($(this).val().trim()).end());
            $list.append($custom);
            return;
        }
        matches.forEach(t => {
            const $item = $('<div class="result-item" tabindex="0">').data('t', t);
            $item.append($('<span>').text(t.n), $('<span class="meta">').text('₹' + money(t.p)));
            $list.append($item);
        });
    });
    $(document).on('click keydown', '#test-results .result-item', function (e) {
        if (e.type === 'keydown' && e.key !== 'Enter') return;
        const t = $(this).data('t');
        addTest(t.n, t.p);
        $('#test-search').val('').trigger('focus');
        $('#test-results').hide().empty();
    });
    $('#test-search').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#test-results .result-item').first().trigger('click'); }
    });

    $('#package-select').on('change', function () {
        const names = PACKAGES[$(this).val()] || [];
        names.forEach(n => addTest(n, priceByName.get(n.toLowerCase()) ?? 0));
        $(this).val('');
    });

    function addTest(name, price) {
        name = (name || '').trim();
        if (!name) return;
        if (state.tests.some(t => t.name.toLowerCase() === name.toLowerCase())) {
            showToast(`"${name}" is already added`, 'info');
            return;
        }
        state.tests.push({ name, price: parseFloat(price) || 0, discount: 0 });
        renderTests();
    }

    function renderTests() {
        const $body = $('#tests-table tbody').empty();
        state.tests.forEach((t, i) => {
            const $tr = $('<tr>').attr('data-i', i);
            $tr.append($('<td class="tname">').text(t.name));
            $tr.append($('<td data-label="Price">').append($('<input type="number" min="0" step="0.01" class="form-control form-control-sm t-price" inputmode="decimal">').val(money(t.price))));
            $tr.append($('<td data-label="Discount">').append($('<input type="number" min="0" step="0.01" class="form-control form-control-sm t-disc" inputmode="decimal">').val(money(t.discount))));
            $tr.append($('<td class="tdel">').append('<button type="button" class="btn btn-sm btn-outline-danger t-del" title="Remove" aria-label="Remove test"><i class="fa fa-trash"></i></button>'));
            $body.append($tr);
        });
        $('#tests-table').toggle(state.tests.length > 0);
        $('#tests-empty').toggle(state.tests.length === 0);
        renderTotals();
    }
    $(document).on('input', '.t-price, .t-disc', function () {
        const t = state.tests[$(this).closest('tr').data('i')];
        if ($(this).hasClass('t-price')) t.price = Math.max(0, parseFloat($(this).val()) || 0);
        else t.discount = Math.max(0, parseFloat($(this).val()) || 0);
        renderTotals();
    });
    $(document).on('click', '.t-del', function () {
        state.tests.splice($(this).closest('tr').data('i'), 1);
        renderTests();
    });

    function totals() {
        const sub = state.tests.reduce((s, t) => s + t.price, 0);
        const disc = state.tests.reduce((s, t) => s + Math.min(t.discount, t.price), 0);
        const net = sub - disc;
        const paid = Math.max(0, parseFloat($('#v-paid').val()) || 0);
        return { sub, disc, net, paid, bal: net - paid };
    }
    function renderTotals() {
        const t = totals();
        $('#t-sub').text(money(t.sub));
        $('#t-disc').text(money(t.disc));
        $('#t-net').text(money(t.net));
        $('#t-paid').text(money(t.paid));
        $('#t-bal').text(money(t.bal));
        $('#t-bal-wrap').toggleClass('due', t.bal > 0).toggleClass('paid', t.bal <= 0);
    }
    $('#v-paid').on('input', renderTotals);
    $('#btn-pay-full').on('click', () => { $('#v-paid').val(money(totals().net)); renderTotals(); });

    // ── Step 3: vitals ───────────────────────────────────
    $('#vt-weight, #vt-height').on('input', function () {
        const w = parseFloat($('#vt-weight').val()), h = parseFloat($('#vt-height').val());
        $('#vt-bmi').text(w > 0 && h > 0 ? (w / Math.pow(h / 100, 2)).toFixed(1) : '—');
    });

    function vitals() {
        const v = {
            temperature: $('#vt-temp').val(), temp_unit: $('#vt-temp-unit').val(), pulse: $('#vt-pulse').val(),
            blood_pressure: $('#vt-bp').val().trim(), spo2: $('#vt-spo2').val(), respiratory_rate: $('#vt-rr').val(),
            weight: $('#vt-weight').val(), height: $('#vt-height').val(), notes: $('#vt-notes').val().trim(),
        };
        Object.keys(v).forEach(k => { if (v[k] === '') delete v[k]; });
        return v;
    }

    // ── Step 4: review & submit ──────────────────────────
    function line(label, value) {
        return $('<div class="review-line">').append($('<span>').text(label), $('<span>').text(value));
    }
    function renderReview() {
        const $p = $('#rv-patient').empty();
        if (state.mode === 'existing' && state.patient) {
            $p.append(line('Name', state.patient.name), line('Patient ID', state.patient.patient_id), line('Age / Sex', `${state.patient.age} ${state.patient.age_type || ''} / ${state.patient.gender}`));
        } else {
            $p.append(line('Name', `${$('#p-first').val().trim()} ${$('#p-last').val().trim()}`), line('Age / Sex', `${$('#p-age').val()} ${$('#p-age-type').val()} / ${$('#p-gender').val()}`), line('Phone', $('#p-phone').val() || '—'), line('Status', 'New registration'));
        }
        $p.append(line('Referred by', $('#v-doctor').val().trim() || 'Self'));

        const t = totals();
        $('#rv-bill').empty().append(line('Net payable', '₹' + money(t.net)), line('Received', '₹' + money(t.paid) + ' (' + $('#v-method').val() + ')'), line('Balance', '₹' + money(t.bal)));

        const $t = $('#rv-tests').empty();
        state.tests.forEach(x => $t.append(line(x.name, '₹' + money(x.price - Math.min(x.discount, x.price)))));

        const v = vitals();
        const labels = { temperature: 'Temp', pulse: 'Pulse', blood_pressure: 'BP', spo2: 'SpO₂', respiratory_rate: 'Resp.', weight: 'Weight', height: 'Height', notes: 'Notes' };
        const $v = $('#rv-vitals').empty();
        const keys = Object.keys(labels).filter(k => v[k] !== undefined);
        if (!keys.length) $v.append($('<div class="meta" style="color:var(--text-muted);font-size:13px;">').text('Skipped'));
        keys.forEach(k => $v.append(line(labels[k], k === 'temperature' ? `${v[k]} °${v.temp_unit}` : v[k])));
    }

    function submit() {
        const $btn = $('#btn-next');
        const payload = {
            patient_mode: state.mode,
            existing_patient_id: state.mode === 'existing' && state.patient ? state.patient.id : null,
            patient: state.mode === 'new' ? {
                first_name: $('#p-first').val().trim(), last_name: $('#p-last').val().trim(), gender: $('#p-gender').val(),
                age: $('#p-age').val(), age_type: $('#p-age-type').val(), phone: $('#p-phone').val().trim() || null,
                email: $('#p-email').val().trim() || null, address: $('#p-address').val().trim() || null,
            } : undefined,
            doctor_name: $('#v-doctor').val().trim() || 'Self',
            tests: state.tests.map(t => ({ name: t.name, price: t.price, discount: Math.min(t.discount, t.price) })),
            advance_paid: totals().paid,
            payment_method: $('#v-method').val(),
            vitals: vitals(),
        };

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving…');
        $.ajax({ url: @json(route('visits.store')), method: 'POST', contentType: 'application/json', data: JSON.stringify(payload), dataType: 'json' })
            .done(res => {
                $('#done-title').text('Visit registered');
                $('#done-sub').text(`${res.patient.name} · ${res.patient.patient_id} · ${res.patient.pending_tests} test(s) waiting for results · Balance ₹${money(res.payment.balance_due)}`);
                $('#done-results').attr('href', res.links.results);
                $('#done-card').attr('href', res.links.patient_card);
                go(5);
            })
            .fail(xhr => {
                const errors = xhr.responseJSON?.errors;
                const msg = errors ? Object.values(errors).map(e => e[0]).join('\n') : (xhr.responseJSON?.message || 'Could not save the visit. Please try again.');
                Swal.fire({ icon: 'error', title: 'Not saved', text: msg, confirmButtonColor: '#1a56db' });
            })
            .always(() => $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Register visit'));
    }

    // ── Init ─────────────────────────────────────────────
    if (PRESELECTED) choosePatient(PRESELECTED);
    renderTests();
})();
</script>
@endpush
