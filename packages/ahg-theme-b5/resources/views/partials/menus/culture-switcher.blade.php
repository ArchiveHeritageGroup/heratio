{{--
  Culture switcher — dropdown listing enabled UI languages.
  Reads enabled languages from ahg_settings (scope = 'i18n_languages', editable = 1)
  with a hard fallback to ['en'] if the setting table is unavailable.

  Selecting a language posts to /set-locale which writes session('locale') and
  redirects back. The SetLocale middleware then applies the culture to every
  subsequent request.
--}}
@php
  // Map of culture codes to display names. Extend this list as new translation
  // files are added under /lang/{code}.json. Codes must match the
  // ahg_settings.i18n_languages 'name' values.
  $cultureLabels = [
      'en'  => 'English',
      'af'  => 'Afrikaans',
      'fr'  => 'Français',
      'nl'  => 'Nederlands',
      'pt'  => 'Português',
      'es'  => 'Español',
      'de'  => 'Deutsch',
      'zu'  => 'isiZulu',
      'xh'  => 'isiXhosa',
      'st'  => 'Sesotho',
      'tn'  => 'Setswana',
      'nso' => 'Sepedi',
      'ts'  => 'Xitsonga',
      'ss'  => 'siSwati',
      've'  => 'Tshivenda',
      'nr'  => 'isiNdebele (Southern)',
      'nd'  => 'isiNdebele (Northern) / Sindebele',
      'kj'  => 'Oshikwanyama',
      'sn'  => 'chiShona',
      'umb' => 'Umbundu',
      'khi' => 'Khoisan (family)',
      'ne'  => 'Nepali',
      'sw'  => 'Kiswahili',
      'ar'  => 'العربية',
  ];

  // Discover which languages are actually enabled. Settings table is the
  // source of truth; fall back to checking which lang/*.json files exist.
  $enabled = [];
  try {
      if (\Illuminate\Support\Facades\Schema::hasTable('setting')) {
          $enabled = \Illuminate\Support\Facades\DB::table('setting')
              ->where('scope', 'i18n_languages')
              ->where('editable', 1)
              ->pluck('name')
              ->toArray();
      }
  } catch (\Throwable $e) { /* fall through */ }

  if (empty($enabled)) {
      // Fallback: enumerate /lang/*.json files
      $files = glob(base_path('lang/*.json')) ?: [];
      $enabled = array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), $files);
  }

  if (empty($enabled)) {
      $enabled = ['en'];
  }

  $current = app()->getLocale();
  $currentLabel = $cultureLabels[$current] ?? strtoupper($current);
@endphp

@if(count($enabled) > 1)
  <ul class="navbar-nav me-2">
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" href="#" id="cultureSwitcher" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Language') }}">
        <i class="fas fa-globe me-1" aria-hidden="true"></i>
        <span class="d-none d-lg-inline">{{ $currentLabel }}</span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="cultureSwitcher">
        @foreach($enabled as $code)
          @php $label = $cultureLabels[$code] ?? strtoupper($code); @endphp
          <li>
            <form method="POST" action="{{ url('/set-locale') }}" class="d-inline">
              @csrf
              <input type="hidden" name="culture" value="{{ $code }}">
              <input type="hidden" name="redirect_to" value="{{ url()->full() }}">
              <button type="submit" class="dropdown-item {{ $code === $current ? 'active' : '' }}">
                @if($code === $current)
                  <i class="fas fa-check me-2"></i>
                @else
                  <span class="me-2" style="display:inline-block;width:1em;"></span>
                @endif
                {{ $label }}
                <small class="text-muted ms-2">{{ $code }}</small>
              </button>
            </form>
          </li>
        @endforeach
        <li><hr class="dropdown-divider"></li>
        <li class="px-3 py-1">
          <small class="text-muted d-block" style="line-height:1.3;">
            UI translations courtesy of
            <a href="https://www.accesstomemory.org/" target="_blank" rel="noopener" class="text-decoration-none">AtoM</a>,
            <a href="https://www.artefactual.com/" target="_blank" rel="noopener" class="text-decoration-none">Artefactual</a>,
            and the AtoM community.<br>
            South African languages by The AHG.
          </small>
        </li>
      </ul>
    </li>
  </ul>
@endif
