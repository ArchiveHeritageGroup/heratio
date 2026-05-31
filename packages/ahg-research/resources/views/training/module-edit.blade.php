{{-- #1099 Training — module editor --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', __('Edit Module'))

@section('content')
<div class="container py-3" style="max-width: 800px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-pen text-primary me-2"></i>{{ __('Edit Module') }}</h1>
    <a href="{{ route('research.training.show', $course['id']) }}" class="btn atom-btn-white">{{ __('Back to') }} {{ $course['title'] }}</a>
  </div>
  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ route('research.training.module-update', $module['id']) }}">
    @csrf @method('PUT')
    <div class="mb-3"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input name="title" class="form-control" required value="{{ old('title', $module['title']) }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Curriculum lecture (content source)') }}</label>
      <select name="lecture_id" class="form-select"><option value="">{{ __('— none / use content below —') }}</option>
        @foreach ($lectures as $l)<option value="{{ $l['id'] }}" @selected((string)old('lecture_id', $module['lecture_id'] ?? '') === (string)$l['id'])>{{ $l['title'] }}</option>@endforeach
      </select></div>
    <div class="mb-3"><label class="form-label">{{ __('Module content (Markdown)') }}</label>
      <textarea name="body_markdown" rows="10" class="form-control font-monospace">{{ old('body_markdown', $module['body_markdown'] ?? '') }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Sort order') }}</label>
      <input type="number" name="sort_order" class="form-control" style="max-width:120px" value="{{ old('sort_order', $module['sort_order'] ?? 0) }}"></div>
    <button class="btn btn-primary">{{ __('Save module') }}</button>
  </form>
</div>
@endsection
