{{-- Mint DOI --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', 'Mint DOI')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item active">Mint DOI</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-stamp text-primary me-2"></i>Mint DOI</h1>
    <a href="{{ route('research.viewProject', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

{{-- DOI Status --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('DOI Status') }}</h6></div>
    <div class="card-body">
        @if(!empty($currentDoi))
            <div class="alert alert-success mb-0">
                <strong>DOI:</strong> <a href="https://doi.org/{{ e($currentDoi) }}" target="_blank" rel="noopener">{{ e($currentDoi) }}</a>
                @if(!empty($doiMintedAt))
                    <br><small class="text-muted">Minted on: {{ e($doiMintedAt) }}</small>
                @endif
            </div>
        @else
            <p class="text-muted mb-0">No DOI has been minted for this project yet.</p>
        @endif
    </div>
</div>

{{-- Metadata form --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">{{ __('DOI Metadata') }}</h6></div>
    <div class="card-body">
        <form id="doiForm">
            <div class="mb-3">
                <label class="form-label">{{ __('Title') }}</label>
                <input type="text" name="title" class="form-control" value="{{ e($project->title ?? '') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Creator(s)') }}</label>
                <input type="text" name="creators" class="form-control" value="{{ e($creatorsString ?? '') }}" placeholder="{{ __('Comma-separated names') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Description') }}</label>
                <textarea name="description" class="form-control" rows="3">{{ e($project->description ?? '') }}</textarea>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">{{ __('Year') }}</label>
                    <input type="text" name="year" class="form-control" value="{{ date('Y') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Resource Type') }}</label>
                    <select name="resource_type" class="form-select">
                        <option value="Dataset">{{ __('Dataset') }}</option>
                        <option value="Collection">{{ __('Collection') }}</option>
                        <option value="Text">{{ __('Text') }}</option>
                        <option value="Other">{{ __('Other') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Publisher') }}</label>
                    <input type="text" name="publisher" class="form-control" value="">
                </div>
            </div>
            <button type="button" id="mintDoiBtn" class="btn btn-primary btn-sm">
                <i class="fas fa-stamp me-1"></i>{{ !empty($currentDoi) ? 'Update DOI' : 'Mint DOI' }}
            </button>
        </form>
        <div id="doiResult" class="mt-3 d-none"></div>
    </div>
</div>

<script>
document.getElementById('mintDoiBtn')?.addEventListener('click', function() {
    var form = document.getElementById('doiForm');
    var formData = new FormData(form);
    var resultDiv = document.getElementById('doiResult');

    fetch(window.location.pathname, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        resultDiv.classList.remove('d-none');
        if (data.success) {
            resultDiv.innerHTML = '<div class="alert alert-success">' + (data.message || 'DOI minted successfully.') + '</div>';
        } else {
            resultDiv.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed to mint DOI.') + '</div>';
        }
    })
    .catch(function(err) {
        resultDiv.classList.remove('d-none');
        resultDiv.innerHTML = '<div class="alert alert-danger">An error occurred while minting the DOI.</div>';
    });
});
</script>
@endsection
