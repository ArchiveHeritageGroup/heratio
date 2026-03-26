<div class="field">
  <h3>{{ __('Creator(s)') }}</h3>
  <div>
    <ul>
      @foreach($ancestor->getCreators() as $item)
        <li>
          @if(0 < count($resource->getCreators()))
            <a href="{{ route('actor.show', $item->slug ?? $item->id) }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a>
          @else
            <a href="{{ route('actor.show', $item->slug ?? $item->id) }}" title="{{ __('Inherited from %1%', ['%1%' => $ancestor->authorized_form_of_name ?? $ancestor->title ?? '']) }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a>
          @endif
        </li>
      @endforeach
    </ul>
  </div>
</div>
