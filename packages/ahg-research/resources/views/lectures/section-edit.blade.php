{{-- #1105 Lecture builder — edit a content section --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'lectures'])
@endsection

@section('title', __('Edit Section'))

@section('content')
<div class="container py-3" style="max-width: 820px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-pen text-primary me-2"></i>{{ __('Edit Section') }}</h1>
    <a href="{{ route('research.lecture-builder.show', $lecture['id']) }}" class="btn atom-btn-white">{{ __('Back to') }} {{ $lecture['title'] }}</a>
  </div>

  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ route('research.lecture-builder.section-update', $section['id']) }}" autocomplete="off">
    @csrf @method('PUT')
    <div class="mb-3"><label class="form-label">{{ __('Heading') }}</label>
      <input name="heading" class="form-control" value="{{ old('heading', $section['heading'] ?? '') }}" autocomplete="off"></div>
    <div class="mb-3"><label class="form-label">{{ __('Body (Markdown)') }}</label>
      <textarea name="body_markdown" rows="12" class="form-control font-monospace">{{ old('body_markdown', $section['body_markdown'] ?? '') }}</textarea></div>
    <div class="row">
      <div class="col-md-8 mb-3"><label class="form-label">{{ __('Media URL') }}</label>
        <input name="media_url" class="form-control" value="{{ old('media_url', $section['media_url'] ?? '') }}"></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Media type') }}</label>
        <select name="media_type" class="form-select"><option value="">{{ __('— none —') }}</option>
          @foreach (['image','video','audio','embed'] as $m)<option value="{{ $m }}" @selected(old('media_type', $section['media_type'] ?? '') === $m)>{{ ucfirst($m) }}</option>@endforeach
        </select></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Sort order') }}</label>
      <input type="number" name="sort_order" class="form-control" style="max-width:120px" value="{{ old('sort_order', $section['sort_order'] ?? 0) }}"></div>
    <button class="btn btn-primary">{{ __('Save section') }}</button>
  </form>
</div>
@endsection
