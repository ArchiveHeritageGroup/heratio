{{-- RO-Crate --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'RO-Crate')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">RO-Crate</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-archive text-primary me-2"></i>RO-Crate</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" disabled><i class="fas fa-download me-1"></i>{{ __('Download RO-Crate') }}</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

{{-- Manifest JSON --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('RO-Crate Manifest (ro-crate-metadata.json)') }}</h6></div>
    <div class="card-body">
        <pre class="mb-0" style="max-height:400px; overflow:auto;"><code>{{ json_encode($manifest ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
    </div>
</div>

{{-- Collaborators --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('Collaborators') }}</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($collaborators ?? [] as $c)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ e(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) }}</span>
            <span class="badge bg-secondary">{{ ucfirst($c->role ?? '') }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No collaborators.</li>
        @endforelse
    </ul>
</div>

{{-- Included resources --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('Included Resources') }}</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($resources ?? [] as $r)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><i class="fas fa-file me-2"></i>{{ e($r->name ?? $r->title ?? '') }}</span>
            <span class="badge bg-secondary">{{ $r->type ?? '' }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No resources included.</li>
        @endforelse
    </ul>
</div>
@endsection
