@extends('layouts.app')
@section('title', ' | Payments & Billing')
@section('page-title', 'Payments & Billing')

@push('styles')
<style>
    .pay-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
    .pay-stat { background: var(--white); border: 1px solid var(--border-color); border-radius: 14px; padding: 14px 16px; }
    .pay-stat .lbl { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); }
    .pay-stat .val { font-size: 22px; font-weight: 800; margin-top: 4px; }
    .pay-stat .sub { font-size: 12px; color: var(--text-muted); }
    @media (max-width: 991px) { .pay-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

    .status-filter { display: flex; gap: 6px; flex-wrap: wrap; }
    .status-filter button { border: 1.5px solid var(--border-color); background: var(--white); border-radius: 20px; padding: 5px 12px; font-size: 12.5px; font-weight: 600; color: var(--text-muted); }
    .status-filter button.on { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
    .status-filter .n { font-weight: 700; margin-left: 4px; opacity: .8; }

    .amt-due { color: #dc2626; font-weight: 700; }
    .amt-clear { color: #059669; font-weight: 700; }
    .badge-gray { background: #f1f5f9; color: #475569; }
    .bill-sub { font-size: 11.5px; color: var(--text-muted); }

    .status-preview { display: inline-flex; align-items: center; gap: 8px; }
    .modal-aw .form-label { font-size: 11.5px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
    .linked-note { font-size: 12px; background: #eff6ff; color: #1e40af; border-radius: 8px; padding: 8px 10px; margin-bottom: 12px; display: none; }
</style>
@endpush

@section('content')
@php
    $statusBadge = fn ($s) => match ($s) { 'Paid' => 'badge-green', 'Partial' => 'badge-orange', 'Refunded' => 'badge-gray', default => 'badge-red' };
    $methodIcon = fn ($m) => match ($m) { 'Cash' => 'fa-money-bill', 'UPI' => 'fa-mobile-screen', 'Card' => 'fa-credit-card', 'Net Banking', 'Bank Transfer' => 'fa-building-columns', 'Online' => 'fa-globe', default => 'fa-wallet' };
@endphp
<div class="page-header-aw">
    <div class="page-title-aw">
        <div class="title-icon"><i class="fa fa-credit-card"></i></div>
        <div>
            <div>Payments &amp; Billing</div>
            <div style="font-size:13px;font-weight:400;color:var(--text-muted);margin-top:2px;">Bills, collections and outstanding balances</div>
        </div>
    </div>
    <button type="button" class="btn-aw-primary" data-bs-toggle="modal" data-bs-target="#modal-add-payment">
        <i class="fa fa-plus"></i> Add New Payment
    </button>
</div>

<div class="pay-stats">
    <div class="pay-stat"><div class="lbl">Billed</div><div class="val">₹{{ number_format($stats['billed'], 2) }}</div><div class="sub">Net of discounts</div></div>
    <div class="pay-stat"><div class="lbl">Collected</div><div class="val" style="color:#059669;">₹{{ number_format($stats['collected'], 2) }}</div><div class="sub">Amount received</div></div>
    <div class="pay-stat"><div class="lbl">Outstanding</div><div class="val" style="color:#dc2626;">₹{{ number_format($stats['outstanding'], 2) }}</div><div class="sub">{{ ($stats['counts']['Unpaid'] ?? 0) + ($stats['counts']['Partial'] ?? 0) }} bill(s) with balance</div></div>
    <div class="pay-stat"><div class="lbl">Bills</div><div class="val">{{ $payments->count() }}</div><div class="sub">{{ $stats['counts']['Paid'] ?? 0 }} paid · {{ $stats['counts']['Refunded'] ?? 0 }} refunded</div></div>
</div>

<div class="aw-card mb-4">
    <div class="aw-card-header">
        <div class="status-filter" role="group" aria-label="Filter by status">
            <button type="button" class="on" data-status="">All<span class="n">{{ $payments->count() }}</span></button>
            @foreach(['Unpaid', 'Partial', 'Paid', 'Refunded'] as $st)
                <button type="button" data-status="{{ $st }}">{{ $st }}<span class="n">{{ $stats['counts'][$st] ?? 0 }}</span></button>
            @endforeach
        </div>
        <div style="position:relative;">
            <i class="fa fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
            <input type="text" id="payment-search" style="border:1.5px solid var(--border-color);border-radius:9px;padding:8px 12px 8px 32px;font-size:13px;outline:none;width:210px;" placeholder="Search patient, bill…" autocomplete="off">
        </div>
    </div>
    <div class="aw-card-body" style="padding:0;">
        <div class="table-responsive-modern">
<table class="table-modern" id="payments-table">
    <thead>
        <tr>
            <th>Bill</th>
            <th>Patient</th>
            <th>Net Amount</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Method</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($payments as $p)
        @php $balance = (float) $p->balance_due; @endphp
        <tr data-status="{{ $p->payment_status }}">
            <td data-label="Bill">
                <span class="badge-aw badge-blue">#{{ $p->id }}</span>
                <div class="bill-sub mt-1">{{ \Carbon\Carbon::parse($p->bill_date)->format('d M Y') }}</div>
            </td>
            <td data-label="Patient">
                <div style="font-weight:600;">{{ optional($p->patient)->first_name }} {{ optional($p->patient)->last_name }}</div>
                <div class="bill-sub">{{ optional($p->patient)->patient_id }}@if($p->appointments_count) · {{ $p->appointments_count }} test(s)@endif</div>
            </td>
            <td data-label="Net Amount">
                <div style="font-weight:700;color:var(--primary);">₹{{ number_format($p->net_amount ?? 0, 2) }}</div>
                @if((float) $p->discount > 0)
                    <div class="bill-sub">₹{{ number_format($p->total_amount, 2) }} − ₹{{ number_format($p->discount, 2) }} disc.</div>
                @endif
            </td>
            <td data-label="Paid">₹{{ number_format($p->advance_paid ?? 0, 2) }}</td>
            <td data-label="Balance">
                @if($p->payment_status === 'Refunded')
                    <span class="bill-sub">—</span>
                @elseif($balance > 0)
                    <span class="amt-due">₹{{ number_format($balance, 2) }}</span>
                @else
                    <span class="amt-clear"><i class="fa fa-circle-check me-1"></i>₹0.00</span>
                @endif
            </td>
            <td data-label="Method">
                @php $method = $p->payment_method ?: 'Cash'; @endphp
                <span class="badge-aw badge-purple"><i class="fa {{ $methodIcon($method) }} me-1"></i>{{ $method }}</span>
            </td>
            <td data-label="Status">
                <span class="badge-aw {{ $statusBadge($p->payment_status) }}">{{ $p->payment_status }}</span>
            </td>
            <td data-label="Actions" class="text-end">
                <div class="d-flex justify-content-end gap-2">
                    @if($balance > 0 && $p->payment_status !== 'Refunded')
                        <button class="btn-aw-outline btn-aw-sm btn-settle" data-id="{{ $p->id }}" data-balance="{{ number_format($balance, 2, '.', '') }}" data-method="{{ $method }}" title="Receive balance"><i class="fa fa-hand-holding-dollar"></i> <span class="d-none d-xl-inline">Receive</span></button>
                    @endif
                    <button class="btn-aw-primary btn-aw-sm btn-edit" data-id="{{ $p->id }}" title="Edit"><i class="fa fa-pen"></i></button>
                    <button class="btn-aw-danger btn-aw-sm btn-delete" data-id="{{ $p->id }}" data-linked="{{ $p->appointments_count }}" title="Delete"><i class="fa fa-trash"></i></button>
                </div>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
        </div>
    </div>
</div>

@foreach(['add' => 'Add New Payment', 'edit' => 'Edit Payment'] as $mode => $title)
<div class="modal fade modal-aw" id="modal-{{ $mode }}-payment" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa {{ $mode === 'add' ? 'fa-credit-card' : 'fa-pen' }} me-2"></i>{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="form-{{ $mode }}-payment" novalidate>
          @if($mode === 'edit')<input type="hidden" name="id" id="edit-id">@endif
          <div class="linked-note" id="{{ $mode }}-linked-note"><i class="fa fa-link me-1"></i> This bill was created from booked tests. Its total and discount come from the tests; edit them on the Patients page.</div>
          <div class="row g-3">
            <div class="col-12">
              <label for="{{ $mode }}-patient-id" class="form-label">Patient</label>
              <select class="form-select" name="patient_id" id="{{ $mode }}-patient-id" required>
                <option value="">-- Select Patient --</option>
                @foreach($patients as $patient)
                  <option value="{{ $patient->id }}">{{ $patient->first_name }} {{ $patient->last_name }} ({{ $patient->patient_id }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-6">
              <label for="{{ $mode }}-bill-date" class="form-label">Bill Date</label>
              <input type="date" class="form-control" name="bill_date" id="{{ $mode }}-bill-date" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-6">
              <label for="{{ $mode }}-method" class="form-label">Payment Method</label>
              <select class="form-select" name="payment_method" id="{{ $mode }}-method" required>
                @foreach($paymentMethods as $m)
                  <option value="{{ $m }}">{{ $m }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-6">
              <label for="{{ $mode }}-total" class="form-label">Total Amount (₹)</label>
              <input type="number" step="0.01" min="0" class="form-control calc-{{ $mode }}" name="total_amount" id="{{ $mode }}-total" required inputmode="decimal">
            </div>
            <div class="col-6">
              <label for="{{ $mode }}-discount" class="form-label">Discount (₹)</label>
              <input type="number" step="0.01" min="0" class="form-control calc-{{ $mode }}" name="discount" id="{{ $mode }}-discount" value="0.00" inputmode="decimal">
            </div>
            <div class="col-6">
              <label for="{{ $mode }}-advance" class="form-label">Amount Paid (₹)</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" class="form-control calc-{{ $mode }}" name="advance_paid" id="{{ $mode }}-advance" value="0.00" inputmode="decimal">
                <button type="button" class="btn btn-outline-success btn-full" data-mode="{{ $mode }}" title="Paid in full">Full</button>
              </div>
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="Refunded" name="payment_status" id="{{ $mode }}-refunded">
                <label class="form-check-label" for="{{ $mode }}-refunded" style="text-transform:none;font-size:13px;color:var(--text-dark);">Mark as refunded</label>
              </div>
            </div>
          </div>
          <div class="bg-primary-light p-3 rounded my-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="status-preview">Status: <span class="badge-aw" id="{{ $mode }}-status-preview">Unpaid</span></span>
            <h5 class="mb-0 fw-bold">Balance: ₹<span id="{{ $mode }}-balance">0.00</span></h5>
          </div>
          <div class="text-danger small mb-2" id="{{ $mode }}-error" style="display:none;"></div>
          <div>
            <label for="{{ $mode }}-remarks" class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" id="{{ $mode }}-remarks" rows="2" maxlength="500"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-aw-outline" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-aw-primary" id="btn-{{ $mode === 'add' ? 'save' : 'update' }}-payment"><i class="fa fa-check"></i> {{ $mode === 'add' ? 'Save Payment' : 'Update Payment' }}</button>
      </div>
    </div>
  </div>
</div>
@endforeach

@push('scripts')
<script>
  $(document).ready(function() {
    const BADGE = { Paid: 'badge-green', Partial: 'badge-orange', Unpaid: 'badge-red', Refunded: 'badge-gray' };
    const errorText = (xhr, fallback) => {
      const j = xhr.responseJSON || {};
      return j.errors ? Object.values(j.errors).map(e => e[0]).join('\n') : (j.error || j.message || fallback);
    };

    // ── Table, search and status filter
    const paymentsTable = $('#payments-table').DataTable({
      dom: "<'row mb-3'<'col-sm-12 col-md-6'l>>" + "<'row'<'col-sm-12'tr>>" + "<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
      pageLength: 10, lengthMenu: [5, 10, 25, 50, 100], ordering: false,
      language: {
        lengthMenu: "Show _MENU_ records", info: "Showing _START_ to _END_ of _TOTAL_ bills",
        infoEmpty: "No bills", infoFiltered: "(filtered from _MAX_)", emptyTable: "No payment records found.",
        paginate: { previous: "<i class='fa fa-angle-left'></i>", next: "<i class='fa fa-angle-right'></i>" }
      }
    });
    $('#payment-search').on('keyup', function() { paymentsTable.search($(this).val()).draw(); });
    let statusFilter = '';
    $.fn.dataTable.ext.search.push((settings, data, idx) =>
      settings.nTable.id !== 'payments-table' || !statusFilter || paymentsTable.row(idx).node().dataset.status === statusFilter);
    $('.status-filter button').on('click', function() {
      $('.status-filter button').removeClass('on'); $(this).addClass('on');
      statusFilter = $(this).data('status'); paymentsTable.draw();
    });

    // ── Live balance + status preview (same rules as the server)
    function recalc(mode) {
      const total = parseFloat($(`#${mode}-total`).val()) || 0;
      const discount = parseFloat($(`#${mode}-discount`).val()) || 0;
      const paid = parseFloat($(`#${mode}-advance`).val()) || 0;
      const net = total - discount;
      const balance = net - paid;
      let status = balance <= 0 ? 'Paid' : (paid > 0 ? 'Partial' : 'Unpaid');
      if ($(`#${mode}-refunded`).is(':checked')) status = 'Refunded';
      $(`#${mode}-balance`).text(balance.toFixed(2)).css('color', balance > 0 ? '#dc2626' : '#059669');
      $(`#${mode}-status-preview`).text(status).attr('class', 'badge-aw ' + BADGE[status]);
      let err = '';
      if (discount > total) err = 'Discount is more than the total.';
      else if (balance < 0) err = 'Amount paid is more than the net amount.';
      $(`#${mode}-error`).text(err).toggle(!!err);
      return !err;
    }
    ['add', 'edit'].forEach(m => $(document).on('input change', `.calc-${m}, #${m}-refunded`, () => recalc(m)));
    $('.btn-full').on('click', function() {
      const m = $(this).data('mode');
      const net = (parseFloat($(`#${m}-total`).val()) || 0) - (parseFloat($(`#${m}-discount`).val()) || 0);
      $(`#${m}-advance`).val(Math.max(0, net).toFixed(2)); recalc(m);
    });

    function submit(mode, url, method, btn) {
      const form = $(`#form-${mode}-payment`)[0];
      if (!form.checkValidity()) { form.reportValidity(); return; }
      if (!recalc(mode)) return;
      btn.prop('disabled', true);
      $.ajax({ url, type: method, data: $(form).serialize() })
        .done(() => location.reload())
        .fail(xhr => { alert(errorText(xhr, 'Could not save payment.')); btn.prop('disabled', false); });
    }

    $('#modal-add-payment').on('show.bs.modal', function() {
      $('#form-add-payment')[0].reset(); $('#add-bill-date').val(new Date().toISOString().slice(0, 10)); recalc('add');
    });
    $('#btn-save-payment').on('click', function() { submit('add', "{{ route('payments.store') }}", 'POST', $(this)); });

    // ── Edit
    $(document).on('click', '.btn-edit', function() {
      $.get("/payments/" + $(this).data('id'), function(data) {
        $('#edit-id').val(data.id);
        $('#edit-patient-id').val(data.patient_id);
        $('#edit-bill-date').val(String(data.bill_date || '').slice(0, 10));
        $('#edit-total').val(parseFloat(data.total_amount || 0).toFixed(2));
        $('#edit-discount').val(parseFloat(data.discount || 0).toFixed(2));
        $('#edit-advance').val(parseFloat(data.advance_paid || 0).toFixed(2));
        // Keep a method that is no longer in the list instead of silently switching it
        if (data.payment_method && !$(`#edit-method option[value="${CSS.escape(data.payment_method)}"]`).length) {
          $('#edit-method').append($('<option>').val(data.payment_method).text(data.payment_method));
        }
        $('#edit-method').val(data.payment_method || 'Cash');
        $('#edit-refunded').prop('checked', data.payment_status === 'Refunded');
        $('#edit-remarks').val(data.remarks || '');
        const linked = data.appointments_count > 0;
        $('#edit-total, #edit-discount').prop('readonly', linked);
        $('#edit-patient-id').prop('disabled', false).css('pointer-events', linked ? 'none' : '');
        $('#edit-linked-note').toggle(linked);
        recalc('edit');
        $('#modal-edit-payment').modal('show');
      }).fail(xhr => alert(errorText(xhr, 'Could not load the bill.')));
    });
    $('#btn-update-payment').on('click', function() { submit('edit', "/payments/" + $('#edit-id').val(), 'PUT', $(this)); });

    // ── Receive the remaining balance
    $(document).on('click', '.btn-settle', function() {
      const id = $(this).data('id'), balance = $(this).data('balance'), method = $(this).data('method');
      const options = @json($paymentMethods).map(m => `<option value="${escHtml(m)}" ${m === method ? 'selected' : ''}>${escHtml(m)}</option>`).join('');
      Swal.fire({
        title: `Receive ₹${balance}?`,
        html: `Bill #${id} will be marked as fully paid.<br><select id="settle-method" class="form-select mt-3">${options}</select>`,
        icon: 'question', showCancelButton: true, confirmButtonText: 'Receive payment', confirmButtonColor: '#059669',
        preConfirm: () => document.getElementById('settle-method').value,
      }).then(r => {
        if (!r.isConfirmed) return;
        $.post(`/payments/${id}/settle`, { payment_method: r.value })
          .done(res => { showToast(res.success); setTimeout(() => location.reload(), 700); })
          .fail(xhr => alert(errorText(xhr, 'Could not record the payment.')));
      });
    });

    // ── Delete
    $(document).on('click', '.btn-delete', function() {
      const id = $(this).data('id');
      if ($(this).data('linked') > 0) {
        Swal.fire({ icon: 'info', title: 'Bill linked to tests', text: 'This bill belongs to booked tests. Remove the tests from the patient (Patients → Edit) and the bill is removed automatically.', confirmButtonColor: '#1a56db' });
        return;
      }
      Swal.fire({ title: `Delete bill #${id}?`, text: 'This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' })
        .then(r => {
          if (!r.isConfirmed) return;
          $.ajax({ url: "/payments/" + id, type: 'DELETE' })
            .done(() => location.reload())
            .fail(xhr => alert(errorText(xhr, 'Could not delete payment.')));
        });
    });
  });
</script>
@endpush
@endsection
