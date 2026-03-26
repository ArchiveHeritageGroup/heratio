<li class="nav-item dropdown d-flex flex-column">
  <a
    class="nav-link dropdown-toggle d-flex align-items-center p-0"
    href="#"
    id="language-menu"
    role="button"
    data-bs-toggle="dropdown"
    aria-expanded="false">
    <i
      class="fas fa-2x fa-fw fa-globe-europe px-0 px-lg-2 py-2"
      data-bs-toggle="tooltip"
      data-bs-placement="bottom"
      data-bs-custom-class="d-none d-lg-block"
      title="{{ __('Language') }}"
      aria-hidden="true">
    </i>
    <span class="d-lg-none mx-1" aria-hidden="true">
      {{ __('Language') }}
    </span>
    <span class="visually-hidden">
      {{ __('Language') }}
    </span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="language-menu">
    <li>
      <h6 class="dropdown-header">
        {{ __('Language') }}
      </h6>
    </li>
    @foreach($langCodes as $value)
      <li>
        <a href="{{ request()->fullUrlWithQuery(['sf_culture' => $value]) }}" class="dropdown-item">
          {{ ucfirst(\Locale::getDisplayLanguage($value, $value)) }}
        </a>
      </li>
    @endforeach
  </ul>
</li>
