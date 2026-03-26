<div class="field">

  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Alternative identifier(s)') }}</h3>

  <div>
    @foreach($resource->getAlternativeIdentifiers() as $item)
      <div class="field">
        <h3 class="fs-6 fw-semibold text-body-secondary">{{ $item->getType(['cultureFallback' => true]) }}</h3>
        <div>{{ $item->getName(['cultureFallback' => true]) }}</div>
      </div>
      @if(!empty($note = $item->getNote(['cultureFallback' => true])))
        <div>{!! $note !!}</div>
      @endif
    @endforeach
  </div>

</div>
