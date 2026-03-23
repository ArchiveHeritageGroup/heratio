@extends('theme::layouts.2col')

@section('title', ($repository->authorized_form_of_name ?? '[Untitled]'))
@section('body-class', 'edit repository')

@section('sidebar')
  @include('ahg-repository-manage::_context-menu', ['resource' => $repository, 'class' => 'QubitRepository'])
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('repository.editTheme.update', ['slug' => $repository->slug]) }}" enctype="multipart/form-data">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="style-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#style-collapse" aria-expanded="false" aria-controls="style-collapse">
            {{ __('Style') }}
          </button>
        </h2>
        <div id="style-collapse" class="accordion-collapse collapse" aria-labelledby="style-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="backgroundColor" class="form-label">{{ __('Background color') }} <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="color" class="form-control form-control-color" id="backgroundColor" name="backgroundColor" value="{{ old('backgroundColor', $repository->background_color ?? '#ffffff') }}">
            </div>

            <div class="mb-3">
              <label for="banner" class="form-label">{{ __('Banner') }} <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="file" class="form-control" id="banner" name="banner" accept="image/*">
              @if(!empty($repository->banner_path))
                <div class="mt-2">
                  <img src="{{ $repository->banner_path }}" class="img-fluid img-thumbnail" alt="{{ __('Current banner') }}" style="max-height:100px">
                </div>
              @endif
            </div>

            <div class="mb-3">
              <label for="logo" class="form-label">{{ __('Logo') }} <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
              @if(!empty($repository->logo_path))
                <div class="mt-2">
                  <img src="{{ $repository->logo_path }}" class="img-fluid img-thumbnail" alt="{{ __('Current logo') }}" style="max-height:100px">
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="content-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-collapse" aria-expanded="false" aria-controls="content-collapse">
            {{ __('Page content') }}
          </button>
        </h2>
        <div id="content-collapse" class="accordion-collapse collapse" aria-labelledby="content-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="htmlSnippet" class="form-label">{{ __('Description') }} <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control resizable" id="htmlSnippet" name="htmlSnippet" rows="6">{{ old('htmlSnippet', $repository->html_snippet ?? '') }}</textarea>
              <div class="form-text">{{ __('Content in this area will appear below an uploaded banner and above the institution\'s description areas. It can be used to offer a summary of the institution\'s mandate, include a tag line or important information, etc. HTML and inline CSS can be used to style the contents.') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a class="btn atom-btn-outline-light" href="{{ route('repository.show', ['slug' => $repository->slug]) }}" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
    </ul>

  </form>
@endsection
