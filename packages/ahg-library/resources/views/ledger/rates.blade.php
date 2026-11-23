@extends('theme::layouts.1col')
@section('title', __('Tax rates'))
@section('content')
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-percent me-2"></i>{{ __('Tax rates') }}</h1>
@include('ahg-library::ledger._nav')
<p class="text-muted">{{ __('Load the rates for your market. A rate is never edited once in use: end it and add the new one, so posted documents keep the rate they were calculated with.') }}</p>
<div class="row"><div class="col-lg-8"><div class="card mb-3"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ __('Code') }}</th><th>{{ __('Label') }}</th><th class="text-end">{{ __('Rate %') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('End rate') }}</th></tr></thead>
<tbody>@forelse($rates as $r)<tr><td>{{ $r->tax_code }}</td><td>{{ $r->label }}</td><td class="text-end">{{ $r->rate_percent }}</td><td>{{ $r->valid_from }}</td><td>{{ $r->valid_to ?: '-' }}</td><td><form method="post" action="{{ route('library.ledger.rate-end',$r->id) }}" class="d-flex gap-1">@csrf<input type="date" aria-label="{{ __('End date') }}" name="valid_to" class="form-control form-control-sm" required><button class="btn btn-sm btn-outline-secondary">{{ __('End') }}</button></form></td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-4">{{ __('No tax rates loaded.') }}</td></tr>@endforelse</tbody></table></div></div></div>
<div class="col-lg-4">
<div class="card mb-3"><div class="card-header">{{ __('Add rate') }}</div><div class="card-body"><form method="post" action="{{ route('library.ledger.rate-store') }}">@csrf
<label class="form-label" for="tax_code">{{ __('Code') }}</label><input id="tax_code" name="tax_code" class="form-control mb-2" maxlength="30" required value="{{ old('tax_code') }}">
<label class="form-label" for="label">{{ __('Label') }}</label><input id="label" name="label" class="form-control mb-2" maxlength="120" required value="{{ old('label') }}">
<label class="form-label" for="rate_percent">{{ __('Rate %') }}</label><input id="rate_percent" name="rate_percent" inputmode="decimal" class="form-control mb-2" required value="{{ old('rate_percent') }}">
<label class="form-label" for="valid_from">{{ __('Valid from') }}</label><input type="date" id="valid_from" name="valid_from" class="form-control mb-2" required value="{{ old('valid_from') }}">
<label class="form-label" for="valid_to">{{ __('Valid to (optional)') }}</label><input type="date" id="valid_to" name="valid_to" class="form-control mb-2" value="{{ old('valid_to') }}">
<button class="btn btn-primary w-100">{{ __('Add') }}</button></form></div></div>
<div class="card"><div class="card-header">{{ __('Rounding rule') }}</div><div class="card-body"><form method="post" action="{{ route('library.ledger.rounding') }}">@csrf<label class="visually-hidden" for="rounding">{{ __('Rounding rule') }}</label><select id="rounding" name="rounding" class="form-select mb-2">@foreach($roundingOptions as $c=>$l)<option value="{{ $c }}" @selected($rounding===$c)>{{ $l }}</option>@endforeach</select><button class="btn btn-primary w-100">{{ __('Save') }}</button></form></div></div>
</div></div>
</div>
@endsection
