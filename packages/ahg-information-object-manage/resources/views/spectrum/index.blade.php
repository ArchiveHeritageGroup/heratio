@extends('theme::layouts.2col')
@section('title', __('Spectrum Data') . ' — ' . ($io->title ?? $io->slug))

@section('sidebar')
  {{-- Quick Info Card --}}
  <div class="card mb-3">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Record Info') }}</h5>
    </div>
    <div class="card-body">
      <dl class="mb-0">
        @if ($io->identifier ?? null)
        <dt>{{ __('Identifier') }}</dt>
        <dd>{{ $io->identifier }}</dd>
        @endif
        <dt>{{ __('Title') }}</dt>
        <dd>{{ $io->title ?? $io->slug }}</dd>
      </dl>
    </div>
  </div>

  {{-- Quick Actions Card --}}
  <div class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
    </div>
    <ul class="list-group list-group-flush">
      <li class="list-group-item">
        <a href="{{ url('/admin/spectrum/label?slug=' . $io->slug) }}">
          <i class="fas fa-barcode me-2"></i>{{ __('Print Labels') }}
        </a>
      </li>
      <li class="list-group-item">
        <a href="{{ url('/admin/spectrum/condition-photos?slug=' . $io->slug) }}">
          <i class="fas fa-camera me-2"></i>{{ __('Condition Photos') }}
        </a>
      </li>
      <li class="list-group-item">
        <a href="{{ url('/admin/spectrum/grap-dashboard?slug=' . $io->slug) }}">
          <i class="fas fa-file-invoice-dollar me-2"></i>{{ __('Heritage Assets') }}
        </a>
      </li>
    </ul>
  </div>

  {{-- Back Link --}}
  <a href="{{ url('/' . $io->slug) }}" class="btn btn-outline-secondary w-100">
    <i class="fas fa-arrow-left me-2"></i>{{ __('Back to record') }}
  </a>
@endsection

@section('content')
  <div class="d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-layer-group me-3 text-primary"></i>
    <div>
      <h1 class="mb-0">{{ __('Spectrum Data') }}</h1>
      <span class="text-muted">{{ $io->title ?? $io->slug }}</span>
    </div>
  </div>

  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ url('/' . $io->slug) }}">{{ $io->title ?? $io->slug }}</a></li>
      <li class="breadcrumb-item active">{{ __('Spectrum Data') }}</li>
    </ol>
  </nav>

  {{-- Spectrum 5.1 Procedures Grid --}}
  <div class="card mb-4">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Spectrum 5.1 Procedures') }}</h5>
    </div>
    <div class="card-body">
      <p class="text-muted mb-4">{{ __('Manage collections management procedures according to Spectrum 5.1 standard.') }}</p>

      @php
      $procedures = [
          'object_entry'    => ['icon' => 'fa-sign-in-alt',       'label' => 'Object Entry'],
          'acquisition'     => ['icon' => 'fa-handshake',         'label' => 'Acquisition'],
          'location'        => ['icon' => 'fa-map-marker-alt',    'label' => 'Location & Movement'],
          'inventory'       => ['icon' => 'fa-clipboard-list',    'label' => 'Inventory'],
          'cataloguing'     => ['icon' => 'fa-database',          'label' => 'Cataloguing'],
          'condition'       => ['icon' => 'fa-heartbeat',         'label' => 'Condition Check'],
          'conservation'    => ['icon' => 'fa-tools',             'label' => 'Conservation'],
          'risk'            => ['icon' => 'fa-exclamation-triangle','label' => 'Risk Management'],
          'insurance'       => ['icon' => 'fa-shield-alt',        'label' => 'Insurance'],
          'valuation'       => ['icon' => 'fa-dollar-sign',       'label' => 'Valuation'],
          'audit'           => ['icon' => 'fa-search',            'label' => 'Audit'],
          'rights'          => ['icon' => 'fa-gavel',             'label' => 'Rights Management'],
          'reproduction'    => ['icon' => 'fa-copy',              'label' => 'Reproduction'],
          'loan_in'         => ['icon' => 'fa-arrow-circle-down', 'label' => 'Loan In'],
          'loan_out'        => ['icon' => 'fa-arrow-circle-up',   'label' => 'Loan Out'],
          'loss'            => ['icon' => 'fa-times-circle',      'label' => 'Loss & Damage'],
          'deaccession'     => ['icon' => 'fa-minus-circle',      'label' => 'Deaccession'],
          'disposal'        => ['icon' => 'fa-trash-alt',         'label' => 'Disposal'],
          'documentation'   => ['icon' => 'fa-file-alt',          'label' => 'Documentation'],
          'exit'            => ['icon' => 'fa-sign-out-alt',      'label' => 'Object Exit'],
          'retrospective'   => ['icon' => 'fa-history',           'label' => 'Retrospective'],
      ];
      $colors = ['primary', 'success', 'info', 'warning', 'secondary', 'dark'];
      $i = 0;
      @endphp

      <div class="row g-3">
        @foreach ($procedures as $key => $proc)
          @php $color = $colors[$i % count($colors)]; $i++; @endphp
          <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card h-100 border-{{ $color }}">
              <div class="card-body text-center p-3">
                <i class="fas {{ $proc['icon'] }} fa-2x mb-2 text-{{ $color }}"></i>
                <h6 class="card-title mb-2">{{ __($proc['label']) }}</h6>
                <a href="{{ url('/admin/spectrum/workflow?slug=' . $io->slug . '&procedure_type=' . $key) }}"
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

  {{-- Recent Activity --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent Procedure Activity') }}</h5>
    </div>
    <div class="card-body">
      @php
      try {
          $recentHistory = \Illuminate\Support\Facades\DB::table('spectrum_procedure_history')
              ->where('object_id', $io->id)
              ->orderBy('created_at', 'desc')
              ->limit(5)
              ->get();
      } catch (\Exception $e) {
          $recentHistory = collect();
      }
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

  {{-- Export Options --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-download me-2"></i>{{ __('Export Options') }}</h5>
    </div>
    <div class="card-body">
      <div class="btn-group flex-wrap" role="group">
        <a href="{{ url('/admin/spectrum/export?slug=' . $io->slug . '&format=pdf') }}" class="btn btn-outline-danger">
          <i class="fas fa-file-pdf me-1"></i>{{ __('Export PDF') }}
        </a>
        <a href="{{ url('/admin/spectrum/export?slug=' . $io->slug . '&format=csv') }}" class="btn btn-outline-success">
          <i class="fas fa-file-csv me-1"></i>{{ __('Export CSV') }}
        </a>
        <a href="{{ url('/admin/spectrum/export?slug=' . $io->slug . '&format=json') }}" class="btn btn-outline-primary">
          <i class="fas fa-file-code me-1"></i>{{ __('Export JSON') }}
        </a>
      </div>
    </div>
  </div>
@endsection
