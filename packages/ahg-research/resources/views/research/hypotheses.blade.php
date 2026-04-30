{{-- Hypotheses --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Hypotheses')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Hypotheses</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-lightbulb text-primary me-2"></i>Hypotheses</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#createHypothesisForm"><i class="fas fa-plus me-1"></i>New Hypothesis</button>
        <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

{{-- Create hypothesis form (collapsed) --}}
<div class="collapse mb-4" id="createHypothesisForm">
    <div class="card">
        <div class="card-header"><h6 class="mb-0">{{ __('Create Hypothesis') }}</h6></div>
        <div class="card-body">
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="mb-3">
                    <label class="form-label">Statement <span class="text-danger">*</span></label>
                    <textarea name="statement" class="form-control" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Tags') }}</label>
                    <input type="text" name="tags" class="form-control" placeholder="{{ __('Comma-separated tags') }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Create</button>
            </form>
        </div>
    </div>
</div>

{{-- Hypotheses list --}}
@if(!empty($hypotheses) && count($hypotheses) > 0)
<div class="row">
    @foreach($hypotheses as $h)
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ e(Str::limit($h->statement ?? 'Untitled', 80)) }}</strong>
                <span class="badge bg-{{ match($h->status ?? '') { 'supported' => 'success', 'refuted' => 'danger', 'testing' => 'info', default => 'warning' } }}">{{ ucfirst($h->status ?? 'proposed') }}</span>
            </div>
            <div class="card-body">
                <p class="mb-0">{{ e($h->statement ?? '') }}</p>
                @if($h->tags ?? null)
                <p class="text-muted small mt-1">Tags: {{ e($h->tags) }}</p>
                @endif
            </div>
            <div class="card-footer text-muted small">
                Created: {{ $h->created_at ?? '' }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="alert alert-info">No hypotheses yet. Create one to get started.</div>
@endif
@endsection
