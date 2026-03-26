@extends('ahg-theme-b5::layout_1col')

@section('title')
  <h1>{{ __('Are you sure you want to delete the finding aid of %1%?', ['%1%' => $resource->title]) }}</h1>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  <form action="{{ route('informationobject.findingaid.delete', $resource->slug) }}" method="post">
    @csrf
    @method('DELETE')

    <div id="content" class="p-3">
      {{ __('The following file will be deleted from the file system:') }}

      <ul class="mb-0">
        <li><a href="{{ asset($path) }}" target="_blank">{{ $filename }}</a></li>
        <li>{{ __('If the finding aid is an uploaded PDF, the transcript will be deleted too.') }}</li>
      </ul>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="{{ __('Delete') }}"></li>
    </ul>

  </form>

@endsection
