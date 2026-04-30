@extends('theme::layouts.2col')

@section('title', 'Edit Orphan Work')
@section('body-class', 'admin rights-admin orphan-work-edit')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">{{ __('Edit Orphan Work Designation') }}</h1>
@endsection

@section('content')
<form method="post" action="{{ route('rights-admin.orphan-work-update', $orphanWork->id ?? 0) }}">
  @csrf
  @method('PUT')
  <div class="card mb-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">{{ __('Orphan Work Details') }}</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label for="designation_date" class="form-label">Designation Date <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <input type="date" name="designation_date" id="designation_date" class="form-control" value="{{ $orphanWork->designation_date ?? '' }}">
        </div>
        <div class="col-md-6 mb-3">
          <label for="search_status" class="form-label">Search Status <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="search_status" id="search_status" class="form-select">
            <option value="pending" {{ ($orphanWork->search_status ?? '') === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="diligent" {{ ($orphanWork->search_status ?? '') === 'diligent' ? 'selected' : '' }}>Diligent Search Completed</option>
            <option value="incomplete" {{ ($orphanWork->search_status ?? '') === 'incomplete' ? 'selected' : '' }}>Incomplete</option>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label for="search_notes" class="form-label">Search Notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <textarea name="search_notes" id="search_notes" class="form-control" rows="4">{{ $orphanWork->search_notes ?? '' }}</textarea>
      </div>
    </div>
  </div>
  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ route('rights-admin.orphan-works') }}" class="btn atom-btn-outline-light">Cancel</a>
    <button type="submit" class="btn atom-btn-outline-light">{{ __('Save') }}</button>
  </section>
</form>
@endsection
