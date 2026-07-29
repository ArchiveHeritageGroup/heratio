{{-- Create / edit an archaeological site - #1428 Phase 4 --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  @php
    $isEdit = (bool) $site;
    $action = $isEdit ? route('archaeology.site.update', $site->id) : route('archaeology.site.store');
    $val = fn($f, $d = '') => old($f, $isEdit ? ($site->$f ?? $d) : $d);
    $sel = fn($f, $id) => (int) old($f, $isEdit ? ($site->$f ?? 0) : 0) === (int) $id;
  @endphp

  <div class="d-flex justify-content-between align-items-start mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? __('Edit site') : __('Add site') }}</h1>
    <a href="{{ $isEdit ? route('archaeology.site', $site->id) : route('archaeology.sites') }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="post" action="{{ $action }}">
    @csrf

    <div class="card mb-3">
      <div class="card-header">{{ __('Identity') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">{{ __('Site name / title') }}</label>
            <input type="text" name="title" class="form-control" value="{{ $val('title') }}">
          </div>
          @unless($isEdit)
            <div class="col-md-4">
              <label class="form-label">{{ __('Repository') }}</label>
              <select name="repository_id" class="form-select">
                <option value="">-</option>
                @foreach($repositories as $r)<option value="{{ $r->id }}" @selected($sel('repository_id',$r->id))>{{ $r->name }}</option>@endforeach
              </select>
            </div>
          @endunless
          <div class="col-md-3">
            <label class="form-label">{{ __('Site number') }} <span class="text-danger">*</span></label>
            <input type="text" name="site_number" class="form-control" required value="{{ $val('site_number') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('National site number') }}</label>
            <input type="text" name="national_site_number" class="form-control" value="{{ $val('national_site_number') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Site type') }}</label>
            <select name="site_type_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['site_type'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('site_type_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Period') }}</label>
            <select name="period_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['period'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('period_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">{{ __('Location') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">{{ __('Region') }}</label><input type="text" name="region" class="form-control" value="{{ $val('region') }}"></div>
          <div class="col-md-4"><label class="form-label">{{ __('Locality') }}</label><input type="text" name="locality" class="form-control" value="{{ $val('locality') }}"></div>
          <div class="col-md-4"><label class="form-label">{{ __('Area (sqm)') }}</label><input type="number" step="0.01" name="area_sqm" class="form-control" value="{{ $val('area_sqm') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Latitude') }}</label><input type="number" step="0.00000001" name="latitude" class="form-control" value="{{ $val('latitude') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Longitude') }}</label><input type="number" step="0.00000001" name="longitude" class="form-control" value="{{ $val('longitude') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Elevation (m)') }}</label><input type="number" name="elevation_m" class="form-control" value="{{ $val('elevation_m') }}"></div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Protection status') }}</label>
            <select name="protection_status_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['protection_status'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('protection_status_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-12"><label class="form-label">{{ __('Location description') }}</label><textarea name="location_description" class="form-control" rows="2">{{ $val('location_description') }}</textarea></div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">{{ __('Dating + investigation') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">{{ __('Date earliest') }}</label><input type="text" name="date_earliest" class="form-control" value="{{ $val('date_earliest') }}" placeholder="c. 1200 AD"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Date latest') }}</label><input type="text" name="date_latest" class="form-control" value="{{ $val('date_latest') }}"></div>
          <div class="col-md-6"><label class="form-label">{{ __('Dating note') }}</label><input type="text" name="dating_note" class="form-control" value="{{ $val('dating_note') }}"></div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input type="checkbox" name="excavated" value="1" class="form-check-input" id="excavated" @checked((bool) $val('excavated'))>
              <label class="form-check-label" for="excavated">{{ __('Excavated') }}</label>
            </div>
          </div>
          <div class="col-md-3"><label class="form-label">{{ __('Excavation years') }}</label><input type="text" name="excavation_years" class="form-control" value="{{ $val('excavation_years') }}" placeholder="2026"></div>
          <div class="col-md-6"><label class="form-label">{{ __('Excavator') }}</label><input type="text" name="excavator" class="form-control" value="{{ $val('excavator') }}"></div>
          <div class="col-md-6"><label class="form-label">{{ __('Excavation institution') }}</label><input type="text" name="excavation_institution" class="form-control" value="{{ $val('excavation_institution') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Permit number') }}</label><input type="text" name="permit_number" class="form-control" value="{{ $val('permit_number') }}"></div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Research potential') }}</label>
            <select name="research_potential" class="form-select">
              @foreach(['high','medium','low'] as $rp)<option value="{{ $rp }}" @selected($val('research_potential','medium') === $rp)>{{ ucfirst($rp) }}</option>@endforeach
            </select>
          </div>
          <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ $val('notes') }}</textarea></div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">{{ $isEdit ? __('Save site') : __('Create site') }}</button>
      <a href="{{ route('archaeology.sites') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
  </form>

</div>
@endsection
