@extends('theme::layouts.1col')
@section('title', 'Budget')
@section('content')
@php
    $alloc     = (float)($budget->allocated_amount??0);
    $committed = (float)($budget->committed_amount??0);
    $spent     = (float)($budget->spent_amount??0);
    $available = $alloc - $committed;
    $pctSpent  = $alloc>0 ? min(100, round($spent/$alloc*100)) : 0;
    $pctComm   = $alloc>0 ? min(100, round($committed/$alloc*100)) : 0;
@endphp
<div class="container py-4">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="mb-1"><i class="fas fa-wallet me-2"></i>{{ e($budget->fund_name??'') }}</h1><span class="text-muted">{{ e($budget->budget_code??'') }} &middot; {{ e($budget->fiscal_year??'') }}</span></div><div><a href="{{ route('library.acquisition-budget-edit',$budget->id) }}" class="btn btn-outline-secondary"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</a> <a href="{{ route('library.acquisition-budgets') }}" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('All Budgets') }}</a></div></div>

<div class="row mb-3">
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Allocated') }}</div><div class="h4 mb-0">{{ number_format($alloc,2) }}</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Committed') }}</div><div class="h4 mb-0 text-warning">{{ number_format($committed,2) }}</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Spent') }}</div><div class="h4 mb-0 text-danger">{{ number_format($spent,2) }}</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Available') }}</div><div class="h4 mb-0 {{ $available<0?'text-danger':'text-success' }}">{{ number_format($available,2) }}</div></div></div></div>
</div>

<div class="card"><div class="card-header"><i class="fas fa-chart-bar me-1"></i>{{ __('Utilisation') }} ({{ e($budget->currency??'') }})</div><div class="card-body">
<label class="form-label small mb-1">{{ __('Spent') }} ({{ $pctSpent }}%)</label>
<div class="progress mb-3" style="height:22px"><div class="progress-bar {{ $pctSpent>=90?'bg-danger':($pctSpent>=70?'bg-warning':'bg-success') }}" role="progressbar" style="width:{{ $pctSpent }}%">{{ number_format($spent,2) }}</div></div>
<label class="form-label small mb-1">{{ __('Committed') }} ({{ $pctComm }}%)</label>
<div class="progress" style="height:22px"><div class="progress-bar bg-info" role="progressbar" style="width:{{ $pctComm }}%">{{ number_format($committed,2) }}</div></div>
@if($budget->category ?? null)<div class="mt-3 text-muted small">{{ __('Category') }}: {{ e($budget->category) }} @if($budget->department ?? null)&middot; {{ __('Department') }}: {{ e($budget->department) }}@endif</div>@endif
@if($budget->notes ?? null)<div class="mt-2">{!! nl2br(e($budget->notes)) !!}</div>@endif
</div></div>
</div>
@endsection
