{{-- heratio#144 — Strongroom show --}}
@extends('theme::layouts.1col')

@section('title', $room->name)
@section('body-class', 'show strongroom')

@section('content')
  @php
    $capacity = $room->capacity_value !== null ? (float) $room->capacity_value : null;
    $used = (float) $usedUnits;
    $pct = ($capacity !== null && $capacity > 0) ? min(100, (int) round(($used / $capacity) * 100)) : null;
    $unitLabel = $capacityUnits[$room->capacity_unit] ?? $room->capacity_unit;
    $barClass = $pct === null ? 'bg-secondary' : ($pct >= 100 ? 'bg-danger' : ($pct >= 90 ? 'bg-warning' : 'bg-success'));
  @endphp

  <div class="d-flex flex-wrap align-items-center mb-3">
    <h1 class="me-3 mb-0">{{ $room->name }}</h1>
    @auth
      <a href="{{ route('strongroom.edit',          ['slug' => $room->slug]) }}" class="btn btn-outline-primary btn-sm me-2">
        <i class="fas fa-pencil-alt me-1"></i>{{ __('Edit') }}
      </a>
      <a href="{{ route('strongroom.confirmDelete', ['slug' => $room->slug]) }}" class="btn btn-outline-danger btn-sm me-2">
        <i class="fas fa-trash me-1"></i>{{ __('Delete') }}
      </a>
    @endauth
    <a href="{{ route('strongroom.browse') }}" class="btn btn-link btn-sm">&laquo; {{ __('Back to strongrooms') }}</a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="row g-3 mb-4">
    <div class="col-md-7">
      <div class="card h-100">
        <div class="card-header">{{ __('Details') }}</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4">{{ __('Slug') }}</dt>
            <dd class="col-sm-8"><code>{{ $room->slug }}</code></dd>

            <dt class="col-sm-4">{{ __('Location') }}</dt>
            <dd class="col-sm-8">{{ $room->location_description ?: '—' }}</dd>

            <dt class="col-sm-4">{{ __('Notes') }}</dt>
            <dd class="col-sm-8" style="white-space: pre-wrap;">{{ $room->notes ?: '—' }}</dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="col-md-5">
      <div class="card h-100">
        <div class="card-header">{{ __('Capacity') }}</div>
        <div class="card-body">
          @if($capacity !== null)
            <div class="progress mb-2" role="progressbar" aria-label="{{ __('Utilisation') }}"
                 aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" style="height: 1.5rem;">
              <div class="progress-bar {{ $barClass }}" style="width: {{ $pct }}%">{{ $pct }}%</div>
            </div>
            <dl class="row mb-0 small">
              <dt class="col-6">{{ __('Total') }}</dt>
              <dd class="col-6 text-end">{{ rtrim(rtrim(number_format($capacity, 2), '0'), '.') }} {{ $unitLabel }}</dd>

              <dt class="col-6">{{ __('Used') }}</dt>
              <dd class="col-6 text-end">{{ rtrim(rtrim(number_format($used, 2), '0'), '.') }} {{ $unitLabel }}</dd>

              <dt class="col-6">{{ __('Remaining') }}</dt>
              <dd class="col-6 text-end">{{ rtrim(rtrim(number_format(max(0, $capacity - $used), 2), '0'), '.') }} {{ $unitLabel }}</dd>
            </dl>
          @else
            <p class="text-muted mb-0">
              {{ __('No capacity set for this strongroom.') }}
              @auth
                <a href="{{ route('strongroom.edit', ['slug' => $room->slug]) }}">{{ __('Set one') }}</a>.
              @endauth
            </p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>{{ __('Occupants') }} <span class="badge bg-secondary">{{ $occupants->count() }}</span></span>
    </div>
    @if($occupants->count() > 0)
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>{{ __('Physical object') }}</th>
              <th>{{ __('Location') }}</th>
              <th class="text-end">{{ __('Size used') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($occupants as $occ)
              <tr>
                <td>
                  @if($occ->slug)
                    <a href="{{ route('physicalobject.show', ['slug' => $occ->slug]) }}">{{ $occ->name ?: $occ->slug }}</a>
                  @else
                    {{ $occ->name ?: __('(unnamed)') }}
                  @endif
                </td>
                <td class="small text-muted">{{ $occ->location }}</td>
                <td class="text-end small">
                  {{ rtrim(rtrim(number_format((float) $occ->size_units_used, 2), '0'), '.') }} {{ $unitLabel }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="card-body">
        <p class="text-muted mb-0">{{ __('No physical objects are assigned to this strongroom yet.') }}</p>
      </div>
    @endif
  </div>
@endsection
