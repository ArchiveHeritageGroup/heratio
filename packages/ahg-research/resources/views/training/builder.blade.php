{{-- #1099 Training — course create/edit --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', $course ? __('Edit Course') : __('New Course'))

@section('content')
<div class="container py-3" style="max-width: 760px;">
  <h1 class="h3 mb-3"><i class="fas fa-user-graduate text-primary me-2"></i>{{ $course ? __('Edit Course') : __('New Course') }}</h1>
  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ $course ? route('research.training.update', $course['id']) : route('research.training.store') }}">
    @csrf @if ($course)@method('PUT')@endif
    <div class="mb-3"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input name="title" class="form-control" required value="{{ old('title', $course['title'] ?? '') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label>
      <textarea name="description" rows="3" class="form-control">{{ old('description', $course['description'] ?? '') }}</textarea></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Audience / role') }}</label>
        <input name="audience" class="form-control" value="{{ old('audience', $course['audience'] ?? '') }}" placeholder="{{ __('e.g. Public Services, Systems Administrator') }}"></div>
      <div class="col-md-3 mb-3"><label class="form-label">{{ __('Language') }}</label>
        <input name="language" class="form-control" value="{{ old('language', $course['language'] ?? '') }}"></div>
      <div class="col-md-3 mb-3"><label class="form-label">{{ __('Pass mark %') }}</label>
        <input type="number" name="pass_mark" class="form-control" min="0" max="100" value="{{ old('pass_mark', $course['pass_mark'] ?? 80) }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Status') }}</label>
      <select name="status" class="form-select">
        @foreach (['draft','published','archived'] as $s)<option value="{{ $s }}" @selected(old('status', $course['status'] ?? 'draft') === $s)>{{ ucfirst($s) }}</option>@endforeach
      </select></div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">{{ __('Save') }}</button>
      <a href="{{ $course ? route('research.training.show', $course['id']) : route('research.training.index') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>
@endsection
