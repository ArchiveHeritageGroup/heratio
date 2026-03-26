@php
    if (isset($active) && isset($options[$active])) {
        // active is already set
    } elseif (request()->has($param) && isset($options[request()->input($param)])) {
        $active = request()->input($param);
    } else {
        $active = array_key_first($options);
    }
@endphp

<div class="dropdown d-inline-block">
  <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="{{ $param }}-button" data-bs-toggle="dropdown" aria-expanded="false">
    {{ $label }}: {{ $options[$active] ?? '' }}
  </button>
  <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="{{ $param }}-button">
    @foreach($options as $key => $value)
      <li>
        <a
          href="{{ request()->fullUrlWithQuery([$param => $key]) }}"
          class="dropdown-item{{ $active == $key ? ' active' : '' }}">
          {{ $value }}
        </a>
      </li>
    @endforeach
  </ul>
</div>
