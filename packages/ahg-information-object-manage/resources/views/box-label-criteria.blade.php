@extends('ahg-theme-b5::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('%1 - report criteria', ['%1' => $type]) }}
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

  <form action="{{ route('informationobject.boxLabel', ['slug' => $resource->slug, 'type' => $type]) }}" class="form-inline" method="post">
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
              <label for="format" class="form-label">{{ __('Format') }}</label>
              <select class="form-select" id="format" name="format">
                <option value="html" {{ old('format', $resource->format ?? '') == 'html' ? 'selected' : '' }}>{{ __('HTML') }}</option>
                <option value="csv" {{ old('format', $resource->format ?? '') == 'csv' ? 'selected' : '' }}>{{ __('CSV') }}</option>
              </select>
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
