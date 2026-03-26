<div class="field mb-3">

  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Alternative identifier(s)') }}</h3>

  <div>
    @foreach($resource->getProperties(null, 'alternativeIdentifiers') as $item)
      <div class="field">
        <h3>{{ $item->name ?? '' }}</h3>
        <div>{{ $item->value ?? '' }}</div>
      </div>
    @endforeach
  </div>

</div>
