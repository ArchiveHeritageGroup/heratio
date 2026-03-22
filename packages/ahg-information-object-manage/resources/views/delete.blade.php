@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $io->title ?? '[Untitled]' }}?</h1>
@endsection

@section('content')

  <form method="POST" action="{{ route('informationobject.destroy', $io->slug) }}">
    @csrf
    @method('DELETE')

    @if(isset($descendantCount) && $descendantCount > 0)
      <div id="content" class="p-3">
        It has {{ $descendantCount }} descendants that will also be deleted:
        <ul class="mb-0">
          @foreach($descendants ?? [] as $index => $item)
            <li><a href="{{ route('informationobject.show', $item->slug) }}">{{ $item->title ?: '[Untitled]' }}</a></li>
            @if($index + 1 >= ($previewSize ?? 10))
              @break
            @endif
          @endforeach
        </ul>

        @if(isset($previewIsLimited) && $previewIsLimited)
          <div class="alert alert-warning mt-3 mb-0">
            Only {{ $previewSize ?? 10 }} descriptions were shown.
            <a href="{{ route('informationobject.browse', ['collection' => $io->id, 'topLod' => 0]) }}" class="alert-link">
              View the full list of descendants.
            </a>
          </div>
        @endif
      </div>
    @endif

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>

@endsection
