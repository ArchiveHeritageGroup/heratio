@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('%1 list - report criteria', ['%1' => $type]) }}
    </h1>
    <span class="small" id="heading-label">
      {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
    </span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  <form action="{{ route('informationobject.itemOrFileList', ['slug' => $resource->slug, 'type' => $type]) }}" class="form-inline" method="post">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="report-criteria-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#report-criteria-collapse" aria-expanded="true" aria-controls="report-criteria-collapse">
            {{ __('Report criteria') }}
          </button>
        </h2>
        <div id="report-criteria-collapse" class="accordion-collapse collapse show" aria-labelledby="report-criteria-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="sortBy" class="form-label">{{ __('Sort by') }}</label>
              <select class="form-select" id="sortBy" name="sortBy">
                <option value="title" {{ old('sortBy') == 'title' ? 'selected' : '' }}>{{ __('Title') }}</option>
                <option value="identifier" {{ old('sortBy') == 'identifier' ? 'selected' : '' }}>{{ __('Identifier') }}</option>
                <option value="date" {{ old('sortBy') == 'date' ? 'selected' : '' }}>{{ __('Date') }}</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="format" class="form-label">{{ __('Format') }}</label>
              <select class="form-select" id="format" name="format">
                <option value="html" {{ old('format') == 'html' ? 'selected' : '' }}>{{ __('HTML') }}</option>
                <option value="csv" {{ old('format') == 'csv' ? 'selected' : '' }}>{{ __('CSV') }}</option>
              </select>
            </div>

            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="includeThumbnails" name="includeThumbnails" value="1" {{ old('includeThumbnails') ? 'checked' : '' }}>
                <label class="form-check-label" for="includeThumbnails">{{ __('Include thumbnails') }}</label>
              </div>
            </div>

          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Continue') }}"></li>
    </ul>

  </form>

@endsection
