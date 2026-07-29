{{-- Create / edit a stratigraphic context - #1428 Phase 1 --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  @php
    $isEdit = (bool) $context;
    $action = $isEdit ? route('archaeology.context.update', $context->id) : route('archaeology.context.store');
    $val = fn($f, $d = '') => old($f, $isEdit ? ($context->$f ?? $d) : $d);
  @endphp

  <div class="d-flex justify-content-between align-items-start mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? __('Edit context') : __('Add context') }}
      <span class="text-muted">&middot; {{ $site->title ?? '' }}</span>
    </h1>
    <a href="{{ route('archaeology.contexts', $site->id) }}" class="btn btn-outline-secondary btn-sm">{{ __('Cancel') }}</a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="post" action="{{ $action }}">
    @csrf
    <input type="hidden" name="site_id" value="{{ $site->id }}">

    <div class="card mb-3">
      <div class="card-header">{{ __('Context') }}</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">{{ __('Context number') }} <span class="text-danger">*</span></label>
            <input type="text" name="context_number" class="form-control" required value="{{ $val('context_number') }}" placeholder="1002">
          </div>
          <div class="col-md-5">
            <label class="form-label">{{ __('Type') }}</label>
            <select name="context_type_id" class="form-select">
              <option value="">-</option>
              @foreach($types as $t)
                <option value="{{ $t->id }}" @selected((int)$val('context_type_id') === (int)$t->id)>{{ $t->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">{{ __('Phase') }}</label>
            <select name="phase_id" class="form-select">
              <option value="">-</option>
              @foreach($phases as $p)
                <option value="{{ $p->id }}" @selected((int)$val('phase_id') === (int)$p->id)>{{ $p->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">{{ __('Top elevation (m)') }}</label>
            <input type="number" step="0.001" name="top_elevation_m" class="form-control" value="{{ $val('top_elevation_m') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Bottom elevation (m)') }}</label>
            <input type="number" step="0.001" name="bottom_elevation_m" class="form-control" value="{{ $val('bottom_elevation_m') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Excavation ref') }}</label>
            <input type="text" name="excavation_reference" class="form-control" value="{{ $val('excavation_reference') }}" placeholder="Trench A, spit 3">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Excavation date') }}</label>
            <input type="date" name="excavation_date" class="form-control" value="{{ $val('excavation_date') }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">{{ __('Excavator') }}</label>
            <input type="text" name="excavator" class="form-control" value="{{ $val('excavator') }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Date earliest') }}</label>
            <input type="text" name="date_earliest" class="form-control" value="{{ $val('date_earliest') }}" placeholder="c. 1400 AD">
          </div>
          <div class="col-md-3">
            <label class="form-label">{{ __('Date latest') }}</label>
            <input type="text" name="date_latest" class="form-control" value="{{ $val('date_latest') }}" placeholder="c. 1700 AD">
          </div>

          <div class="col-12">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="2">{{ $val('description') }}</textarea>
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Interpretation') }}</label>
            <textarea name="interpretation" class="form-control" rows="2">{{ $val('interpretation') }}</textarea>
          </div>
          <div class="col-12">
            <label class="form-label">{{ __('Dating note') }}</label>
            <input type="text" name="dating_note" class="form-control" value="{{ $val('dating_note') }}">
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">{{ $isEdit ? __('Save context') : __('Create context') }}</button>
      <a href="{{ route('archaeology.contexts', $site->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
    @if(! $isEdit)
      <p class="text-muted small mt-2">{{ __('A descriptive record is created for this context so you can upload its plan and section drawings.') }}</p>
    @endif
  </form>

</div>
@endsection
