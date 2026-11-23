@extends('theme::layouts.1col')
@section('title', __('Aged creditors'))
@section('content')
@php $m = fn($c) => number_format($c/100,2); $totals = []; foreach ($rows as $r) { foreach (['current','d30','d60','d90','over90','total'] as $k) { $totals[$r['currency']][$k] = ($totals[$r['currency']][$k] ?? 0) + $r[$k]; } } @endphp
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-hourglass-half me-2"></i>{{ __('Aged creditors') }}</h1>
@include('ahg-library::ledger._nav')
<form method="get" class="row g-2 mb-3"><div class="col-auto"><label class="col-form-label" for="as_of">{{ __('As at') }}</label></div><div class="col-auto"><input type="date" id="as_of" name="as_of" class="form-control" value="{{ $asOf }}"></div><div class="col-auto"><button class="btn btn-primary">{{ __('Show') }}</button></div></form>
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>{{ __('Supplier') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Not yet due') }}</th><th class="text-end">{{ __('1-30 days') }}</th><th class="text-end">{{ __('31-60 days') }}</th><th class="text-end">{{ __('61-90 days') }}</th><th class="text-end">{{ __('Over 90') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
<tbody>@forelse($rows as $r)<tr><td>{{ $r['vendor_name'] }}</td><td>{{ $r['currency'] }}</td>@foreach(['current','d30','d60','d90','over90','total'] as $k)<td class="text-end">{{ $m($r[$k]) }}</td>@endforeach</tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">{{ __('Nothing outstanding at this date.') }}</td></tr>@endforelse</tbody>
@if($totals)<tfoot>@foreach($totals as $cur=>$t)<tr><th>{{ __('Total') }}</th><th>{{ $cur }}</th>@foreach(['current','d30','d60','d90','over90','total'] as $k)<th class="text-end">{{ $m($t[$k]) }}</th>@endforeach</tr>@endforeach</tfoot>@endif
</table></div></div>
<p class="text-muted small mt-2">{{ __('Aged from the due date, or the invoice date when no due date was captured. A negative figure means the supplier owes the library. Figures are as at the end of the chosen date: payments reversed and documents voided after it still count, and invoices captured late appear at their invoice date.') }}</p>
</div>
@endsection
