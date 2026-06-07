{{-- heratio#146 — Exhibition spaces browse --}}
@extends('theme::layouts.1col')

@section('title', __('Exhibition spaces'))
@section('body-class', 'browse exhibition-space')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-3 gap-2">
    <h1 class="mb-0 flex-grow-1"><i class="fas fa-palette me-2"></i>{{ __('Exhibition spaces') }}</h1>
    <a href="{{ route('exhibition-space.create') }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>{{ __('Add exhibition space') }}
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <form method="GET" action="{{ route('exhibition-space.browse') }}" class="mb-3" role="search">
    <div class="input-group input-group-sm" style="max-width: 32rem;">
      <input type="search" name="subquery" class="form-control" placeholder="{{ __('Search by name or building...') }}" value="{{ $search }}">
      <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i></button>
      @if(!empty($search))
        <a href="{{ route('exhibition-space.browse') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
      @endif
    </div>
  </form>

  @if($pager->isEmpty())
    <div class="alert alert-info">
      {{ __('No exhibition spaces yet.') }}
      @auth <a href="{{ route('exhibition-space.create') }}">{{ __('Add the first one.') }}</a> @endauth
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Building / floor') }}</th>
            <th>{{ __('Capacity') }}</th>
            <th>{{ __('Current utilisation') }}</th>
            <th>{{ __('Current placements') }}</th>
            <th class="text-end">{{ __('Plan') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager as $row)
            <tr>
              <td><a href="{{ route('exhibition-space.show', ['slug' => $row->slug]) }}">{{ $row->name }}</a></td>
              <td><span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $row->space_type)) }}</span></td>
              <td>{{ trim($row->building.($row->floor ? ' · '.$row->floor : '')) ?: '—' }}</td>
              <td>
                @if($row->capacity_value !== null)
                  {{ (float) $row->capacity_value }} {{ __(\AhgExhibition\Services\ExhibitionSpaceService::CAPACITY_UNITS[$row->capacity_unit] ?? $row->capacity_unit) }}
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td>
                @if($row->capacity_value !== null && (float) $row->capacity_value > 0)
                  @php $pct = min(100, ((float) $row->used_units_today / (float) $row->capacity_value) * 100); @endphp
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height: 8px; min-width: 6rem;">
                      <div class="progress-bar @if($pct >= 90) bg-danger @elseif($pct >= 70) bg-warning @else bg-success @endif" role="progressbar" style="width: {{ $pct }}%"></div>
                    </div>
                    <small class="text-muted">{{ (float) $row->used_units_today }} / {{ (float) $row->capacity_value }}</small>
                  </div>
                @else
                  <span class="text-muted small">{{ (float) $row->used_units_today }}</span>
                @endif
              </td>
              <td>{{ (int) $row->current_placements }}</td>
              <td class="text-end"><a href="{{ route('exhibition-space.plan', ['slug' => $row->slug]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Building plan') }}"><i class="fas fa-drafting-compass me-1"></i>{{ __('Plan') }}</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="mt-3">{{ $pager->withQueryString()->links() }}</div>
  @endif
@endsection
