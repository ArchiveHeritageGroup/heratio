{{--
  Clipboard toggle button.
  Usage: @include('ahg-core::clipboard._button', ['slug' => $io->slug, 'type' => 'informationObject', 'wide' => true])

  Parameters:
    $slug  - entity slug
    $type  - clipboard type: informationObject, actor, repository, accession
    $wide  - (optional) show label text beside icon (default: false)
--}}
@php
  $wide = $wide ?? false;
  $type = $type ?? 'informationObject';
@endphp

<button
  class="btn atom-btn-white clipboard{{ $wide ? ' w-100' : '' }}"
  data-clipboard-slug="{{ $slug }}"
  data-clipboard-type="{{ $type }}"
  @unless($wide) data-tooltip="true" @endunless
  data-title="{{ $wide ? 'Add' : 'Add to clipboard' }}"
  data-alt-title="{{ $wide ? 'Remove' : 'Remove from clipboard' }}"
  title="{{ $wide ? 'Add to clipboard' : 'Add to clipboard' }}">
  <i class="fas fa-lg fa-paperclip" aria-hidden="true"></i>
  <span class="{{ $wide ? 'ms-2' : 'visually-hidden' }}">
    {{ $wide ? 'Add to clipboard' : 'Add to clipboard' }}
  </span>
</button>
