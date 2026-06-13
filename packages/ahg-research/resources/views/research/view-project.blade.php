@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection

@section('content')
@php
    $isOwner = ($project->owner_id ?? 0) == ($researcher->id ?? 0);
@endphp

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">Projects</a></li>
        <li class="breadcrumb-item active">{{ e($project->title) }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h2">{{ e($project->title) }}</h1>
        <span class="badge bg-{{ match($project->status ?? '') { 'active' => 'success', 'planning' => 'info', 'on_hold' => 'warning', 'completed' => 'secondary', default => 'dark' } }} me-2">{{ ucfirst($project->status ?? 'active') }}</span>
        <span class="badge bg-light text-dark">{{ ucfirst($project->project_type ?? 'personal') }}</span>
    </div>
    @if($isOwner)
    <div class="d-flex gap-2">
        <a href="{{ route('research.viewProject', $project->id) }}?action=edit" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
        </a>
    </div>
    @endif
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- Research OS #1225 - the per-project Command Centre journey --}}
@include('research::research._command-centre')

// ... rest of the file unchanged ...

@include('research::research.levels_guide')

@endsection
