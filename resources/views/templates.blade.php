@extends('layouts.app')
@section('title', ' | Test Panels')
@section('page-title', 'Test Panels')

@push('styles')
<style>
    .tp-toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .tp-toolbar .search-wrap { position: relative; }
    .tp-toolbar .search-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; }
    .tp-toolbar input, .tp-toolbar select { border: 1.5px solid var(--border-color); border-radius: 9px; padding: 8px 12px; font-size: 13px; outline: none; background: var(--white); }
    .tp-toolbar input { padding-left: 32px; width: 230px; }
    .chip { display: inline-flex; align-items: center; gap: 6px; background: var(--primary-light); color: var(--primary); border-radius: 20px; padding: 3px 10px; font-size: 12px; font-weight: 600; margin: 2px 4px 2px 0; }
    .chip button { border: 0; background: none; color: inherit; padding: 0; line-height: 1; }
    .chip.muted { background: #f1f5f9; color: var(--text-muted); }
    .chip.warn { background: #fff7ed; color: #c2410c; }

    /* Builder */
    #modal-panel .modal-body { background: #f8fafc; }
    .pb-card { background: var(--white); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; }
    .pb-card h6 { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
    .pb-label { font-size: 11.5px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px; display: block; }
    .pb-card .form-control, .pb-card .form-select { font-size: 13.5px; border-radius: 8px; }
    .it-row { background: var(--white); border: 1px solid var(--border-color); border-radius: 10px; margin-bottom: 8px; }
    .it-row.sortable-ghost { opacity: .4; }
    .it-row.is-heading { background: #f1f5f9; border-style: dashed; }
    .it-row.has-error { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
    .it-main { display: flex; gap: 8px; align-items: flex-start; padding: 8px 10px; flex-wrap: wrap; }
    .it-handle { cursor: grab; color: #94a3b8; padding: 7px 2px; }
    .it-name { flex: 1 1 220px; min-width: 180px; position: relative; }
    .it-code { width: 92px; text-transform: uppercase; }
    .it-unit { width: 110px; }
    .it-ref { flex: 1 1 160px; min-width: 140px; }
    .it-actions { display: flex; gap: 4px; }
    .it-actions .btn { padding: 4px 8px; }
    .it-link { font-size: 11.5px; color: var(--text-muted); margin-top: 3px; }
    .it-link .linked { color: #059669; font-weight: 600; }
    .it-link .new { color: #d97706; font-weight: 600; }
    .it-more { border-top: 1px dashed var(--border-color); padding: 10px 12px 12px 34px; display: none; }
    .it-row.open .it-more { display: block; }
    .it-badge { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 5px; background: #ede9fe; color: #6d28d9; margin-left: 6px; }
    .it-error { color: #dc2626; font-size: 12px; padding: 0 12px 8px 34px; }
    .suggest { position: absolute; z-index: 30; left: 0; right: 0; top: 100%; background: var(--white); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.1); max-height: 240px; overflow-y: auto; display: none; }
    .suggest div { padding: 7px 10px; font-size: 13px; cursor: pointer; display: flex; justify-content: space-between; gap: 8px; }
    .suggest div:hover, .suggest div.on { background: var(--primary-light); }
    .suggest small { color: var(--text-muted); }
    .code-chip { display: inline-block; font-family: monospace; font-size: 11.5px; background: #f1f5f9; border-radius: 5px; padding: 1px 6px; margin: 2px; cursor: pointer; }
    .formula-ok { color: #059669; font-size: 12px; }
    .formula-bad { color: #dc2626; font-size: 12px; }
    .preview-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .preview-table th { text-align: left; font-size: 11px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1.5px solid var(--border-color); padding: 6px 8px; }
    .preview-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .preview-table tr.ph td { font-weight: 700; font-style: italic; background: #f8fafc; }
    .preview-dept { color: #8a277d; font-weight: 800; letter-spacing: .5px; border-bottom: 2px solid #8a277d; padding-bottom: 4px; margin-bottom: 6px; }
    @media (max-width: 767px) {
        .it-code, .it-unit { width: calc(50% - 30px); }
        .it-ref { flex-basis: 100%; }
        .it-more { padding-left: 12px; }
        .tp-toolbar input { width: 100%; }
        .tp-toolbar .search-wrap { flex: 1 1 100%; }
    }
</style>
@endpush

@section('content')
<div class="page-header-aw">
    <div class="page-title-aw">
        <div class="title-icon"><i class="fa fa-layer-group"></i></div>
        <div>
            <div>Test Panels</div>
            <div style="font-size:13px;font-weight:400;color:var(--text-muted);margin-top:2px;">Profiles like CBC or LFT: what a billed test expands into when results are entered</div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn-aw-outline" id="btn-open-library"><i class="fa fa-book-medical"></i> Standard panels</button>
        <button type="button" class="btn-aw-primary" id="btn-new-panel"><i class="fa fa-plus"></i> New Panel</button>
    </div>
</div>

<div class="aw-card mb-4">
    <div class="aw-card-header">
        <div class="tp-toolbar">
            <div class="search-wrap"><i class="fa fa-search"></i><input type="search" id="panel-search" placeholder="Search panels…" aria-label="Search panels"></div>
            <select id="panel-filter" aria-label="Filter panels">
                <option value="">All panels</option>
                <option value="linked">Billed (linked to a test)</option>
                <option value="unlinked">Not linked to billing</option>
                <option value="calc">With calculations</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <span style="font-size:12px;color:var(--text-muted);">{{ $templates->count() }} panels</span>
    </div>
    <div class="aw-card-body" style="padding:0;">
        <div class="table-responsive-modern">
            <table class="table-modern" id="panels-table">
                <thead>
                    <tr><th>Panel</th><th>Department</th><th>Parameters</th><th>Billed as</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @foreach($templates as $t)
                        <tr data-linked="{{ $t->billingTests->isNotEmpty() ? 1 : 0 }}" data-calc="{{ $t->calc_count > 0 ? 1 : 0 }}" data-active="{{ $t->is_active ? 1 : 0 }}">
                            <td data-label="Panel">
                                <div style="font-weight:600;">{{ $t->name }}</div>
                                @if(!$t->is_active)<span class="chip muted">Inactive</span>@endif
                            </td>
                            <td data-label="Department" style="color:var(--text-muted);">{{ $t->department ?: '—' }}</td>
                            <td data-label="Parameters">
                                {{ $t->params_count }}
                                @if($t->calc_count)<span class="it-badge">{{ $t->calc_count }} calc</span>@endif
                            </td>
                            <td data-label="Billed as">
                                @forelse($t->billingTests as $bt)
                                    <span class="chip">{{ $bt->name }} · ₹{{ number_format($bt->price, 0) }}</span>
                                @empty
                                    <span class="chip warn" title="Booking a test will not expand into this panel">Not linked</span>
                                @endforelse
                            </td>
                            <td data-label="Actions" class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn-aw-primary btn-aw-sm btn-edit-panel" data-id="{{ $t->id }}" title="Edit"><i class="fa fa-pen"></i></button>
                                    <button type="button" class="btn-aw-outline btn-aw-sm btn-duplicate-panel" data-id="{{ $t->id }}" title="Duplicate"><i class="fa fa-copy"></i></button>
                                    <button type="button" class="btn-aw-danger btn-aw-sm btn-delete-panel" data-id="{{ $t->id }}" data-name="{{ $t->name }}" data-linked="{{ $t->billingTests->count() }}" title="Delete"><i class="fa fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Panel builder ───────────────────────────────────────────── --}}
<div class="modal fade modal-aw" id="modal-panel" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="panel-title"><i class="fa fa-layer-group me-2"></i>New Panel</h5>
                <ul class="nav nav-pills ms-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active py-1 px-3" data-bs-toggle="pill" data-bs-target="#tab-build" type="button">Build</button></li>
                    <li class="nav-item"><button class="nav-link py-1 px-3" data-bs-toggle="pill" data-bs-target="#tab-preview" type="button" id="btn-preview-tab">Preview</button></li>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-build">
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <div class="pb-card mb-3">
                                    <h6>Panel</h6>
                                    <label class="pb-label" for="p-name">Name *</label>
                                    <input class="form-control mb-2" id="p-name" maxlength="255" placeholder="e.g. Complete Blood Count (CBC)">
                                    <label class="pb-label" for="p-dept">Department (printed heading)</label>
                                    <input class="form-control mb-2" id="p-dept" list="dept-list" maxlength="255" placeholder="e.g. HAEMATOLOGY">
                                    <datalist id="dept-list">@foreach($departments as $d)<option value="{{ $d }}">@endforeach</datalist>
                                    <label class="pb-label" for="p-desc">Description</label>
                                    <input class="form-control mb-2" id="p-desc" maxlength="2000">
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="p-active" checked>
                                        <label class="form-check-label" for="p-active" style="font-size:13px;">Active (available when entering results)</label>
                                    </div>
                                </div>
                                <div class="pb-card mb-3">
                                    <h6>Billed as <small style="text-transform:none;font-weight:500;">booking these expands into this panel</small></h6>
                                    <div id="billing-chips" class="mb-2"></div>
                                    <div style="position:relative;">
                                        <input class="form-control form-control-sm" id="billing-search" placeholder="Link a billing test…" autocomplete="off">
                                        <div class="suggest" id="billing-suggest"></div>
                                    </div>
                                    <div class="input-group input-group-sm mt-2">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" min="0" step="0.01" class="form-control" id="billing-new-price" placeholder="Price">
                                        <button type="button" class="btn btn-outline-primary" id="btn-create-billing" title="Create a billing test with the panel's name">Create billing test</button>
                                    </div>
                                </div>
                                <div class="pb-card">
                                    <h6>Interpretation <small style="text-transform:none;font-weight:500;">printed under the panel</small></h6>
                                    <textarea class="form-control" id="p-interp" rows="4" maxlength="5000" placeholder="e.g. LDL is calculated by the Friedewald formula…"></textarea>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="pb-card">
                                    <h6>
                                        <span>Parameters <span id="items-count" class="chip muted">0</span></span>
                                        <span class="d-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-heading"><i class="fa fa-heading"></i> Sub-heading</button>
                                            <button type="button" class="btn btn-sm btn-primary" id="btn-add-param"><i class="fa fa-plus"></i> Parameter</button>
                                        </span>
                                    </h6>
                                    <div id="items-empty" class="text-center py-4" style="color:var(--text-muted);font-size:13px;">
                                        Add parameters from the catalogue (start typing a name) or create new ones.<br>Drag <i class="fa fa-grip-vertical"></i> to reorder.
                                    </div>
                                    <div id="items-list"></div>
                                    <div class="mt-2" style="font-size:12px;color:var(--text-muted);">
                                        Codes used in this panel (click to copy into a formula): <span id="codes-list">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-preview">
                        <div class="pb-card" style="max-width:820px;margin:0 auto;">
                            <h6>How it prints <small style="text-transform:none;font-weight:500;">(ranges shown for a male / female adult)</small></h6>
                            <div id="preview"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div id="panel-errors" class="text-danger small me-auto" style="max-width:60%;"></div>
                <button type="button" class="btn-aw-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-aw-primary" id="btn-save-panel"><i class="fa fa-check"></i> Save Panel</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Standard library ─────────────────────────────────────────── --}}
<div class="modal fade modal-aw" id="modal-library" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa fa-book-medical me-2"></i>Standard panels</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="font-size:13px;color:var(--text-muted);">Clean, ready-made panels with units, adult reference ranges, calculated parameters and result pick-lists. Each comes with a billing test (price ₹0 – set your price). <b>Verify the ranges against your analyser / kit inserts before use.</b></p>
                @foreach($standardPanels as $name)
                    @php $exists = in_array($name, $installedPanels, true); @endphp
                    <div class="form-check mb-2">
                        <input class="form-check-input lib-check" type="checkbox" value="{{ $name }}" id="lib-{{ $loop->index }}" {{ $exists ? 'disabled' : 'checked' }}>
                        <label class="form-check-label" for="lib-{{ $loop->index }}">{{ $name }} @if($exists)<span class="chip muted">installed</span>@endif</label>
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-aw-outline" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-aw-primary" id="btn-install-library"><i class="fa fa-download"></i> Install selected</button>
            </div>
        </div>
    </div>
</div>

<datalist id="unit-list">@foreach($units as $u)<option value="{{ $u }}">@endforeach</datalist>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script>
(function () {
    const CATALOG = @json($catalog);
    const byId = new Map(CATALOG.map(t => [t.id, t]));
    let panel = null;      // panel being edited
    let uidSeq = 1;

    const num = v => (v === null || v === undefined || v === '') ? '' : String(+v);
    const errText = xhr => { const j = xhr.responseJSON || {}; return j.errors ? Object.values(j.errors).map(e => e[0]).join(' ') : (j.error || j.message || 'Something went wrong.'); };

    // ── List: search + filter ─────────────────────────────
    const table = $('#panels-table').DataTable({
        dom: "<'row mb-3'<'col-sm-12 col-md-6'l>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        pageLength: 25, lengthMenu: [10, 25, 50, 100], ordering: false,
        language: { lengthMenu: "Show _MENU_", info: "Showing _START_ to _END_ of _TOTAL_ panels", emptyTable: "No panels yet. Create one or install the standard panels.", infoEmpty: "No panels", infoFiltered: "(filtered from _MAX_)",
            paginate: { previous: "<i class='fa fa-angle-left'></i>", next: "<i class='fa fa-angle-right'></i>" } }
    });
    $('#panel-search').on('input', function () { table.search(this.value).draw(); });
    let listFilter = '';
    $.fn.dataTable.ext.search.push((settings, data, idx) => {
        if (settings.nTable.id !== 'panels-table' || !listFilter) return true;
        const d = table.row(idx).node().dataset;
        return { linked: d.linked === '1', unlinked: d.linked === '0', calc: d.calc === '1', inactive: d.active === '0' }[listFilter];
    });
    $('#panel-filter').on('change', function () { listFilter = this.value; table.draw(); });

    // ── Catalogue search (shared by parameters and billing) ──
    function searchCatalog(q, limit = 8) {
        q = q.trim().toLowerCase();
        if (!q) return [];
        const starts = [], has = [];
        for (const t of CATALOG) {
            const n = t.n.toLowerCase();
            if (n.startsWith(q)) starts.push(t); else if (n.includes(q)) has.push(t);
            if (starts.length >= limit) break;
        }
        return starts.concat(has).slice(0, limit);
    }

    // ── Panel state <-> form ──────────────────────────────
    function blankItem(type) {
        return { uid: uidSeq++, item_type: type, name: '', lab_test_id: null, code: '', unit: '', normal_value: '', default_value: '', options: '', formula: '', decimals: '', ranges: {} };
    }

    function openPanel(data) {
        panel = data;
        $('#panel-title').html('<i class="fa fa-layer-group me-2"></i>' + (data.id ? 'Edit Panel' : 'New Panel'));
        $('#p-name').val(data.name || '');
        $('#p-dept').val(data.department || '');
        $('#p-desc').val(data.description || '');
        $('#p-interp').val(data.interpretation || '');
        $('#p-active').prop('checked', data.is_active !== false);
        $('#billing-new-price').val('');
        $('#panel-errors').empty();
        renderBilling();
        renderItems();
        bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-build"]')).show();
        $('#modal-panel').modal('show');
    }

    $('#btn-new-panel').on('click', () => openPanel({ id: null, name: '', billing: [], items: [blankItem('parameter')] }));

    $(document).on('click', '.btn-edit-panel', function () {
        $.getJSON('/templates/' + $(this).data('id')).done(t => {
            openPanel({
                id: t.id, name: t.name, department: t.department, description: t.description, interpretation: t.interpretation, is_active: !!t.is_active,
                billing: (t.billing_tests || []).map(b => ({ id: b.id, name: b.name, price: +b.price })),
                items: (t.items || []).map(i => ({
                    uid: uidSeq++, item_type: i.item_type || 'parameter', name: i.name || '', lab_test_id: i.lab_test_id,
                    code: i.code || '', unit: i.unit || '', normal_value: i.normal_value || '', default_value: i.default_value || '',
                    options: i.options || '', formula: i.formula || '', decimals: i.decimals ?? '', ranges: {},
                })),
            });
        }).fail(xhr => showToast(errText(xhr), 'error'));
    });

    // ── Billing links ─────────────────────────────────────
    function renderBilling() {
        const $c = $('#billing-chips').empty();
        if (!panel.billing.length) $c.append($('<span class="chip warn">').text('Not linked – booking will not expand into this panel'));
        panel.billing.forEach((b, i) => {
            const chip = $('<span class="chip">').text(`${b.name} · ₹${(+b.price || 0).toFixed(0)}`);
            chip.append($('<button type="button" aria-label="Unlink">').html('&times;').on('click', () => { panel.billing.splice(i, 1); renderBilling(); }));
            $c.append(chip);
        });
    }
    $('#billing-search').on('input', function () {
        const $s = $('#billing-suggest').empty();
        const hits = searchCatalog(this.value);
        hits.forEach(t => {
            const other = t.panel && t.panel !== panel.id ? ' · linked to another panel' : '';
            $s.append($('<div>').append($('<span>').text(t.n), $('<small>').text(`₹${(+t.p).toFixed(0)}${other}`)).on('mousedown', e => {
                e.preventDefault();
                if (!panel.billing.some(b => b.id === t.id)) panel.billing.push({ id: t.id, name: t.n, price: t.p });
                renderBilling(); $('#billing-search').val(''); $s.hide();
            }));
        });
        $s.toggle(hits.length > 0);
    }).on('blur', () => setTimeout(() => $('#billing-suggest').hide(), 150));

    $('#btn-create-billing').on('click', function () {
        const name = $('#p-name').val().trim();
        if (!name) { showToast('Enter the panel name first.', 'warning'); $('#p-name').focus(); return; }
        const price = $('#billing-new-price').val() || 0;
        $.post("{{ route('lab-tests.store') }}", { name, price }).done(res => {
            const t = res.test;
            CATALOG.push({ id: t.id, n: t.name, p: +t.price }); byId.set(t.id, { id: t.id, n: t.name, p: +t.price });
            panel.billing.push({ id: t.id, name: t.name, price: +t.price }); renderBilling();
            showToast('Billing test created and linked', 'success');
        }).fail(xhr => showToast(errText(xhr), 'error'));
    });

    // ── Items ─────────────────────────────────────────────
    function itemRow(it) {
        const $r = $('<div class="it-row">').attr('data-uid', it.uid).toggleClass('is-heading', it.item_type === 'heading');
        const $main = $('<div class="it-main">').append('<span class="it-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></span>');
        if (it.item_type === 'heading') {
            $main.append($('<div class="it-name">').append($('<input class="form-control form-control-sm f-name fw-bold" placeholder="Sub-heading, e.g. Differential Count" maxlength="255">').val(it.name)));
            $main.append('<div class="it-actions"><button type="button" class="btn btn-sm btn-outline-danger f-del" title="Remove"><i class="fa fa-trash"></i></button></div>');
            return $r.append($main);
        }
        const master = it.lab_test_id ? byId.get(+it.lab_test_id) : null;
        const $name = $('<div class="it-name">').append(
            $('<input class="form-control form-control-sm f-name" placeholder="Parameter – type to search the catalogue" autocomplete="off" maxlength="255">').val(it.name),
            $('<div class="suggest">'),
            $('<div class="it-link">').html(master ? `<span class="linked"><i class="fa fa-link"></i> ${escHtml(master.n)}</span> <a href="#" class="f-unlink">unlink</a>` : (it.name ? '<span class="new"><i class="fa fa-plus-circle"></i> new parameter – created on save</span>' : '')),
        );
        $main.append($name,
            $('<input class="form-control form-control-sm it-code f-code" placeholder="CODE" maxlength="30" title="Short code used in formulas, e.g. HB">').val(it.code),
            $('<input class="form-control form-control-sm it-unit f-unit" list="unit-list" maxlength="100">').attr('placeholder', master?.u || 'Unit').val(it.unit),
            $('<input class="form-control form-control-sm it-ref f-ref" maxlength="1000" title="Leave empty to use the patient-specific range">').attr('placeholder', master?.r ? 'Range: ' + master.r : 'Reference range text').val(it.normal_value),
            $('<div class="it-actions">').append(
                $('<button type="button" class="btn btn-sm btn-outline-secondary f-more" title="Ranges, formula, defaults">').html('<i class="fa fa-sliders"></i>' + (it.formula ? '<span class="it-badge">fx</span>' : '')),
                '<button type="button" class="btn btn-sm btn-outline-danger f-del" title="Remove"><i class="fa fa-trash"></i></button>'));
        const rng = it.ranges || {};
        const pv = k => num(rng[k] ?? (master ? master[{ male_min: 'mm', male_max: 'mx', female_min: 'fm', female_max: 'fx' }[k]] : ''));
        const $more = $('<div class="it-more">').html(`
            <div class="row g-2">
                <div class="col-6 col-md-3"><label class="pb-label">Male min</label><input type="number" step="any" class="form-control form-control-sm f-rng" data-k="male_min"></div>
                <div class="col-6 col-md-3"><label class="pb-label">Male max</label><input type="number" step="any" class="form-control form-control-sm f-rng" data-k="male_max"></div>
                <div class="col-6 col-md-3"><label class="pb-label">Female min</label><input type="number" step="any" class="form-control form-control-sm f-rng" data-k="female_min"></div>
                <div class="col-6 col-md-3"><label class="pb-label">Female max</label><input type="number" step="any" class="form-control form-control-sm f-rng" data-k="female_max"></div>
                <div class="col-md-6"><label class="pb-label">Default result</label><input class="form-control form-control-sm f-default" maxlength="255" placeholder="e.g. Nil"></div>
                <div class="col-md-6"><label class="pb-label">Result choices <small style="text-transform:none;">comma separated, * = abnormal</small></label><input class="form-control form-control-sm f-options" maxlength="500" placeholder="Nil, Trace*, +*, ++*"></div>
                <div class="col-md-9"><label class="pb-label">Formula <small style="text-transform:none;">calculated from other codes, e.g. {TC} - {HDL} - {TG} / 5</small></label><input class="form-control form-control-sm f-formula" maxlength="255"><div class="f-formula-msg"></div></div>
                <div class="col-md-3"><label class="pb-label">Decimals</label><input type="number" min="0" max="6" class="form-control form-control-sm f-dec"></div>
            </div>`);
        $more.find('.f-rng').each(function () { $(this).val(pv(this.dataset.k)); });
        $more.find('.f-default').val(it.default_value); $more.find('.f-options').val(it.options); $more.find('.f-formula').val(it.formula); $more.find('.f-dec').val(it.decimals);
        return $r.append($main, $more);
    }

    function renderItems() {
        const $l = $('#items-list').empty();
        panel.items.forEach(it => $l.append(itemRow(it)));
        $('#items-empty').toggle(panel.items.length === 0);
        refreshMeta();
    }

    function itemOf(el) { const uid = +$(el).closest('.it-row').data('uid'); return panel.items.find(i => i.uid === uid); }

    function refreshMeta() {
        $('#items-count').text(panel.items.filter(i => i.item_type === 'parameter').length);
        const codes = panel.items.filter(i => i.item_type === 'parameter' && i.code).map(i => i.code.toUpperCase());
        const $c = $('#codes-list').empty();
        if (!codes.length) $c.text('—');
        codes.forEach(c => $c.append($('<span class="code-chip">').text('{' + c + '}')));
        // live formula check
        $('#items-list .it-row').each(function () {
            const it = itemOf(this);
            const $msg = $(this).find('.f-formula-msg');
            if (!it || !it.formula) { $msg.empty(); return; }
            const refs = (it.formula.match(/\{([A-Za-z0-9_]+)\}/g) || []).map(r => r.slice(1, -1).toUpperCase());
            const missing = refs.filter(r => !codes.includes(r));
            const bad = /[^\d\s.+\-*/(){}A-Za-z_]/.test(it.formula) || !refs.length;
            if (bad) $msg.html('<span class="formula-bad">Use {CODES}, numbers and + - * / ( ) only.</span>');
            else if (missing.length) $msg.html('<span class="formula-bad">Unknown code: ' + escHtml(missing.join(', ')) + '</span>');
            else if (it.code && refs.includes(it.code.toUpperCase())) $msg.html('<span class="formula-bad">A parameter cannot use its own code.</span>');
            else $msg.html('<span class="formula-ok"><i class="fa fa-check"></i> Uses ' + escHtml(refs.join(', ')) + '</span>');
        });
    }

    Sortable.create(document.getElementById('items-list'), {
        handle: '.it-handle', animation: 150,
        onEnd: () => {
            const order = $('#items-list .it-row').map(function () { return +$(this).data('uid'); }).get();
            panel.items.sort((a, b) => order.indexOf(a.uid) - order.indexOf(b.uid));
        },
    });

    $('#btn-add-param').on('click', () => { panel.items.push(blankItem('parameter')); renderItems(); $('#items-list .it-row:last .f-name').focus(); });
    $('#btn-add-heading').on('click', () => { panel.items.push(blankItem('heading')); renderItems(); $('#items-list .it-row:last .f-name').focus(); });

    $(document).on('click', '#items-list .f-del', function () {
        const it = itemOf(this); panel.items = panel.items.filter(i => i !== it); renderItems();
    });
    $(document).on('click', '#items-list .f-more', function () { $(this).closest('.it-row').toggleClass('open'); });
    $(document).on('click', '#items-list .f-unlink', function (e) { e.preventDefault(); const it = itemOf(this); it.lab_test_id = null; it.ranges = {}; renderItems(); });

    $(document).on('input', '#items-list .f-code', function () { this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''); itemOf(this).code = this.value; refreshMeta(); });
    $(document).on('input', '#items-list .f-unit', function () { itemOf(this).unit = this.value; });
    $(document).on('input', '#items-list .f-ref', function () { itemOf(this).normal_value = this.value; });
    $(document).on('input', '#items-list .f-default', function () { itemOf(this).default_value = this.value; });
    $(document).on('input', '#items-list .f-options', function () { itemOf(this).options = this.value; });
    $(document).on('input', '#items-list .f-formula', function () { itemOf(this).formula = this.value; refreshMeta(); });
    $(document).on('input', '#items-list .f-dec', function () { itemOf(this).decimals = this.value; });
    $(document).on('input', '#items-list .f-rng', function () { itemOf(this).ranges[this.dataset.k] = this.value; });
    $(document).on('click', '#codes-list .code-chip', function () {
        const f = $('#items-list .it-row.open .f-formula').last();
        if (f.length) { f.val((f.val() + ' ' + $(this).text()).trim()).trigger('input').focus(); }
        else navigator.clipboard?.writeText($(this).text());
    });

    // name input: free text or pick from catalogue
    $(document).on('input', '#items-list .f-name', function () {
        const it = itemOf(this); it.name = this.value;
        if (it.item_type !== 'parameter') return;
        const $s = $(this).siblings('.suggest').empty();
        searchCatalog(this.value).forEach(t => {
            $s.append($('<div>').append($('<span>').text(t.n), $('<small>').text([t.u, t.r].filter(Boolean).join(' · '))).on('mousedown', e => {
                e.preventDefault();
                it.lab_test_id = t.id; it.name = t.n; it.ranges = {};
                if (!it.code) it.code = t.n.replace(/\(.*?\)/g, '').trim().split(/\s+/).map(w => w[0]).join('').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 6);
                renderItems();
            }));
        });
        $s.toggle($s.children().length > 0);
        $(this).siblings('.it-link').html(it.lab_test_id ? $(this).siblings('.it-link').html() : (this.value ? '<span class="new"><i class="fa fa-plus-circle"></i> new parameter – created on save</span>' : ''));
    }).on('blur', '#items-list .f-name', function () { const s = $(this).siblings('.suggest'); setTimeout(() => s.hide(), 150); });

    // ── Preview ───────────────────────────────────────────
    $('#btn-preview-tab').on('shown.bs.tab', renderPreview);
    function renderPreview() {
        const $p = $('#preview').empty();
        const dept = $('#p-dept').val().trim() || $('#p-name').val().trim() || 'PANEL';
        $p.append($('<div class="preview-dept">').text(dept.toUpperCase()));
        if ($('#p-dept').val().trim()) $p.append($('<div style="font-weight:700;margin:6px 0;">').text($('#p-name').val()));
        const $t = $('<table class="preview-table"><thead><tr><th>Test</th><th>Result</th><th>Unit</th><th>Reference range</th></tr></thead><tbody></tbody></table>');
        panel.items.forEach(it => {
            if (it.item_type === 'heading') { $t.find('tbody').append($('<tr class="ph">').append($('<td colspan="4">').text(it.name))); return; }
            const m = it.lab_test_id ? byId.get(+it.lab_test_id) : null;
            const ref = it.normal_value || (m ? (m.fr && m.fr !== m.r ? `M: ${m.r || '-'}\nF: ${m.fr}` : (m.r || '')) : '');
            const res = it.formula ? 'calc' : (it.default_value || '—');
            $t.find('tbody').append($('<tr>').append($('<td>').text(it.name), $('<td style="color:var(--text-muted);">').text(res), $('<td>').text(it.unit || m?.u || ''), $('<td style="white-space:pre-line;">').text(ref)));
        });
        $p.append($t);
        const note = $('#p-interp').val().trim();
        if (note) $p.append($('<div class="mt-3 p-2" style="background:#f8fafc;border-left:3px solid #8a277d;white-space:pre-line;font-size:13px;">').text(note));
    }

    // ── Save ──────────────────────────────────────────────
    $('#btn-save-panel').on('click', function () {
        const $btn = $(this);
        $('.it-row').removeClass('has-error').find('.it-error').remove();
        $('#panel-errors').empty();
        const items = panel.items.filter(i => (i.name || '').trim() !== '');
        if (!$('#p-name').val().trim()) { $('#panel-errors').text('Panel name is required.'); $('#p-name').focus(); return; }
        if (!items.some(i => i.item_type === 'parameter')) { $('#panel-errors').text('Add at least one parameter.'); return; }
        const payload = {
            name: $('#p-name').val().trim(), department: $('#p-dept').val().trim() || null, description: $('#p-desc').val().trim() || null,
            interpretation: $('#p-interp').val().trim() || null, is_active: $('#p-active').is(':checked'),
            billing_test_ids: panel.billing.map(b => b.id),
            items: items.map(i => ({
                item_type: i.item_type, name: i.name.trim(), lab_test_id: i.lab_test_id || null, code: i.code || null,
                unit: i.unit || null, normal_value: i.normal_value || null, default_value: i.default_value || null,
                options: i.options || null, formula: i.formula || null, decimals: i.decimals === '' ? null : +i.decimals,
                ranges: Object.keys(i.ranges || {}).length ? i.ranges : undefined,
            })),
        };
        $btn.prop('disabled', true);
        $.ajax({ url: panel.id ? '/templates/' + panel.id : "{{ route('templates.store') }}", method: panel.id ? 'PUT' : 'POST', contentType: 'application/json', data: JSON.stringify(payload) })
            .done(res => { showToast(res.success, 'success'); setTimeout(() => location.reload(), 600); })
            .fail(xhr => {
                const errs = (xhr.responseJSON || {}).errors || {};
                const general = [];
                Object.entries(errs).forEach(([key, msgs]) => {
                    const m = key.match(/^items\.(\d+)\./);
                    if (m) {
                        const it = items[+m[1]];
                        const $row = $(`#items-list .it-row[data-uid="${it.uid}"]`).addClass('has-error open');
                        $row.append($('<div class="it-error">').text(msgs[0]));
                    } else general.push(msgs[0]);
                });
                $('#panel-errors').text(general.join(' ') || (Object.keys(errs).length ? 'Please fix the highlighted rows.' : errText(xhr)));
                $('#items-list .has-error').first()[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            })
            .always(() => $btn.prop('disabled', false));
    });

    // ── Duplicate / delete ────────────────────────────────
    $(document).on('click', '.btn-duplicate-panel', function () {
        $.post('/templates/' + $(this).data('id') + '/duplicate').done(res => { showToast(res.success, 'success'); setTimeout(() => location.reload(), 600); }).fail(xhr => showToast(errText(xhr), 'error'));
    });
    $(document).on('click', '.btn-delete-panel', function () {
        const id = $(this).data('id'), name = $(this).data('name'), linked = +$(this).data('linked');
        confirmDelete({
            title: 'Delete panel "' + name + '"?',
            text: linked ? `${linked} billing test(s) are linked to it; booking them will no longer expand into parameters. Saved reports are not affected.` : 'Saved reports are not affected.',
        }, function () {
            $.ajax({ url: '/templates/' + id, type: 'DELETE' }).done(res => { showToast(res.success, 'success'); setTimeout(() => location.reload(), 600); }).fail(xhr => showToast(errText(xhr), 'error'));
        });
    });

    // ── Standard library ─────────────────────────────────
    $('#btn-open-library').on('click', () => $('#modal-library').modal('show'));
    $('#btn-install-library').on('click', function () {
        const panels = $('.lib-check:checked:not(:disabled)').map(function () { return this.value; }).get();
        if (!panels.length) { showToast('Select at least one panel.', 'warning'); return; }
        const $b = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Installing…');
        $.ajax({ url: "{{ route('templates.install-standard') }}", method: 'POST', contentType: 'application/json', data: JSON.stringify({ panels }) })
            .done(res => Swal.fire({ icon: 'success', title: 'Standard panels', text: res.success, confirmButtonColor: '#1a56db' }).then(() => location.reload()))
            .fail(xhr => { showToast(errText(xhr), 'error'); $b.prop('disabled', false).html('<i class="fa fa-download"></i> Install selected'); });
    });
})();
</script>
@endpush
