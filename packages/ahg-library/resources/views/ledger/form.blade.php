@extends('theme::layouts.1col')
@section('title', $doc ? __('Edit draft invoice') : __('New supplier invoice'))
@section('content')
@php
  $rows = old('lines', $lines);
  $rows = array_values($rows);
  for ($i = 0; $i < 3; $i++) { $rows[] = ['order_line_id'=>'','description'=>'','quantity'=>'','unit_price'=>'','tax_code'=>'']; }
@endphp
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-receipt me-2"></i>{{ $doc ? __('Edit draft invoice') : __('New supplier invoice') }}</h1>
@include('ahg-library::ledger._nav')
@unless($order)
<form method="get" class="card card-body mb-3"><label class="form-label" for="order_id">{{ __('Purchase order') }}</label><div class="d-flex gap-2"><select id="order_id" name="order_id" class="form-select" required><option value="">{{ __('Select an order') }}</option>@foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->order_number }} - {{ $o->vendor_name }}</option>@endforeach</select><button class="btn btn-primary">{{ __('Continue') }}</button></div></form>
@else
@if(!count($taxCodes))<div class="alert alert-warning">{{ __('No tax rates are loaded yet. An administrator must add them before an invoice can be posted.') }}</div>@endif
<form method="post" action="{{ $doc ? route('library.ledger.update',$doc->id) : route('library.ledger.store') }}">
@csrf @if($doc) @method('PUT') @endif
<input type="hidden" name="order_id" value="{{ $order->id }}">
<div class="card card-body mb-3"><div class="row g-3">
  <div class="col-md-4"><label class="form-label">{{ __('Order') }}</label><input class="form-control" value="{{ $order->order_number }} ({{ $order->currency }})" readonly></div>
  <div class="col-md-4"><label class="form-label" for="vendor_id">{{ __('Supplier') }}</label><select id="vendor_id" name="vendor_id" class="form-select" required><option value="">{{ __('Select') }}</option>@foreach($vendors as $v)<option value="{{ $v->id }}" @selected((int)old('vendor_id',$doc->vendor_id ?? $order->vendor_id)===(int)$v->id)>{{ $v->name }}</option>@endforeach</select></div>
  <div class="col-md-4"><label class="form-label" for="supplier_reference">{{ __('Supplier invoice number') }}</label><input id="supplier_reference" name="supplier_reference" class="form-control" maxlength="100" required value="{{ old('supplier_reference',$doc->supplier_reference ?? '') }}"></div>
  <div class="col-md-3"><label class="form-label" for="document_date">{{ __('Invoice date') }}</label><input type="date" id="document_date" name="document_date" class="form-control" required value="{{ old('document_date',$doc->document_date ?? now()->toDateString()) }}"></div>
  <div class="col-md-3"><label class="form-label" for="due_date">{{ __('Due date') }}</label><input type="date" id="due_date" name="due_date" class="form-control" value="{{ old('due_date',$doc->due_date ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label" for="notes">{{ __('Notes') }}</label><input id="notes" name="notes" class="form-control" maxlength="2000" value="{{ old('notes',$doc->notes ?? '') }}"></div>
</div></div>
<div class="card mb-3"><div class="card-header">{{ __('Lines (amounts before tax)') }}</div><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>{{ __('Description') }}</th><th style="width:7rem">{{ __('Quantity') }}</th><th style="width:9rem">{{ __('Unit price') }}</th><th style="width:13rem">{{ __('Tax code') }}</th></tr></thead>
<tbody>@foreach($rows as $i=>$r)<tr>
  <td><input type="hidden" name="lines[{{ $i }}][order_line_id]" value="{{ $r['order_line_id'] ?? '' }}"><input aria-label="{{ __('Description') }}" name="lines[{{ $i }}][description]" class="form-control form-control-sm" maxlength="500" value="{{ $r['description'] ?? '' }}"></td>
  <td><input aria-label="{{ __('Quantity') }}" type="number" min="1" max="65535" name="lines[{{ $i }}][quantity]" class="form-control form-control-sm" value="{{ $r['quantity'] ?? '' }}"></td>
  <td><input aria-label="{{ __('Unit price') }}" inputmode="decimal" name="lines[{{ $i }}][unit_price]" class="form-control form-control-sm" value="{{ $r['unit_price'] ?? '' }}"></td>
  <td><select aria-label="{{ __('Tax code') }}" name="lines[{{ $i }}][tax_code]" class="form-select form-select-sm"><option value="">{{ __('Select') }}</option>@foreach($taxCodes as $t)<option value="{{ $t->tax_code }}" @selected(($r['tax_code'] ?? '')===$t->tax_code)>{{ $t->tax_code }} - {{ $t->label }}</option>@endforeach</select></td>
</tr>@endforeach</tbody></table></div></div>
<button class="btn btn-primary">{{ __('Save draft') }}</button>
</form>
@endunless
</div>
@endsection
