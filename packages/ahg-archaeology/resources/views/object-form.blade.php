{{-- Create / edit an archaeological find - #1428 Phase 4 (with context picker) --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  @php
    $isEdit = (bool) $find;
    $action = $isEdit ? route('archaeology.object.update', $find->id) : route('archaeology.object.store');
    $val = fn($f, $d = '') => old($f, $isEdit ? ($find->$f ?? $d) : $d);
    $sel = fn($f, $id) => (int) old($f, $isEdit ? ($find->$f ?? 0) : 0) === (int) $id;
  @endphp

  <div class="d-flex justify-content-between align-items-start mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? __('Edit find') : __('Add find') }}</h1>
    <a href="{{ $isEdit ? route('archaeology.object', $find->id) : route('archaeology.objects') }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="post" action="{{ $action }}">
    @csrf

    <div class="card mb-3">
      <div class="card-header">{{ __('Find') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">{{ __('Title / object name') }}</label>
            <input type="text" name="title" class="form-control" value="{{ $val('title') }}">
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Accession number') }} <span class="text-danger">*</span></label>
            <input type="text" name="accession_number" class="form-control" required value="{{ $val('accession_number') }}">
          </div>

          <div class="col-md-4">
            <label class="form-label">{{ __('Site') }}</label>
            <select name="site_id" class="form-select">
              <option value="">-</option>
              @foreach($sites as $s)
                <option value="{{ $s->id }}" @selected((int) old('site_id', $siteId) === (int) $s->id)>{{ $s->site_number }} @if($s->title)- {{ $s->title }}@endif</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Context') }}</label>
            <select name="context_id" class="form-select">
              <option value="">-</option>
              @foreach($contexts as $c)<option value="{{ $c->id }}" @selected($sel('context_id',$c->id))>{{ $c->context_number }}</option>@endforeach
            </select>
            <small class="text-muted">{{ __('Contexts of the selected site. Change the site and save to switch.') }}</small>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Object type') }}</label>
            <select name="object_type_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['object_type'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('object_type_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">{{ __('Material') }}</label>
            <select name="material_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['material'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('material_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Period') }}</label>
            <select name="period_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['period'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('period_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Condition') }}</label>
            <select name="condition_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['condition'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('condition_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">{{ __('Recovery + dating') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Recovery method') }}</label>
            <select name="recovery_method_id" class="form-select">
              <option value="">-</option>
              @foreach(($vocab['recovery_method'] ?? []) as $t)<option value="{{ $t->id }}" @selected($sel('recovery_method_id',$t->id))>{{ $t->name }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-4"><label class="form-label">{{ __('Excavation ref') }}</label><input type="text" name="excavation_reference" class="form-control" value="{{ $val('excavation_reference') }}"></div>
          <div class="col-md-4"><label class="form-label">{{ __('Context reference (free text)') }}</label><input type="text" name="context_reference" class="form-control" value="{{ $val('context_reference') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Find date') }}</label><input type="date" name="find_date" class="form-control" value="{{ $val('find_date') }}"></div>
          <div class="col-md-5"><label class="form-label">{{ __('Find location') }}</label><input type="text" name="find_location" class="form-control" value="{{ $val('find_location') }}"></div>
          <div class="col-md-4"><label class="form-label">{{ __('Finder') }}</label><input type="text" name="finder" class="form-control" value="{{ $val('finder') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Date earliest') }}</label><input type="text" name="date_earliest" class="form-control" value="{{ $val('date_earliest') }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Date latest') }}</label><input type="text" name="date_latest" class="form-control" value="{{ $val('date_latest') }}"></div>
          <div class="col-md-6"><label class="form-label">{{ __('Dating note') }}</label><input type="text" name="dating_note" class="form-control" value="{{ $val('dating_note') }}"></div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">{{ __('Measurements + curation') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-2"><label class="form-label">{{ __('Item count') }}</label><input type="number" name="item_count" class="form-control" value="{{ $val('item_count', 1) }}"></div>
          <div class="col-md-3"><label class="form-label">{{ __('Weight (g)') }}</label><input type="number" step="0.001" name="weight_g" class="form-control" value="{{ $val('weight_g') }}"></div>
          <div class="col-md-4"><label class="form-label">{{ __('Storage location') }}</label><input type="text" name="storage_location" class="form-control" value="{{ $val('storage_location') }}"></div>
          <div class="col-12"><label class="form-label">{{ __('Provenance') }}</label><textarea name="provenance" class="form-control" rows="2">{{ $val('provenance') }}</textarea></div>
          <div class="col-12"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ $val('notes') }}</textarea></div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">{{ $isEdit ? __('Save find') : __('Create find') }}</button>
      <a href="{{ route('archaeology.objects') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
  </form>

</div>
@endsection
