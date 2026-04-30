{{-- Invite Collaborator --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Invite Collaborator')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Invite Collaborator</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-user-plus text-primary me-2"></i>{{ __('Invite Collaborator') }}</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">{{ __('Send Invitation') }}</h6></div>
            <div class="card-body">
                <form method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="{{ __('researcher@example.com') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Role') }}</label>
                        <select name="role" class="form-select">
                            <option value="contributor">{{ __('Contributor') }}</option>
                            <option value="reviewer">{{ __('Reviewer') }}</option>
                            <option value="editor">{{ __('Editor') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>{{ __('Invite') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">{{ __('Current Collaborators') }}</h6></div>
            <ul class="list-group list-group-flush">
                @forelse($collaborators ?? [] as $c)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ e(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) }}</span>
                    <span class="badge bg-secondary">{{ ucfirst($c->role ?? '') }}</span>
                </li>
                @empty
                <li class="list-group-item text-muted small">No collaborators yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
