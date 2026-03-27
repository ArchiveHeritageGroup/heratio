@extends('theme::layouts.1col')

@section('title', __('Data Quality'))

@section('content')

@php
$qualityScore = $qualityScore ?? 0;
$totalObjects = $totalObjects ?? 0;
$missingTitles = $missingTitles ?? 0;
$missingDates = $missingDates ?? 0;
$missingRepository = $missingRepository ?? 0;
$missingDigitalObjects = $missingDigitalObjects ?? 0;
@endphp

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ahgspectrum.dashboard') }}">Spectrum</a></li>
        <li class="breadcrumb-item active">{{ __('Data Quality') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2 mb-0"><i class="fas fa-chart-line text-primary me-2"></i>{{ __('Data Quality Dashboard') }}</h1>
    <a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100 {{ $qualityScore >= 80 ? 'border-success' : ($qualityScore >= 50 ? 'border-warning' : 'border-danger') }}">
            <div class="card-body text-center">
                <h1 class="display-3 {{ $qualityScore >= 80 ? 'text-success' : ($qualityScore >= 50 ? 'text-warning' : 'text-danger') }}">
                    {{ $qualityScore }}%
                </h1>
                <h5>{{ __('Overall Quality Score') }}</h5>
                <p class="text-muted">{{ __('Based on metadata completeness') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>{{ __('Collection Overview') }}</h5></div>
            <div class="card-body">
                <h2 class="text-primary">{{ number_format($totalObjects) }}</h2>
                <p class="text-muted mb-0">{{ __('Total Objects in Collection') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card h-100 {{ $missingTitles == 0 ? 'border-success' : 'border-danger' }}">
            <div class="card-body text-center">
                <h3 class="{{ $missingTitles == 0 ? 'text-success' : 'text-danger' }}">{{ number_format($missingTitles) }}</h3>
                <p class="mb-0"><i class="fas fa-heading me-1"></i>{{ __('Missing Titles') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card h-100 {{ $missingDates == 0 ? 'border-success' : 'border-warning' }}">
            <div class="card-body text-center">
                <h3 class="{{ $missingDates == 0 ? 'text-success' : 'text-warning' }}">{{ number_format($missingDates) }}</h3>
                <p class="mb-0"><i class="fas fa-calendar me-1"></i>{{ __('Missing Dates') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card h-100 {{ $missingRepository == 0 ? 'border-success' : 'border-warning' }}">
            <div class="card-body text-center">
                <h3 class="{{ $missingRepository == 0 ? 'text-success' : 'text-warning' }}">{{ number_format($missingRepository) }}</h3>
                <p class="mb-0"><i class="fas fa-building me-1"></i>{{ __('Missing Repository') }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card h-100 {{ $missingDigitalObjects == 0 ? 'border-success' : 'border-info' }}">
            <div class="card-body text-center">
                <h3 class="{{ $missingDigitalObjects == 0 ? 'text-success' : 'text-info' }}">{{ number_format($missingDigitalObjects) }}</h3>
                <p class="mb-0"><i class="fas fa-image me-1"></i>{{ __('No Digital Objects') }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Recommendations') }}</h5></div>
    <div class="card-body">
        <ul class="list-group list-group-flush">
            @if($missingTitles > 0)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas fa-exclamation-triangle text-danger me-2"></i>{{ __('Add titles to :count objects', ['count' => $missingTitles]) }}</span>
                <span class="badge bg-danger">{{ __('High Priority') }}</span>
            </li>
            @endif
            @if($missingDates > 0)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas fa-exclamation-circle text-warning me-2"></i>{{ __('Add date information to :count objects', ['count' => $missingDates]) }}</span>
                <span class="badge bg-warning text-dark">{{ __('Medium Priority') }}</span>
            </li>
            @endif
            @if($missingRepository > 0)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas fa-info-circle text-warning me-2"></i>{{ __('Assign repository to :count objects', ['count' => $missingRepository]) }}</span>
                <span class="badge bg-warning text-dark">{{ __('Medium Priority') }}</span>
            </li>
            @endif
            @if($missingDigitalObjects > 0)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas fa-image text-info me-2"></i>{{ __('Consider adding digital objects to :count records', ['count' => $missingDigitalObjects]) }}</span>
                <span class="badge bg-info">{{ __('Low Priority') }}</span>
            </li>
            @endif
            @if($qualityScore == 100)
            <li class="list-group-item text-success">
                <i class="fas fa-check-circle me-2"></i>{{ __('Excellent! All quality checks passed.') }}
            </li>
            @endif
        </ul>
    </div>
</div>

@endsection
