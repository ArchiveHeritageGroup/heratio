@extends('theme::layouts.1col')

@section('title')
  <h1>Are you sure you want to delete {{ $term->name }}?</h1>
@endsection

@section('content')

  @if($taxonomyName)
    <p class="text-muted">Taxonomy: {{ $taxonomyName }}</p>
  @endif

  <form method="POST" action="{{ route('term.destroy', $term->slug) }}">
    @csrf
    @method('DELETE')

    @if((isset($relatedObjectCount) && $relatedObjectCount > 0) || (isset($descendantCount) && $descendantCount > 0) || (isset($eventCount) && $eventCount > 0))
      <div id="content" class="p-3">

        @if(isset($relatedObjectCount) && $relatedObjectCount > 0)
          <p>This term is used by {{ $relatedObjectCount }} related object(s).</p>
        @endif

        @if(isset($eventCount) && $eventCount > 0)
          <p>It is associated with {{ $eventCount }} event(s).</p>
        @endif

        @if(isset($descendantCount) && $descendantCount > 0)
          <p>It has {{ $descendantCount }} descendants that will also be deleted:</p>
          <ul class="mb-0">
            @foreach($descendants ?? [] as $index => $desc)
              <li><a href="{{ route('term.show', $desc->slug) }}">{{ $desc->name ?: '[Untitled]' }}</a></li>
              @if($index + 1 >= ($previewSize ?? 10))
                @break
              @endif
            @endforeach
          </ul>

          @if(isset($previewIsLimited) && $previewIsLimited)
            <div class="alert alert-warning mt-3 mb-0">
              Only {{ $previewSize ?? 10 }} terms were shown.
            </div>
          @endif
        @endif

      </div>
    @endif

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('term.show', $term->slug) }}" class="btn atom-btn-outline-light">Cancel</a></li>
      <li><input class="btn atom-btn-outline-danger" type="submit" value="Delete"></li>
    </ul>
  </form>

@endsection
