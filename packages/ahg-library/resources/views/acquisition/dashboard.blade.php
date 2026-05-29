@extends('theme::layouts.1col')
@section('title', 'Acquisitions Dashboard')
@section('content')
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="mb-0"><i class="fas fa-chart-line me-2"></i>{{ __('Acquisitions Dashboard') }}</h1><div><a href="{{ route('library.acquisitions') }}" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('Orders') }}</a> <a href="{{ route('library.acquisition-order-create') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('New Order') }}</a></div></div>

<div class="row mb-3">
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Orders') }}</div><div class="h3 mb-0">{{ ($orders??collect())->count() }}</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Total Value') }}</div><div class="h3 mb-0">{{ number_format((float)($orders??collect())->sum('total_amount'),2) }}</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Budgets') }}</div><div class="h3 mb-0">{{ ($budgets??collect())->count() }}</div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div class="text-muted small">{{ __('Allocated') }}</div><div class="h3 mb-0">{{ number_format((float)($budgets??collect())->sum('allocated_amount'),2) }}</div></div></div></div>
</div>

<div class="row">
<div class="col-md-6">
<div class="card mb-3"><div class="card-header"><i class="fas fa-truck me-1"></i>{{ __('Orders by Vendor') }}</div><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>{{ __('Vendor') }}</th><th class="text-end">{{ __('Orders') }}</th><th class="text-end">{{ __('Value') }}</th></tr></thead><tbody>@forelse($byVendor??[] as $vendor=>$grp)<tr><td>{{ e($vendor) }}</td><td class="text-end">{{ $grp->count }}</td><td class="text-end">{{ number_format($grp->total,2) }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center py-3">{{ __('No orders.') }}</td></tr>@endforelse</tbody></table></div></div>

<div class="card mb-3"><div class="card-header"><i class="fas fa-tag me-1"></i>{{ __('Orders by Status') }}</div><div class="card-body">@forelse($statusCounts??[] as $status=>$count)<div class="d-flex justify-content-between border-bottom py-1"><span><span class="badge bg-secondary">{{ e($status) }}</span></span><span>{{ $count }}</span></div>@empty<div class="text-muted">{{ __('No orders.') }}</div>@endforelse</div></div>
</div>

<div class="col-md-6">
<div class="card mb-3"><div class="card-header"><i class="fas fa-wallet me-1"></i>{{ __('Budget Utilisation') }}</div><div class="card-body">@forelse($budgets??[] as $b)@php $alloc=(float)($b->allocated_amount??0); $committed=(float)($b->committed_amount??0); $spent=(float)($b->spent_amount??0); $pctS=$alloc>0?min(100,round($spent/$alloc*100)):0; $pctC=$alloc>0?min(100,round($committed/$alloc*100)):0; @endphp
<div class="mb-3"><div class="d-flex justify-content-between"><a href="{{ route('library.acquisition-budget',$b->id) }}"><strong>{{ e($b->fund_name??'') }}</strong></a><small class="text-muted">{{ number_format($spent,2) }} / {{ number_format($alloc,2) }} {{ e($b->currency??'') }}</small></div>
<div class="progress" style="height:18px"><div class="progress-bar {{ $pctS>=90?'bg-danger':($pctS>=70?'bg-warning':'bg-success') }}" style="width:{{ $pctS }}%" title="{{ __('Spent') }} {{ $pctS }}%">{{ $pctS }}%</div><div class="progress-bar bg-info" style="width:{{ max(0,$pctC-$pctS) }}%" title="{{ __('Committed') }} {{ $pctC }}%"></div></div></div>
@empty<div class="text-muted">{{ __('No budgets configured.') }}</div>@endforelse
<small class="text-muted"><span class="badge bg-success">&nbsp;</span> {{ __('Spent') }} &nbsp; <span class="badge bg-info">&nbsp;</span> {{ __('Committed') }}</small>
</div></div>
</div>
</div>
</div>
@endsection
