{{-- View Reproduction - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reproductions'])@endsection
@section('title', 'Reproduction Request Details')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.reproductions') }}">Reproductions</a></li><li class="breadcrumb-item active">Details</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-copy text-primary me-2"></i>Reproduction Request #{{ $reproduction->id ?? '' }}</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Request Details</div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-4">Item Reference</dt><dd class="col-sm-8">{{ e($reproduction->item_reference ?? '') }}</dd>
        <dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $reproduction->reproduction_type ?? '')) }}</dd>
        <dt class="col-sm-4">Format</dt><dd class="col-sm-8">{{ strtoupper($reproduction->format ?? '') }}</dd>
        <dt class="col-sm-4">Quantity</dt><dd class="col-sm-8">{{ $reproduction->quantity ?? 1 }}</dd>
        <dt class="col-sm-4">Purpose</dt><dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $reproduction->purpose ?? '')) }}</dd>
        <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge bg-{{ match($reproduction->status ?? '') { 'completed' => 'success', 'in_progress' => 'primary', 'denied' => 'danger', default => 'warning' } }} fs-6">{{ ucfirst(str_replace('_', ' ', $reproduction->status ?? 'pending')) }}</span></dd>
        <dt class="col-sm-4">Submitted</dt><dd class="col-sm-8">{{ $reproduction->created_at ?? '' }}</dd>
    </dl>
    @if($reproduction->notes ?? false)<hr><h6>Special Instructions</h6><p class="mb-0">{{ e($reproduction->notes) }}</p>@endif
</div></div>
@if(!empty($reproduction->cost))
<div class="card mb-4"><div class="card-header">Cost Estimate</div><div class="card-body">
    <div class="row text-center"><div class="col"><h4>{{ $reproduction->currency ?? 'ZAR' }} {{ number_format($reproduction->cost ?? 0, 2) }}</h4><small class="text-muted">Estimated Cost</small></div>
    <div class="col"><span class="badge bg-{{ ($reproduction->payment_status ?? '') === 'paid' ? 'success' : 'warning' }} fs-6">{{ ucfirst($reproduction->payment_status ?? 'unpaid') }}</span></div></div>
</div></div>
@endif
</div><div class="col-md-4">
<div class="card mb-4"><div class="card-header"><h6 class="mb-0">Researcher</h6></div><div class="card-body small">
    <dl class="row mb-0"><dt class="col-5">Name</dt><dd class="col-7">{{ e(($reproduction->first_name ?? '') . ' ' . ($reproduction->last_name ?? '')) }}</dd><dt class="col-5">Email</dt><dd class="col-7">{{ e($reproduction->email ?? '') }}</dd></dl>
</div></div>
<div class="d-flex flex-column gap-2">
    <a href="{{ route('research.reproductions') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to List</a>
</div>
</div></div>
@endsection