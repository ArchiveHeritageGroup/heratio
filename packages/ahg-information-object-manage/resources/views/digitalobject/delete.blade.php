@extends('theme::layout_1col')

@section('title')
  @if(isset($resource->parent))
    @php
      $chaptersId = config('atom.term.CHAPTERS_ID');
      $subtitlesId = config('atom.term.SUBTITLES_ID');
    @endphp
    @if($chaptersId == $resource->usageId || $subtitlesId == $resource->usageId)
      <h1>{{ __('Are you sure you want to delete these captions/subtitles/chapters?') }}</h1>
    @else
      <h1>{{ __('Are you sure you want to delete this reference/thumbnail representation?') }}</h1>
    @endif
  @else
    <h1>{{ __('Are you sure you want to delete the %1% linked to %2%?', ['%1%' => mb_strtolower(config('app.ui_label_digitalobject', 'digital object')), '%2%' => $object->authorized_form_of_name ?? $object->title ?? '']) }}</h1>
  @endif
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

  <form method="POST" action="{{ route('io.digitalobject.destroy', $resource->id) }}">
    @csrf
    @method('DELETE')

    <ul class="actions mb-3 nav gap-2">
      @if(isset($resource->parent))
        <li><a href="{{ route('io.digitalobject.edit', $resource->parent->id ?? $resource->parentId) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      @else
        <li><a href="{{ route('io.digitalobject.edit', $resource->id) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      @endif
      <li><input class="btn atom-btn-outline-danger" type="submit" value="{{ __('Delete') }}"></li>
    </ul>

  </form>

@endsection
