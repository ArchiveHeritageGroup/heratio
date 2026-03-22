{{-- View Assertion - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title', 'View Assertion')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Assertion</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-gavel text-primary me-2"></i>Assertion</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Claim</div><div class="card-body">
    <h5>{{ e($assertion->claim ?? '') }}</h5>
    <span class="badge bg-{{ match($assertion->status ?? '') { 'approved' => 'success', 'rejected' => 'danger', 'flagged' => 'warning', default => 'secondary' } }}">{{ ucfirst($assertion->status ?? 'pending') }}</span>
    <span class="badge bg-info ms-2">Confidence: {{ number_format(($assertion->confidence ?? 0) * 100) }}%</span>
</div></div>
@if($assertion->evidence ?? false)
<div class="card mb-4"><div class="card-header">Evidence</div><div class="card-body">{{ e($assertion->evidence) }}</div></div>
@endif
@if($assertion->reasoning ?? false)
<div class="card mb-4"><div class="card-header">Reasoning</div><div class="card-body">{{ e($assertion->reasoning) }}</div></div>
@endif
</div><div class="col-md-4">
<div class="card mb-4"><div class="card-header"><h6 class="mb-0">Details</h6></div><div class="card-body small">
    <dl class="row mb-0">
        <dt class="col-5">Researcher</dt><dd class="col-7">{{ e(($assertion->first_name ?? '') . ' ' . ($assertion->last_name ?? '')) }}</dd>
        <dt class="col-5">Source</dt><dd class="col-7">{{ e($assertion->source_title ?? '-') }}</dd>
        <dt class="col-5">Created</dt><dd class="col-7">{{ $assertion->created_at ?? '' }}</dd>
        <dt class="col-5">Updated</dt><dd class="col-7">{{ $assertion->updated_at ?? '' }}</dd>
    </dl>
</div></div>
@if(($assertion->status ?? '') === 'pending')
<div class="card"><div class="card-header"><h6 class="mb-0">Actions</h6></div><div class="card-body d-flex flex-wrap gap-2">
    <form method="POST" class="d-inline">@csrf <input type="hidden" name="assertion_id" value="{{ $assertion->id ?? 0 }}"><button type="submit" name="action" value="approve" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Approve</button></form>
    <form method="POST" class="d-inline">@csrf <input type="hidden" name="assertion_id" value="{{ $assertion->id ?? 0 }}"><button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"><i class="fas fa-times me-1"></i>Reject</button></form>
    <form method="POST" class="d-inline">@csrf <input type="hidden" name="assertion_id" value="{{ $assertion->id ?? 0 }}"><button type="submit" name="action" value="flag" class="btn btn-warning btn-sm"><i class="fas fa-flag me-1"></i>Flag</button></form>
</div></div>
@endif
</div></div>
@endsection