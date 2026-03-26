@php
  $url = route($module . '.' . $action, $getParams ?? []);
@endphp
<a
  href="{{ $url }}"
  class="btn btn-sm atom-btn-white align-self-start mw-100 filter-tag d-flex">
  <span class="visually-hidden">
    {{ __('Remove filter:') }}
  </span>
  <span class="text-truncate d-inline-block">
    {{ $label ?: ($object->authorized_form_of_name ?? $object->title ?? '') }}
  </span>
  <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
</a>
