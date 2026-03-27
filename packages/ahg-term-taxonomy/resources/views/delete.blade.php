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
          <p>It is associated with {{ $eventCount }} event(s):</p>
          @php
            $eventDetails = \Illuminate\Support\Facades\DB::table('event')
                ->leftJoin('event_i18n', function ($j) {
                    $j->on('event_i18n.id', '=', 'event.id')->where('event_i18n.culture', '=', 'en');
                })
                ->leftJoin('information_object', 'information_object.id', '=', 'event.object_id')
                ->leftJoin('information_object_i18n', function ($j) {
                    $j->on('information_object_i18n.id', '=', 'information_object.id')->where('information_object_i18n.culture', '=', 'en');
                })
                ->leftJoin('slug', function ($j) {
                    $j->on('slug.object_id', '=', 'information_object.id');
                })
                ->where('event.type_id', $term->id)
                ->select(
                    'event.id as event_id',
                    'event_i18n.date as date_display',
                    'event.start_date',
                    'event.end_date',
                    'information_object_i18n.title as io_title',
                    'slug.slug as io_slug'
                )
                ->limit(10)
                ->get();
          @endphp
          @if($eventDetails->isNotEmpty())
            <ul class="mb-0">
              @foreach($eventDetails as $evt)
                <li>
                  @if($evt->io_slug)
                    <a href="{{ url('/' . $evt->io_slug) }}">{{ $evt->io_title ?: '[Untitled]' }}</a>
                  @elseif($evt->io_title)
                    {{ $evt->io_title }}
                  @else
                    Event #{{ $evt->event_id }}
                  @endif
                  @if($evt->date_display)
                    &mdash; {{ $evt->date_display }}
                  @elseif($evt->start_date || $evt->end_date)
                    &mdash; {{ $evt->start_date ?? '' }}{{ ($evt->start_date && $evt->end_date) ? ' - ' : '' }}{{ $evt->end_date ?? '' }}
                  @endif
                </li>
              @endforeach
            </ul>
            @if($eventCount > 10)
              <p class="text-muted small mt-1">... and {{ $eventCount - 10 }} more event(s).</p>
            @endif
          @endif
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
