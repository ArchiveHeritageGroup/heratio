@extends('theme::layouts.1col')
@section('title', 'Budgets')
@section('content')
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="mb-0"><i class="fas fa-wallet me-2"></i>{{ __('Acquisition Budgets') }}</h1><div><a href="{{ route('library.acquisitions') }}" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('Orders') }}</a> <a href="{{ route('library.acquisition-budget-create') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('New Budget') }}</a></div></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card"><div class="card-body p-0"><table class="table table-striped table-hover mb-0"><thead><tr><th>{{ __('Fund') }}</th><th>{{ __('Code') }}</th><th>{{ __('Year') }}</th><th class="text-end">{{ __('Allocated') }}</th><th class="text-end">{{ __('Committed') }}</th><th class="text-end">{{ __('Spent') }}</th><th class="text-end">{{ __('Available') }}</th></tr></thead><tbody>@forelse($budgets??[] as $b)@php $alloc=(float)($b->allocated_amount??0); $committed=(float)($b->committed_amount??0); $spent=(float)($b->spent_amount??0); @endphp<tr style="cursor:pointer" onclick="window.location='{{ route('library.acquisition-budget',$b->id) }}'"><td><strong>{{ e($b->fund_name??'') }}</strong></td><td>{{ e($b->budget_code??'') }}</td><td>{{ e($b->fiscal_year??'') }}</td><td class="text-end">{{ number_format($alloc,2) }}</td><td class="text-end">{{ number_format($committed,2) }}</td><td class="text-end">{{ number_format($spent,2) }}</td><td class="text-end"><strong>{{ number_format($alloc-$committed,2) }}</strong></td></tr>@empty<tr><td colspan="7" class="text-muted text-center py-3">{{ __('No budgets.') }}</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
