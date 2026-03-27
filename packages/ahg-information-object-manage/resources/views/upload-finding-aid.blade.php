@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')

  <h1>{{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}</h1>

@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  <form action="{{ route('informationobject.findingaid.upload', $resource->slug) }}" method="post" enctype="multipart/form-data">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="load-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#load-collapse" aria-expanded="true" aria-controls="load-collapse">
            {{ __('Upload finding aid') }}
          </button>
        </h2>
        <div id="load-collapse" class="accordion-collapse collapse show" aria-labelledby="load-heading">
          <div class="accordion-body">
            @if(isset($errorMessage))
              <div class="alert alert-danger" role="alert">
                {{ $errorMessage }}
              </div>
            @endif

            <div class="mb-3">
              <label for="file" class="form-label">{{ __('%1% file', ['%1%' => strtoupper($format ?? 'PDF')]) }}</label>
              <input type="file" class="form-control" id="file" name="file">
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Upload') }}"></li>
    </ul>

  </form>

@endsection
