@extends('theme::layouts.1col')

@section('title', __('Spectrum Data'))

@section('content')

<div class="d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-layer-group me-3 text-primary"></i>
    <div>
        <h1 class="mb-0">{{ __('Spectrum Data') }}</h1>
        <span class="text-muted">{{ $resource->title ?? $resource->slug ?? '' }}</span>
    </div>
</div>

<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ url('/informationobject/' . ($resource->slug ?? '')) }}">{{ $resource->title ?? $resource->slug ?? '' }}</a></li>
        <li class="breadcrumb-item active">{{ __('Spectrum Data') }}</li>
    </ol>
</nav>

<div class="row">
    <!-- Sidebar -->
    <div class="col-md-3">
        <!-- Quick Info Card -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Record Info') }}</h5>
            </div>
            <div class="card-body">
                <dl class="mb-0">
                    @if (!empty($resource->identifier))
                    <dt>{{ __('Identifier') }}</dt>
                    <dd>{{ $resource->identifier }}</dd>
                    @endif
                    <dt>{{ __('Title') }}</dt>
                    <dd>{{ $resource->title ?? $resource->slug ?? '' }}</dd>
                </dl>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <a href="{{ route('ahgspectrum.label') }}?slug={{ $resource->slug ?? '' }}">
                        <i class="fas fa-barcode me-2"></i>{{ __('Print Labels') }}
                    </a>
                </li>
                <li class="list-group-item">
                    <a href="{{ route('ahgspectrum.condition-photos') }}?slug={{ $resource->slug ?? '' }}">
                        <i class="fas fa-camera me-2"></i>{{ __('Condition Photos') }}
                    </a>
                </li>
                <li class="list-group-item">
                    <a href="{{ route('ahgspectrum.grap-dashboard') }}?slug={{ $resource->slug ?? '' }}">
                        <i class="fas fa-file-invoice-dollar me-2"></i>{{ __('Heritage Assets') }}
                    </a>
                </li>
            </ul>
        </div>

        <!-- Back Link -->
        <a href="{{ url('/informationobject/' . ($resource->slug ?? '')) }}" class="btn btn-outline-secondary w-100">
            <i class="fas fa-arrow-left me-2"></i>{{ __('Back to record') }}
        </a>
    </div>

    <!-- Main Content -->
    <div class="col-md-9">
        <!-- Spectrum 5.1 Procedures Grid -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Spectrum 5.1 Procedures') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">{{ __('Manage collections management procedures according to Spectrum 5.1 standard.') }}</p>

                <div class="row g-3">
                    @php
                    $procedures = $procedures ?? [];
                    $colors = ['primary', 'success', 'info', 'warning', 'secondary', 'dark'];
                    $i = 0;
                    @endphp
                    @foreach ($procedures as $key => $proc)
                        @php $color = $colors[$i % count($colors)]; $i++; @endphp
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="card h-100 border-{{ $color }}">
                            <div class="card-body text-center p-3">
                                <i class="fas {{ $proc['icon'] }} fa-2x mb-2 text-{{ $color }}"></i>
                                <h6 class="card-title mb-2">{{ $proc['label'] }}</h6>
                                <a href="{{ route('ahgspectrum.workflow') }}?slug={{ $resource->slug ?? '' }}&procedure_type={{ $key }}"
                                   class="btn btn-sm btn-outline-{{ $color }}">
                                    <i class="fas fa-cog me-1"></i>{{ __('Manage') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent Procedure Activity') }}</h5>
            </div>
            <div class="card-body">
                @php
                $recentHistory = \Illuminate\Support\Facades\DB::table('spectrum_procedure_history')
                    ->where('object_id', $resource->id ?? 0)
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
                @endphp
                @if ($recentHistory->isEmpty())
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('No procedure history recorded yet.') }}</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($recentHistory as $entry)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <strong>{{ ucfirst(str_replace('_', ' ', $entry->procedure_type)) }}</strong>
                                <span class="text-muted"> - {{ $entry->action }}</span>
                            </span>
                            <small class="text-muted">{{ date('Y-m-d H:i', strtotime($entry->created_at)) }}</small>
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <!-- Export Options -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-download me-2"></i>{{ __('Export Options') }}</h5>
            </div>
            <div class="card-body">
                <div class="btn-group flex-wrap" role="group">
                    <a href="{{ route('ahgspectrum.export') }}?slug={{ $resource->slug ?? '' }}&format=pdf" class="btn btn-outline-danger">
                        <i class="fas fa-file-pdf me-1"></i>{{ __('Export PDF') }}
                    </a>
                    <a href="{{ route('ahgspectrum.export') }}?slug={{ $resource->slug ?? '' }}&format=csv" class="btn btn-outline-success">
                        <i class="fas fa-file-csv me-1"></i>{{ __('Export CSV') }}
                    </a>
                    <a href="{{ route('ahgspectrum.export') }}?slug={{ $resource->slug ?? '' }}&format=json" class="btn btn-outline-primary">
                        <i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
