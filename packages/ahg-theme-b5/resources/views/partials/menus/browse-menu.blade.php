@php
  $browseItems = $themeData['browseMenu'] ?? [];
@endphp

<div class="dropdown my-2 me-3">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="browse-menu" data-bs-toggle="dropdown" aria-expanded="false">
    {{ __('Browse') }}
  </button>
  <ul class="dropdown-menu mt-2" aria-labelledby="browse-menu">
    <li><h6 class="dropdown-header">{{ __('Browse') }}</h6></li>
    @foreach($browseItems as $item)
      <li id="node_{{ $item->name }}">
        <a class="dropdown-item" href="{{ \AhgCore\Services\MenuService::resolvePath($item->path) }}">
          {{-- Run the en label through __() so it picks up lang/{locale}.json
               and the setting_i18n hydrator (no duplicate menu_i18n rows). --}}
          {{ $item->label ? __($item->label) : $item->name }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
