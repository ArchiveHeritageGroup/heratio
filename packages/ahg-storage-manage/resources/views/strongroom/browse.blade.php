{{-- heratio#144 — Strongroom browse --}}
@extends('theme::layouts.1col')

@section('title', 'Strongrooms')
@section('body-class', 'browse strongroom')

@section('content')
  <div class="d-flex align-items-center mb-3">
    <h1 class="me-3 mb-0">{{ __('Strongrooms') }}</h1>
    @auth
      <a href="{{ route('strongroom.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus me-1"></i>{{ __('Add strongroom') }}
      </a>
    @endauth
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <form method="get" action="{{ route('strongroom.browse') }}" class="mb-3" role="search">
    <div class="input-group" style="max-width: 32rem;">
      <input type="search" name="q" value="{{ $search }}" class="form-control"
             placeholder="{{ __('Search strongrooms') }}" aria-label="{{ __('Search strongrooms') }}">
      <button type="submit" class="btn btn-outline-secondary">{{ __('Search') }}</button>
      @if($search !== '')
        <a href="{{ route('strongroom.browse') }}" class="btn btn-outline-secondary">{{ __('Clear') }}</a>
      @endif
    </div>
  </form>

  @if($rooms->total() > 0)
    <div class="table-responsive mb-3">
      <table class="table table-bordered align-middle mb-0">
        <thead>
          <tr>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Location') }}</th>
            <th>{{ __('Capacity') }}</th>
            <th style="min-width: 12rem;">{{ __('Utilisation') }}</th>
            <th class="text-end">{{ __('Occupants') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rooms as $room)
            @php
              $capacity = $room->capacity_value !== null ? (float) $room->capacity_value : null;
              $used = (float) $room->used_units;
              $pct = ($capacity !== null && $capacity > 0) ? min(100, (int) round(($used / $capacity) * 100)) : null;
              $unitLabel = $capacityUnits[$room->capacity_unit] ?? $room->capacity_unit;
              $barClass = $pct === null ? 'bg-secondary' : ($pct >= 100 ? 'bg-danger' : ($pct >= 90 ? 'bg-warning' : 'bg-success'));
            @endphp
            <tr>
              <td>
                <a href="{{ route('strongroom.show', ['slug' => $room->slug]) }}">{{ $room->name }}</a>
              </td>
              <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $room->location_description, 80) }}</td>
              <td class="small">
                @if($capacity !== null)
                  {{ rtrim(rtrim(number_format($capacity, 2), '0'), '.') }} {{ $unitLabel }}
                @else
                  <span class="text-muted">{{ __('not set') }}</span>
                @endif
              </td>
              <td>
                @if($pct !== null)
                  <div class="progress" role="progressbar" aria-label="{{ __('Utilisation') }}"
                       aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" style="height: 1rem;">
                    <div class="progress-bar {{ $barClass }}" style="width: {{ $pct }}%">{{ $pct }}%</div>
                  </div>
                  <div class="small text-muted mt-1">
                    {{ rtrim(rtrim(number_format($used, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($capacity, 2), '0'), '.') }} {{ $unitLabel }}
                  </div>
                @else
                  <span class="text-muted small">{{ __('no capacity set') }}</span>
                @endif
              </td>
              <td class="text-end">{{ (int) $room->occupant_count }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{ $rooms->withQueryString()->links() }}
  @else
    <p class="text-muted">
      @if($search !== '')
        {{ __('No strongrooms match your search.') }}
      @else
        {{ __('No strongrooms yet.') }}
        @auth
          <a href="{{ route('strongroom.create') }}">{{ __('Add the first one.') }}</a>
        @endauth
      @endif
    </p>
  @endif
@endsection
