@extends('theme::layouts.1col')

@section('title', __('Spectrum History Export'))

@section('content')
<h1 class="h3 mb-4"><i class="fas fa-download me-2"></i>{{ __('Spectrum History Export') }}</h1>

<p class="text-muted mb-4">Export Spectrum procedure histories for audit and compliance purposes.</p>

<div class="row">
@foreach($exportTypes as $type => $label)
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="fas fa-{{ match($type) {
                    'condition' => 'heartbeat',
                    'valuation' => 'coins',
                    'movement' => 'truck',
                    'loan' => 'handshake',
                    'workflow' => 'tasks',
                    default => 'file'
                } }} fa-3x text-primary mb-3"></i>
                <h5>{{ $label }}</h5>
            </div>
            <div class="card-footer">
                <div class="btn-group w-100">
                    <a href="{{ route('ahgspectrum.spectrum-export') }}?type={{ $type }}&format=csv&download=1" class="btn btn-outline-primary">
                        <i class="fas fa-file-csv me-1"></i>CSV
                    </a>
                    <a href="{{ route('ahgspectrum.spectrum-export') }}?type={{ $type }}&format=json&download=1" class="btn btn-outline-secondary">
                        <i class="fas fa-file-code me-1"></i>JSON
                    </a>
                </div>
            </div>
        </div>
    </div>
@endforeach
</div>

<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">{{ __('Export Notes') }}</h5></div>
    <div class="card-body">
        <ul class="mb-0">
            <li><strong>CSV</strong> - Compatible with Excel, suitable for AGSA audit submissions</li>
            <li><strong>JSON</strong> - Machine-readable, suitable for system integrations</li>
            <li>All exports include object titles and full history records</li>
            <li>Timestamps are in ISO 8601 format</li>
        </ul>
    </div>
</div>
@endsection
