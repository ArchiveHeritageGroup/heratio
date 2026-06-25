@extends('theme::layouts.1col')

@section('title', __('New Research Dataset'))
@section('body-class', 'rdm datasets')

@section('content')
<h1 class="h4 mb-3"><i class="fas fa-database me-2"></i>{{ __('New Research Dataset') }}</h1>

@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card">
  <div class="card-body">
    <form method="POST" action="{{ route('rdm.datasets.store') }}">
      @csrf
      <div class="mb-3">
        <label for="title" class="form-label fw-bold">{{ __('Title') }}</label>
        <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}" required maxlength="500">
      </div>
      <div class="mb-3">
        <label for="description" class="form-label">{{ __('Description') }}</label>
        <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
      </div>
      <div class="mb-3">
        <label for="project_id" class="form-label">{{ __('Research project') }} <span class="text-muted small">({{ __('optional') }})</span></label>
        <select name="project_id" id="project_id" class="form-select">
          <option value="">{{ __('— none —') }}</option>
          @foreach ($projects as $p)
            <option value="{{ $p->id }}" @selected(old('project_id') == $p->id)>{{ $p->title }}</option>
          @endforeach
        </select>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>{{ __('Create dataset') }}</button>
        <a href="{{ route('rdm.datasets.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>
@endsection
