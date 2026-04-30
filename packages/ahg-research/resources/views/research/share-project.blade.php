{{-- Share Project --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Share Project')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Share</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-share-alt text-primary me-2"></i>Share Project</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<div class="card">
    <div class="card-body">
        @if(!empty($project->share_token))
            <div class="mb-3">
                <label class="form-label fw-bold">{{ __('Share URL') }}</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="shareUrl" value="{{ url('/research/shared/' . $project->share_token) }}" readonly>
                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('shareUrl').value); this.innerHTML='<i class=\'fas fa-check\'></i> Copied'; setTimeout(function(){ document.querySelector('#shareUrl + button').innerHTML='<i class=\'fas fa-copy\'></i> Copy'; }, 2000);">
                        <i class="fas fa-copy"></i> {{ __('Copy') }}
                    </button>
                </div>
                <small class="text-muted">{{ __('Anyone with this link can view the project.') }}</small>
            </div>
        @else
            <p class="text-muted mb-3">No share link has been generated for this project yet.</p>
            <form method="POST">
                @csrf
                <input type="hidden" name="form_action" value="generate_token">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-link me-1"></i>{{ __('Generate Share Link') }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
