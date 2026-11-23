@extends('theme::layouts.1col')
@section('title', __('Budget match'))
@section('content')
@php $m = fn($c) => number_format($c/100,2); @endphp
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-balance-scale me-2"></i>{{ __('Invoices against budget commitments') }}</h1>
@include('ahg-library::ledger._nav')
<div class="card"><div class="table-responsive"><table class="table table-striped mb-0">
<thead><tr><th>{{ __('Budget') }}</th><th>{{ __('Fiscal year') }}</th><th>{{ __('Fund') }}</th><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Allocated') }}</th><th class="text-end">{{ __('Committed') }}</th><th class="text-end">{{ __('Received') }}</th><th class="text-end">{{ __('Invoiced net') }}</th><th class="text-end">{{ __('Received, not invoiced') }}</th><th class="text-end">{{ __('Committed, not invoiced') }}</th></tr></thead>
<tbody>@forelse($rows as $r)<tr><td>{{ $r->budget_code }}@if($r->excluded_orders)<span class="badge bg-warning text-dark ms-1" title="{{ __('Orders in another currency are not included') }}">{{ __(':n excluded',['n'=>$r->excluded_orders]) }}</span>@endif @if($r->unattributed_orders)<span class="badge bg-warning text-dark ms-1" title="{{ __('Orders under this code that could not be placed in one fiscal year are not included') }}">{{ __(':n unplaced',['n'=>$r->unattributed_orders]) }}</span>@endif</td><td>{{ $r->fiscal_year }}</td><td>{{ $r->fund_name }}</td><td>{{ $r->currency }}</td><td class="text-end">{{ $m($r->allocated) }}</td><td class="text-end">{{ $m($r->committed) }}</td><td class="text-end">{{ $m($r->received) }}</td><td class="text-end">{{ $m($r->invoiced) }}</td><td class="text-end {{ $r->received_not_invoiced < 0 ? 'text-danger' : '' }}">{{ $m($r->received_not_invoiced) }}</td><td class="text-end {{ $r->commitment_not_invoiced < 0 ? 'text-danger' : '' }}">{{ $m($r->commitment_not_invoiced) }}</td></tr>@empty<tr><td colspan="10" class="text-center text-muted py-4">{{ __('No budgets.') }}</td></tr>@endforelse</tbody>
</table></div></div>
<p class="text-muted small mt-2">{{ __('All figures exclude tax. A red figure means more has been invoiced than was received or committed. When a code exists in several fiscal years, each order counts in the year of its order date. Orders that fit no single year are marked unplaced and left out.') }}</p>
</div>
@endsection
