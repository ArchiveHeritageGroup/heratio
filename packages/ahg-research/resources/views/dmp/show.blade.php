{{-- Data Management Plan (DMP) Builder - read-only assembled plan (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Data Management Plan'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.dmp.index', $project->id ?? 0) }}">{{ __('Data Management Plans') }}</a></li>
        <li class="breadcrumb-item active">{{ e($plan['title']) }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

@php
    $st = $plan['status'] ?? 'draft';
    $badge = match($st) { 'published' => 'success', 'approved' => 'primary', 'in_review' => 'info', 'superseded' => 'dark', default => 'secondary' };
@endphp

<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <h1 class="h3 mb-1"><i class="fas fa-database text-primary me-2"></i>{{ e($plan['title']) }}</h1>
        <div class="text-muted small">
            <span class="badge bg-{{ $badge }}">{{ e($statusOptions[$st] ?? ucfirst(str_replace('_',' ',$st))) }}</span>
            @if($plan['funder'] !== '')<span class="ms-2"><i class="fas fa-hand-holding-usd me-1"></i>{{ e($plan['funder']) }}</span>@endif
            @if($plan['funder_template'] !== '')<span class="ms-2 text-muted">{{ e($funderOptions[$plan['funder_template']] ?? $plan['funder_template']) }}</span>@endif
            <span class="ms-2"><i class="fas fa-language me-1"></i>{{ e($plan['language']) }}</span>
        </div>
        @if($plan['contact_name'] !== '' || $plan['contact_email'] !== '')
            <div class="text-muted small mt-1"><i class="fas fa-user me-1"></i>{{ e($plan['contact_name']) }} @if($plan['contact_email'] !== '')&lt;{{ e($plan['contact_email']) }}&gt;@endif</div>
        @endif
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('research.dmp.edit', [$project->id ?? 0, $plan['id']]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        <a href="{{ route('research.dmp.export', [$project->id ?? 0, $plan['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-code me-1"></i>{{ __('maDMP JSON') }}</a>
        <a href="{{ route('research.dmp.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

{{-- Completeness --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ __('Completeness') }}</strong>
            <span class="text-muted small">{{ $completeness['filled'] }}/{{ $completeness['total'] }} {{ __('sections answered') }}</span>
        </div>
        <div class="progress" role="progressbar" aria-valuenow="{{ $completeness['pct'] }}" aria-valuemin="0" aria-valuemax="100" style="height:22px;">
            <div class="progress-bar @if($completeness['pct']==100) bg-success @endif" style="width: {{ $completeness['pct'] }}%;">{{ $completeness['pct'] }}%</div>
        </div>
    </div>
</div>

{{-- Assembled sections --}}
@if(empty($sections))
    <div class="alert alert-info">{{ __('This plan has no sections yet.') }}</div>
@else
    @foreach($sections as $s)
    <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">{{ e($s['label']) }}</h6></div>
        <div class="card-body">
            @if(trim($s['body']) === '')
                <span class="text-muted fst-italic">{{ __('Not yet answered.') }}</span>
            @else
                <div style="white-space: pre-wrap;">{{ $s['body'] }}</div>
            @endif
        </div>
    </div>
    @endforeach
@endif
@endsection
