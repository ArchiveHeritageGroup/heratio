@extends('theme::layouts.1col')

@section('title', __('Heritage Assets'))

@section('content')

<h1><i class="fas fa-landmark"></i> {{ __('Heritage Assets') }}</h1>

<div class="heritage-assets-dashboard">
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        {{ __('Heritage Assets: Financial reporting for cultural property, museum collections, and archival materials per international accounting standards.') }}
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h2 class="mb-0">{{ number_format($totalAssets ?? 0) }}</h2>
                    <p class="mb-0">{{ __('Total Heritage Assets') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h2 class="mb-0">{{ number_format($valuedAssets ?? 0) }}</h2>
                    <p class="mb-0">{{ __('Valued Assets') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-warning text-dark">
                <div class="card-body">
                    <h2 class="mb-0">{{ number_format($pendingValuation ?? 0) }}</h2>
                    <p class="mb-0">{{ __('Pending Valuation') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h2 class="mb-0">{{ number_format($totalValue ?? 0, 2) }}</h2>
                    <p class="mb-0">{{ __('Total Value') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Compliance Status -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Compliance Checklist') }}</h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Asset Register Complete') }}
                        <span class="badge bg-{{ ($assetRegisterComplete ?? false) ? 'success' : 'danger' }}">
                            {{ ($assetRegisterComplete ?? false) ? __('Yes') : __('No') }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Valuations Current (< 5 years)') }}
                        <span class="badge bg-{{ ($valuationsCurrent ?? false) ? 'success' : 'warning' }}">
                            {{ ($valuationsCurrent ?? false) ? __('Yes') : __('Review Needed') }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Condition Assessments') }}
                        <span class="badge bg-{{ ($conditionComplete ?? false) ? 'success' : 'warning' }}">
                            {{ ($conditionComplete ?? false) ? __('Complete') : __('Incomplete') }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Depreciation Recorded') }}
                        <span class="badge bg-{{ ($depreciationRecorded ?? false) ? 'success' : 'secondary' }}">
                            {{ ($depreciationRecorded ?? false) ? __('Yes') : __('N/A - Heritage') }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Insurance Valuations') }}
                        <span class="badge bg-{{ ($insuranceComplete ?? false) ? 'success' : 'warning' }}">
                            {{ ($insuranceComplete ?? false) ? __('Complete') : __('Incomplete') }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Asset Categories') }}</h5>
                </div>
                <div class="card-body">
                    @if(!empty($categories))
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Category') }}</th>
                                <th class="text-end">{{ __('Count') }}</th>
                                <th class="text-end">{{ __('Value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categories as $cat)
                            <tr>
                                <td>{{ $cat['name'] ?? 'Uncategorized' }}</td>
                                <td class="text-end">{{ number_format($cat['count'] ?? 0) }}</td>
                                <td class="text-end">{{ number_format($cat['value'] ?? 0, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <p class="text-muted">{{ __('No category data available.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-file-export me-2"></i>{{ __('Export Heritage Assets Report') }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <a href="{{ route('ahgspectrum.grap-dashboard', ['slug' => $resource->slug ?? '', 'export' => 'csv']) }}" class="btn btn-outline-primary w-100">
                        <i class="fas fa-file-csv me-2"></i>{{ __('Export to CSV') }}
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('ahgspectrum.grap-dashboard', ['slug' => $resource->slug ?? '', 'export' => 'xlsx']) }}" class="btn btn-outline-success w-100">
                        <i class="fas fa-file-excel me-2"></i>{{ __('Export to Excel') }}
                    </a>
                </div>
                <div class="col-md-4">
                    <a href="{{ route('ahgspectrum.grap-dashboard', ['slug' => $resource->slug ?? '', 'export' => 'pdf']) }}" class="btn btn-outline-danger w-100">
                        <i class="fas fa-file-pdf me-2"></i>{{ __('Export to PDF') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.heritage-assets-dashboard .card {
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.heritage-assets-dashboard .card-header {
    font-weight: bold;
}
</style>

@endsection
