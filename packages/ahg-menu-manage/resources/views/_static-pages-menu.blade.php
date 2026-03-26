<section class="card mb-3">
  <h2 class="h5 p-3 mb-0">
    {{ $menu->getLabel() }}
  </h2>
  <div class="list-group list-group-flush">
    @foreach($menu->getChildren() as $item)
      <a
        class="list-group-item list-group-item-action"
        href="{{ $item->getPath(['getUrl' => true, 'resolveAlias' => true]) }}">
        {{ $item->getLabel(['cultureFallback' => true]) }}
      </a>
    @endforeach
  </div>
</section>
