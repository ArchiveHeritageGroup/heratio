@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $storage->name ?? '[Untitled]' }}?</h1>
@endsection

@section('content')
  <form method="POST" action="{{ route('physicalobject.destroy', $storage->slug) }}">
    @csrf
    @method('DELETE')

    @if(isset($informationObjects) && count($informationObjects) > 0)
      <div id="content" class="p-3">
        Click Confirm to delete this physical storage from the system. This will also remove the physical storage location from the following records:
        <ul class="mb-0">
          @foreach($informationObjects as $item)
            <li><a href="{{ route('informationobject.show', $item->slug) }}">{{ $item->title ?: '[Untitled]' }}</a></li>
          @endforeach
        </ul>
      </div>
    @endif

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('physicalobject.show', $storage->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>
@endsection
