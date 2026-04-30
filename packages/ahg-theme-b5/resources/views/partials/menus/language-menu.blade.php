@php
  // Get enabled languages from settings
  $langCodes = \Illuminate\Support\Facades\DB::table('setting')
    ->where('scope', 'i18n_languages')
    ->where('editable', 1)
    ->pluck('name')
    ->toArray();
@endphp

@if(count($langCodes) > 1)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="language-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-globe-europe px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="{{ __('Language') }}" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">{{ __('Language') }}</span>
    <span class="visually-hidden">{{ __('Language') }}</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="language-menu">
    <li><h6 class="dropdown-header">{{ __('Language') }}</h6></li>
    @foreach($langCodes as $code)
      <li>
        <a class="dropdown-item {{ $code === app()->getLocale() ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sf_culture' => $code]) }}">
          {{ ucfirst(\Locale::getDisplayLanguage($code, $code)) }}
        </a>
      </li>
    @endforeach
  </ul>
</li>
@endif
