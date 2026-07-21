{{-- Archaeology collections dashboard --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <h1 class="h3 mb-3"><i class="fas fa-trowel"></i> {{ __('Archaeology') }}</h1>
  <p class="text-muted small mb-4">
    {{ __('Sites and finds catalogued to ISAD(G). A Work-level record describes the project or accession, a site is a series, and each find an item.') }}
  </p>

  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-primary"><div class="card-body text-center py-3">
        <div class="display-6 text-primary">{{ number_format($stats['sites']) }}</div>
        <div class="small text-muted text-uppercase">{{ __('Sites') }}</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-info"><div class="card-body text-center py-3">
        <div class="display-6 text-info">{{ number_format($stats['excavated']) }}</div>
        <div class="small text-muted text-uppercase">{{ __('Excavated') }}</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-success"><div class="card-body text-center py-3">
        <div class="display-6 text-success">{{ number_format($stats['objects']) }}</div>
        <div class="small text-muted text-uppercase">{{ __('Find records') }}</div>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card border-secondary"><div class="card-body text-center py-3">
        <div class="display-6 text-secondary">{{ number_format($stats['items']) }}</div>
        <div class="small text-muted text-uppercase">{{ __('Physical items') }}</div>
      </div></div>
    </div>
  </div>

  @if(($stats['unsited'] ?? 0) > 0)
    <div class="alert alert-warning small">
      <i class="fas fa-triangle-exclamation me-1"></i>
      {{ trans_choice(':count find record is not linked to a site.|:count find records are not linked to a site.', $stats['unsited'], ['count' => number_format($stats['unsited'])]) }}
      {{ __('Provenance cannot be reconstructed for these.') }}
      <a href="{{ route('archaeology.objects') }}">{{ __('Review finds') }}</a>
    </div>
  @endif

  <div class="row g-3">
    @foreach([
      ['title' => __('Finds by period'),   'rows' => $byPeriod],
      ['title' => __('Finds by material'), 'rows' => $byMaterial],
      ['title' => __('Sites by type'),     'rows' => $bySiteType],
    ] as $panel)
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">{{ $panel['title'] }}</div>
          <div class="card-body p-0">
            @if($panel['rows']->isEmpty())
              <p class="text-muted small p-3 mb-0">{{ __('Nothing recorded yet.') }}</p>
            @else
              <table class="table table-sm mb-0">
                <tbody>
                @foreach($panel['rows'] as $row)
                  <tr>
                    <td>{{ $row->label }}</td>
                    <td class="text-end text-muted">{{ number_format($row->total) }}</td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            @endif
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="mt-4">
    <a href="{{ route('archaeology.sites') }}" class="btn btn-outline-primary btn-sm">
      <i class="fas fa-map-location-dot me-1"></i>{{ __('Browse sites') }}
    </a>
    <a href="{{ route('archaeology.objects') }}" class="btn btn-outline-success btn-sm">
      <i class="fas fa-box-archive me-1"></i>{{ __('Browse finds') }}
    </a>
  </div>

</div>
@endsection
