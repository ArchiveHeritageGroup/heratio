@extends('theme::layouts.1col')
@section('title', $doc->document_number ?: __('Draft document'))
@section('content')
<div class="container py-4">
<h1 class="mb-1"><i class="fas fa-receipt me-2"></i>{{ $doc->document_number ?: __('Draft #:id',['id'=>$doc->id]) }}</h1>
<p><span class="badge bg-secondary">{{ $statuses[$doc->status] ?? $doc->status }}</span> {{ $docTypes[$doc->doc_type] ?? $doc->doc_type }}</p>
@include('ahg-library::ledger._nav')
<div class="row"><div class="col-lg-8">
<div class="card mb-3"><div class="card-body"><dl class="row mb-0">
  <dt class="col-sm-4">{{ __('Supplier') }}</dt><dd class="col-sm-8">{{ $doc->vendor_name }}</dd>
  <dt class="col-sm-4">{{ __('Supplier reference') }}</dt><dd class="col-sm-8">{{ $doc->supplier_reference }}</dd>
  <dt class="col-sm-4">{{ __('Order') }}</dt><dd class="col-sm-8"><a href="{{ route('library.acquisition-order',$doc->order_id) }}">{{ $doc->order_number }}</a></dd>
  @if($doc->credited_document_id)<dt class="col-sm-4">{{ __('Credits invoice') }}</dt><dd class="col-sm-8"><a href="{{ route('library.ledger.show',$doc->credited_document_id) }}">{{ $doc->credited_number }}</a></dd>@endif
  @if($doc->credit_reason)<dt class="col-sm-4">{{ __('Credit type') }}</dt><dd class="col-sm-8">{{ $creditReasons[$doc->credit_reason] ?? $doc->credit_reason }}</dd>@endif
  <dt class="col-sm-4">{{ __('Date') }}</dt><dd class="col-sm-8">{{ $doc->document_date }}</dd>
  @if($doc->due_date)<dt class="col-sm-4">{{ __('Due') }}</dt><dd class="col-sm-8">{{ $doc->due_date }}</dd>@endif
  @if($doc->notes)<dt class="col-sm-4">{{ __('Notes') }}</dt><dd class="col-sm-8">{{ $doc->notes }}</dd>@endif
  @if($doc->variance_reason)<dt class="col-sm-4">{{ __('Posted with variance') }}</dt><dd class="col-sm-8">{{ $doc->variance_reason }}</dd>@endif
  @if($doc->void_reason)<dt class="col-sm-4">{{ __('Void reason') }}</dt><dd class="col-sm-8">{{ $doc->void_reason }}</dd>@endif
  @if($doc->export_batch)<dt class="col-sm-4">{{ __('Export batch') }}</dt><dd class="col-sm-8">{{ $doc->export_batch }}</dd>@endif
</dl></div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>{{ __('Description') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Unit') }}</th><th class="text-end">{{ __('Net') }}</th><th>{{ __('Tax code') }}</th><th class="text-end">{{ __('Rate %') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end">{{ __('Gross') }}</th></tr></thead>
<tbody>@foreach($lines as $l)<tr><td>{{ $l->description }}</td><td class="text-end">{{ $l->quantity }}</td><td class="text-end">{{ $l->unit_price }}</td><td class="text-end">{{ $l->net_amount }}</td><td>{{ $l->tax_code }}</td><td class="text-end">{{ $doc->status === 'draft' ? '-' : $l->tax_rate }}</td><td class="text-end">{{ $doc->status === 'draft' ? '-' : $l->tax_amount }}</td><td class="text-end">{{ $doc->status === 'draft' ? '-' : $l->gross_amount }}</td></tr>@endforeach</tbody>
<tfoot><tr><th colspan="3" class="text-end">{{ __('Totals') }} ({{ $doc->currency }})</th><th class="text-end">{{ $doc->subtotal }}</th><th colspan="2"></th><th class="text-end">{{ $doc->status === 'draft' ? '-' : $doc->tax_total }}</th><th class="text-end">{{ $doc->status === 'draft' ? '-' : $doc->total }}</th></tr></tfoot>
</table></div>@if($doc->status === 'draft')<div class="card-footer text-muted small">{{ __('Tax is calculated when the document is posted.') }}</div>@endif</div>
@if(count($credits))<div class="card mb-3"><div class="card-header">{{ __('Credit notes') }}</div><ul class="list-group list-group-flush">@foreach($credits as $c)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('library.ledger.show',$c->id) }}">{{ $c->document_number ?: __('Draft #:id',['id'=>$c->id]) }}</a><span>{{ $statuses[$c->status] ?? $c->status }} - {{ $c->total }}</span></li>@endforeach</ul></div>@endif
@if(count($payments))<div class="card mb-3"><div class="card-header">{{ __('Payments') }}</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Reference') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead><tbody>@foreach($payments as $p)<tr class="{{ $p->reversed_at ? 'text-muted text-decoration-line-through' : '' }}"><td>{{ $p->payment_date }}</td><td>{{ $p->reference }}</td><td class="text-end">{{ $p->amount }}</td><td class="text-end">@unless($p->reversed_at)<form method="post" action="{{ route('library.ledger.payment-reverse',[$doc->id,$p->id]) }}" onsubmit="return confirm('{{ __('Reverse this payment?') }}')">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Reverse') }}</button></form>@endunless</td></tr>@endforeach</tbody></table></div></div>@endif
</div><div class="col-lg-4">
@if($doc->status === 'draft')
<div class="card mb-3"><div class="card-header">{{ __('Post') }}</div><div class="card-body">
@if(count($variances))<div class="alert alert-warning small"><strong>{{ __('Does not match the order or receipts') }}</strong><ul class="mb-0">@foreach($variances as $v)<li>{{ $v }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ route('library.ledger.post',$doc->id) }}">@csrf
@if(count($variances))<label class="form-label" for="variance_reason">{{ __('Reason for posting anyway') }}</label><input id="variance_reason" name="variance_reason" class="form-control mb-2" maxlength="255" required>@endif
<button class="btn btn-primary w-100">{{ __('Post document') }}</button></form>
@if($doc->doc_type === 'invoice')<a class="btn btn-outline-secondary w-100 mt-2" href="{{ route('library.ledger.edit',$doc->id) }}">{{ __('Edit draft') }}</a>@endif
</div></div>
@endif
@if($isPostedInvoice)
<div class="card mb-3"><div class="card-header">{{ __('Outstanding') }}: {{ $outstanding }} {{ $doc->currency }}</div><div class="card-body">
<form method="post" action="{{ route('library.ledger.payment',$doc->id) }}">@csrf
<label class="form-label" for="payment_date">{{ __('Payment date') }}</label><input type="date" id="payment_date" name="payment_date" class="form-control mb-2" required value="{{ now()->toDateString() }}">
<label class="form-label" for="amount">{{ __('Amount') }}</label><input id="amount" name="amount" inputmode="decimal" class="form-control mb-2" required>
<label class="form-label" for="reference">{{ __('Payment reference') }}</label><input id="reference" name="reference" class="form-control mb-2" maxlength="100" required>
<button class="btn btn-primary w-100">{{ __('Record payment') }}</button></form>
<a class="btn btn-outline-secondary w-100 mt-2" href="{{ route('library.ledger.credit',$doc->id) }}">{{ __('Raise credit note') }}</a>
</div></div>
@endif
@if($doc->status !== 'void' && !$doc->export_batch)
<div class="card border-danger"><div class="card-header">{{ __('Void') }}</div><div class="card-body"><form method="post" action="{{ route('library.ledger.void',$doc->id) }}" onsubmit="return confirm('{{ __('Void this document?') }}')">@csrf<label class="form-label" for="void_reason">{{ __('Reason') }}</label><input id="void_reason" name="void_reason" class="form-control mb-2" maxlength="255" required><button class="btn btn-outline-danger w-100">{{ __('Void document') }}</button></form></div></div>
@endif
</div></div>
</div>
@endsection
