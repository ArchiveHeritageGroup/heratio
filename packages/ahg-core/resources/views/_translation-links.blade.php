<div class="dropdown d-inline-block mb-3 translation-links">
  <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" id="translation-links-button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-globe-europe me-1" aria-hidden="true"></i>
    {{ __('Other languages available') }}
  </button>
  <ul class="dropdown-menu mt-2" aria-labelledby="translation-links-button">
    @foreach($translations as $code => $value)
      <li>
        <a href="{{ request()->fullUrlWithQuery(['sf_culture' => $code]) }}" class="dropdown-item">{!! ($value['language'] ?? $code) . ' &raquo; ' . ($value['name'] ?? '') !!}</a>
      </li>
    @endforeach
  </ul>
</div>
