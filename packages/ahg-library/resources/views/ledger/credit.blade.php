@extends('theme::layouts.1col')
@section('title', __('Raise credit note'))
@section('content')
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-undo me-2"></i>{{ __('Credit note against :n',['n'=>$doc->document_number]) }}</h1>
@include('ahg-library::ledger._nav')
<form method="post" action="{{ route('library.ledger.credit-store',$doc->id) }}">@csrf
<div class="card card-body mb-3"><div class="row g-3">
  <div class="col-12"><label class="form-label" for="credit_reason">{{ __('Credit type') }}</label><select id="credit_reason" name="credit_reason" class="form-select" required aria-describedby="credit_reason_help"><option value="">{{ __('Select') }}</option>@foreach($creditReasons as $code=>$label)<option value="{{ $code }}" @selected(old('credit_reason')===$code)>{{ $label }}</option>@endforeach</select><div id="credit_reason_help" class="form-text">{{ __('Only a billing correction at the original price lets the supplier invoice that quantity again. Returned goods stay counted as billed.') }}</div></div>
  <div class="col-md-4"><label class="form-label" for="supplier_reference">{{ __('Supplier credit note number') }}</label><input id="supplier_reference" name="supplier_reference" class="form-control" maxlength="100" required value="{{ old('supplier_reference') }}"></div>
  <div class="col-md-3"><label class="form-label" for="document_date">{{ __('Date') }}</label><input type="date" id="document_date" name="document_date" min="{{ $doc->document_date }}" class="form-control" required value="{{ old('document_date',now()->toDateString()) }}"></div>
  <div class="col-md-5"><label class="form-label" for="notes">{{ __('Reason') }}</label><input id="notes" name="notes" class="form-control" maxlength="2000" required value="{{ old('notes') }}"></div>
</div></div>
<div class="card mb-3"><div class="card-header">{{ __('Lines to credit. Leave quantity empty to skip. The original tax rate is kept.') }}</div><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>{{ __('Description') }}</th><th class="text-end">{{ __('Invoiced') }}</th><th class="text-end">{{ __('Left to credit') }}</th><th>{{ __('Tax') }}</th><th style="width:7rem">{{ __('Quantity') }}</th><th style="width:9rem">{{ __('Unit price') }}</th></tr></thead>
<tbody>@foreach($lines as $i=>$l)<tr>
<td>{{ $l->description }}<input type="hidden" name="lines[{{ $i }}][credited_line_id]" value="{{ $l->id }}"></td><td class="text-end">{{ $l->quantity }} x {{ $l->unit_price }}</td><td class="text-end">{{ \AhgLibrary\Services\Ledger\LedgerMoney::fromCents($l->remaining_net) }}</td><td>{{ $l->tax_code }} ({{ $l->tax_rate }}%)</td>
<td><input aria-label="{{ __('Quantity') }}" type="number" min="0" max="65535" name="lines[{{ $i }}][quantity]" class="form-control form-control-sm" value="{{ old("lines.$i.quantity") }}" @disabled($l->remaining_net <= 0)></td>
<td><input aria-label="{{ __('Unit price') }}" inputmode="decimal" name="lines[{{ $i }}][unit_price]" class="form-control form-control-sm" value="{{ old("lines.$i.unit_price",$l->unit_price) }}" @disabled($l->remaining_net <= 0)></td>
</tr>@endforeach</tbody></table></div></div>
<button class="btn btn-primary">{{ __('Save draft credit note') }}</button> <a class="btn btn-outline-secondary" href="{{ route('library.ledger.show',$doc->id) }}">{{ __('Cancel') }}</a>
</form>
</div>
@endsection
