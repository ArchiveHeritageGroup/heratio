{{-- Archaeological sites browse --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><i class="fas fa-map-location-dot"></i> {{ __('Archaeological sites') }}</h1>
    <a href="{{ route('archaeology.index') }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('Back') }}</a>
  </div>

  <form method="get" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label for="q" class="form-label small">{{ __('Search') }}</label>
        <input type="text" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm"
               placeholder="{{ __('Site number, locality, title') }}">
      </div>
      <div class="col-md-3">
        <label for="period_id" class="form-label small">{{ __('Period') }}</label>
        <select id="period_id" name="period_id" class="form-select form-select-sm">
          <option value="">{{ __('Any period') }}</option>
          @foreach($vocab['period'] ?? [] as $t)
            <option value="{{ $t->id }}" @selected(($filters['period_id'] ?? null) == $t->id)>{{ $t->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label for="site_type_id" class="form-label small">{{ __('Site type') }}</label>
        <select id="site_type_id" name="site_type_id" class="form-select form-select-sm">
          <option value="">{{ __('Any type') }}</option>
          @foreach($vocab['site_type'] ?? [] as $t)
            <option value="{{ $t->id }}" @selected(($filters['site_type_id'] ?? null) == $t->id)>{{ $t->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label for="excavated" class="form-label small">{{ __('Excavated') }}</label>
        <select id="excavated" name="excavated" class="form-select form-select-sm">
          <option value="">{{ __('Any') }}</option>
          <option value="1" @selected(($filters['excavated'] ?? '') === '1')>{{ __('Yes') }}</option>
          <option value="0" @selected(($filters['excavated'] ?? '') === '0')>{{ __('No') }}</option>
        </select>
      </div>
      <div class="col-md-1">
        <button class="btn btn-primary btn-sm w-100">{{ __('Filter') }}</button>
      </div>
    </div>
  </form>

  @if($sites->isEmpty())
    <div class="alert alert-info">{{ __('No sites match these criteria.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead>
          <tr>
            <th>{{ __('Site no.') }}</th>
            <th>{{ __('Title') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Period') }}</th>
            <th>{{ __('Locality') }}</th>
            <th class="text-center">{{ __('Excavated') }}</th>
          </tr>
        </thead>
        <tbody>
        @foreach($sites as $s)
          <tr>
            <td><a href="{{ route('archaeology.site', $s->id) }}">{{ $s->site_number }}</a>
              @if($s->national_site_number)
                <div class="small text-muted">{{ $s->national_site_number }}</div>
              @endif
            </td>
            <td>{{ $s->title ?: __('Untitled') }}</td>
            <td>{{ $s->site_type_name ?: '-' }}</td>
            <td>{{ $s->period_name ?: '-' }}</td>
            <td>{{ $s->locality ?: ($s->region ?: '-') }}</td>
            <td class="text-center">
              @if($s->excavated)<span class="badge bg-info">{{ __('Yes') }}</span>@else<span class="text-muted">-</span>@endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    {{ $sites->links() }}
  @endif

</div>
@endsection
