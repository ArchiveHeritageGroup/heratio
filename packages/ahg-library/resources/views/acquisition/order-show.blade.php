@extends('theme::layouts.1col')
@section('title', 'Purchase Order')
@section('content')
@php
    $statuses = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','library_order_status')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
    $disposalReasons = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','acq_disposal_reason')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
    $editable = !in_array($order->status??'', ['received','cancelled']);
@endphp
<div class="container py-4">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="mb-1"><i class="fas fa-file-invoice me-2"></i>{{ e($order->order_number??'') }}</h1><span class="badge bg-secondary">{{ e($order->status??'') }}</span> @if($order->written_off_reason ?? null)<span class="badge bg-danger ms-1">{{ __('Written off') }}: {{ e($order->written_off_reason) }}</span>@endif</div><div><a href="{{ route('library.acquisition-order-edit',$order->id) }}" class="btn btn-outline-secondary"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</a> <a href="{{ route('library.acquisitions') }}" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('All Orders') }}</a></div></div>

<div class="row">
<div class="col-md-8">
<div class="card mb-3"><div class="card-header"><i class="fas fa-info-circle me-1"></i>{{ __('Order Details') }}</div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">{{ __('Vendor') }}</dt><dd class="col-sm-9">{{ e($order->vendor_name??'-') }}</dd><dt class="col-sm-3">{{ __('Order Date') }}</dt><dd class="col-sm-9">{{ $order->order_date??'-' }}</dd><dt class="col-sm-3">{{ __('Expected') }}</dt><dd class="col-sm-9">{{ $order->expected_date??'-' }}</dd><dt class="col-sm-3">{{ __('Type') }}</dt><dd class="col-sm-9">{{ e($order->order_type??'-') }}</dd><dt class="col-sm-3">{{ __('Budget') }}</dt><dd class="col-sm-9">{{ e($order->budget_code??'-') }}</dd>@if($order->notes ?? null)<dt class="col-sm-3">{{ __('Notes') }}</dt><dd class="col-sm-9">{!! nl2br(e($order->notes)) !!}</dd>@endif</dl></div></div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-list-ol me-1"></i>{{ __('Order Lines') }}</span>@if($editable)<form method="post" action="{{ route('library.acquisition-order-receive-all',$order->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Mark all pending lines as received?') }}')">@csrf<button class="btn btn-sm btn-success"><i class="fas fa-box-open me-1"></i>{{ __('Receive All') }}</button></form>@endif</div><div class="card-body p-0"><div id="acq-lines-container">@include('ahg-library::acquisition._order-lines', ['lines'=>$lines, 'order'=>$order, 'editable'=>$editable])</div></div></div>
</div>

<div class="col-md-4">
<div class="card mb-3"><div class="card-header"><i class="fas fa-calculator me-1"></i>{{ __('Totals') }}</div><div class="card-body"><dl class="row mb-0"><dt class="col-7">{{ __('Subtotal') }}</dt><dd class="col-5 text-end">{{ number_format((float)($order->subtotal??0),2) }}</dd><dt class="col-7">{{ __('Tax') }}</dt><dd class="col-5 text-end">{{ number_format((float)($order->tax??0),2) }}</dd><dt class="col-7">{{ __('Shipping') }}</dt><dd class="col-5 text-end">{{ number_format((float)($order->shipping??0),2) }}</dd><dt class="col-7"><strong>{{ __('Total') }}</strong></dt><dd class="col-5 text-end"><strong>{{ number_format((float)($order->total??0),2) }} {{ e($order->currency??'') }}</strong></dd></dl></div></div>

@if($budget)<div class="card mb-3"><div class="card-header"><i class="fas fa-wallet me-1"></i>{{ __('Budget') }}</div><div class="card-body"><strong>{{ e($budget->fund_name??'') }}</strong> <span class="text-muted">({{ e($budget->budget_code??'') }})</span>@php $alloc=(float)($budget->allocated_amount??0); $spent=(float)($budget->spent_amount??0); $pct=$alloc>0?min(100,round($spent/$alloc*100)):0; @endphp<div class="progress mt-2" style="height:18px"><div class="progress-bar {{ $pct>=90?'bg-danger':($pct>=70?'bg-warning':'bg-success') }}" role="progressbar" style="width:{{ $pct }}%">{{ $pct }}%</div></div><small class="text-muted">{{ number_format($spent,2) }} / {{ number_format($alloc,2) }} {{ e($budget->currency??'') }}</small></div></div>@endif

<div class="card mb-3"><div class="card-header"><i class="fas fa-exchange-alt me-1"></i>{{ __('Status') }}</div><div class="card-body"><form method="post" action="{{ route('library.acquisition-order-transition',$order->id) }}" class="d-flex gap-2">@csrf<select name="status" class="form-select form-select-sm">@foreach($statuses as $s)<option value="{{ $s->code }}" {{ ($order->status??'')===$s->code?'selected':'' }}>{{ $s->label }}</option>@endforeach</select><button class="btn btn-sm btn-primary">{{ __('Set') }}</button></form></div></div>

@if(($order->status??'') !== 'cancelled')<div class="card border-danger"><div class="card-header bg-danger text-white"><i class="fas fa-ban me-1"></i>{{ __('Write Off (GRAP 103 / IPSAS 17)') }}</div><div class="card-body"><form method="post" action="{{ route('library.acquisition-order-write-off',$order->id) }}" onsubmit="return confirm('{{ __('Write off this order? This releases its budget commitment.') }}')">@csrf<div class="mb-2"><label class="form-label form-label-sm">{{ __('Reason') }}</label><select name="reason" class="form-select form-select-sm" required>@foreach($disposalReasons as $r)<option value="{{ $r->code }}">{{ $r->label }}</option>@endforeach</select></div><div class="mb-2"><textarea name="note" class="form-control form-control-sm" rows="2" placeholder="{{ __('Note (optional)') }}"></textarea></div><button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-ban me-1"></i>{{ __('Write Off') }}</button></form></div></div>@endif
</div>
</div>
</div>

{{-- #1311 Multi-fund split editor modal --}}
<div class="modal fade" id="acqSplitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-code-branch me-2"></i>{{ __('Split line across funds') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2" id="acqSplitLineTitle"></p>
        <div class="alert alert-danger d-none" id="acqSplitError"></div>
        <div class="d-flex justify-content-between mb-2">
          <span>{{ __('Line total') }}: <strong id="acqSplitLineTotal">0.00</strong></span>
          <span>{{ __('Allocated') }}: <strong id="acqSplitAllocated">0.00</strong> <span id="acqSplitBalanceBadge" class="badge bg-secondary ms-1">{{ __('Balance') }}: <span id="acqSplitBalance">0.00</span></span></span>
        </div>
        <table class="table table-sm" id="acqSplitTable">
          <thead><tr><th>{{ __('Fund') }}</th><th class="text-end" style="width:30%">{{ __('Amount') }}</th><th style="width:1%"></th></tr></thead>
          <tbody></tbody>
        </table>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="acqSplitAddRow"><i class="fas fa-plus me-1"></i>{{ __('Add fund') }}</button>
        <p class="text-muted small mt-2 mb-0">{{ __('Leave the table empty to revert this line to its single fund. The amounts must sum to the line total.') }}</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-primary" id="acqSplitSave">{{ __('Save splits') }}</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    var orderId = {{ (int) $order->id }};
    var csrf = '{{ csrf_token() }}';
    var modalEl = document.getElementById('acqSplitModal');
    if (!modalEl || typeof bootstrap === 'undefined') { return; }
    var modal = new bootstrap.Modal(modalEl);
    var tbody = modalEl.querySelector('#acqSplitTable tbody');
    var budgets = [];
    var currentLineId = null;
    var lineTotal = 0;

    function fmt(n) { return (Number(n) || 0).toFixed(2); }

    function fundOptions(selected) {
        var html = '<option value="">' + {{ Js::from(__('-- select fund --')) }} + '</option>';
        budgets.forEach(function (b) {
            var label = b.fund_name + ' (' + b.budget_code + ')';
            var sel = (b.budget_code === selected) ? ' selected' : '';
            html += '<option value="' + b.budget_code + '"' + sel + '>' + label + '</option>';
        });
        return html;
    }

    function addRow(fundCode, amount) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select class="form-select form-select-sm acq-split-fund">' + fundOptions(fundCode || '') + '</select></td>' +
            '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end acq-split-amount" value="' + (amount != null ? fmt(amount) : '') + '"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger acq-split-remove"><i class="fas fa-times"></i></button></td>';
        tbody.appendChild(tr);
        recalc();
    }

    function recalc() {
        var sum = 0;
        tbody.querySelectorAll('.acq-split-amount').forEach(function (i) { sum += Number(i.value) || 0; });
        modalEl.querySelector('#acqSplitAllocated').textContent = fmt(sum);
        var bal = lineTotal - sum;
        modalEl.querySelector('#acqSplitBalance').textContent = fmt(bal);
        var badge = modalEl.querySelector('#acqSplitBalanceBadge');
        badge.className = 'badge ms-1 ' + (Math.abs(bal) < 0.005 ? 'bg-success' : 'bg-warning');
    }

    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('acq-split-amount')) { recalc(); }
    });
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.acq-split-remove');
        if (btn) { btn.closest('tr').remove(); recalc(); }
    });
    modalEl.querySelector('#acqSplitAddRow').addEventListener('click', function () { addRow('', null); });

    function showError(msg) {
        var box = modalEl.querySelector('#acqSplitError');
        box.textContent = msg;
        box.classList.remove('d-none');
    }
    function clearError() {
        modalEl.querySelector('#acqSplitError').classList.add('d-none');
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.acq-split-btn');
        if (!btn) { return; }
        currentLineId = btn.getAttribute('data-line-id');
        lineTotal = Number(btn.getAttribute('data-line-total')) || 0;
        clearError();
        tbody.innerHTML = '';
        modalEl.querySelector('#acqSplitLineTitle').textContent = btn.getAttribute('data-line-title') || '';
        modalEl.querySelector('#acqSplitLineTotal').textContent = fmt(lineTotal);

        fetch('{{ url('/library-manage/acquisition/order') }}/' + orderId + '/line/' + currentLineId + '/funds', {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            budgets = data.budgets || [];
            if (data.splits && data.splits.length) {
                data.splits.forEach(function (s) { addRow(s.fund_code, s.amount); });
            } else {
                addRow('', null);
            }
            recalc();
            modal.show();
        }).catch(function () { showError({{ Js::from(__('Could not load fund data.')) }}); modal.show(); });
    });

    modalEl.querySelector('#acqSplitSave').addEventListener('click', function () {
        clearError();
        var splits = [];
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var code = tr.querySelector('.acq-split-fund').value;
            var amt = tr.querySelector('.acq-split-amount').value;
            if (code !== '' || (amt !== '' && Number(amt) !== 0)) {
                splits.push({ fund_code: code, amount: amt === '' ? 0 : Number(amt) });
            }
        });

        fetch('{{ url('/library-manage/acquisition/order') }}/' + orderId + '/line/' + currentLineId + '/funds', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ splits: splits })
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
          .then(function (res) {
            if (!res.ok || !res.body.success) {
                showError(res.body.error || {{ Js::from(__('Save failed.')) }});
                return;
            }
            modal.hide();
            window.location.reload();
        }).catch(function () { showError({{ Js::from(__('Save failed.')) }}); });
    });
})();
</script>
@endsection
