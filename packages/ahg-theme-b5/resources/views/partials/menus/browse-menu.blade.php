@php
  $browseItems = $themeData['browseMenu'] ?? [];
@endphp

<div class="dropdown my-2 me-3">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="browse-menu" data-bs-toggle="dropdown" aria-expanded="false">
    Browse
  </button>
  <ul class="dropdown-menu mt-2" aria-labelledby="browse-menu">
    <li><h6 class="dropdown-header">Browse</h6></li>
    @foreach($browseItems as $item)
      <li id="node_{{ $item->name }}">
        <a class="dropdown-item" href="{{ \AhgCore\Services\MenuService::resolvePath($item->path) }}">
          {{ $item->label }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
