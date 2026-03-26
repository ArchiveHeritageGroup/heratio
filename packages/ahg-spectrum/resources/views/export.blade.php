@extends('theme::layouts.1col')

@section('title', __('Spectrum Data Export'))

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ahgspectrum.dashboard') }}">{{ __('Spectrum') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Export') }}</li>
    </ol>
</nav>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="fas fa-download text-primary me-2"></i>{{ __('Spectrum Data Export') }}</h1>
    <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

@if($identifier ?? null)
<div class="alert alert-info">{{ __('Exporting data for:') }} <strong>{{ $identifier }}</strong></div>
@endif

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-truck me-2"></i>{{ __('Movements') }}</h5></div>
            <div class="card-body"><h3>{{ count($movements ?? []) }}</h3><p class="text-muted">{{ __('records available') }}</p></div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Condition Checks') }}</h5></div>
            <div class="card-body"><h3>{{ count($conditions ?? []) }}</h3><p class="text-muted">{{ __('records available') }}</p></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning"><h5 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>{{ __('Valuations') }}</h5></div>
            <div class="card-body"><h3>{{ count($valuations ?? []) }}</h3><p class="text-muted">{{ __('records available') }}</p></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>{{ __('Loans In') }}</h5></div>
            <div class="card-body"><h3>{{ count($loansIn ?? []) }}</h3><p class="text-muted">{{ __('records available') }}</p></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-secondary text-white"><h5 class="mb-0"><i class="fas fa-sign-out-alt me-2"></i>{{ __('Loans Out') }}</h5></div>
            <div class="card-body"><h3>{{ count($loansOut ?? []) }}</h3><p class="text-muted">{{ __('records available') }}</p></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('Export Options') }}</h5></div>
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            @if($slug ?? null)
            <input type="hidden" name="slug" value="{{ $slug }}">
            @endif
            <input type="hidden" name="download" value="1">

            <div class="col-md-4">
                <label class="form-label">{{ __('Export Type') }}</label>
                <select name="type" class="form-select">
                    <option value="movement">{{ __('Movements') }} ({{ count($movements ?? []) }})</option>
                    <option value="condition">{{ __('Condition Checks') }} ({{ count($conditions ?? []) }})</option>
                    <option value="valuation">{{ __('Valuations') }} ({{ count($valuations ?? []) }})</option>
                    <option value="loan">{{ __('Loans') }} ({{ count($loansIn ?? []) + count($loansOut ?? []) }})</option>
                    <option value="workflow">{{ __('Workflow History') }}</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ __('Format') }}</label>
                <select name="format" class="form-select">
                    <option value="csv">CSV</option>
                    <option value="json">JSON</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-download me-2"></i>{{ __('Download') }}
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
