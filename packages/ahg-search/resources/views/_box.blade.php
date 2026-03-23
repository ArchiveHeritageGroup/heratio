<form
  id="search-box"
  class="d-flex flex-grow-1 my-2"
  role="search"
  action="{{ route('informationobject.browse') }}">
  <h2 class="visually-hidden">{{ __('Search') }}</h2>
  <input type="hidden" name="topLod" value="0">
  <input type="hidden" name="sort" value="relevance">
  <div class="input-group flex-nowrap">
    <button
      id="search-box-options"
      class="btn btn-sm atom-btn-secondary dropdown-toggle"
      type="button"
      data-bs-toggle="dropdown"
      data-bs-auto-close="outside"
      aria-expanded="false">
      <i class="fas fa-cog" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search options') }}</span>
    </button>
    <div class="dropdown-menu mt-2" aria-labelledby="search-box-options">
      @if(config('app.multi_repository'))
        <div class="px-3 py-2">
          <div class="form-check">
            <input
              class="form-check-input"
              type="radio"
              name="repos"
              id="search-realm-global"
              checked
              value>
            <label class="form-check-label" for="search-realm-global">
              {{ __('Global search') }}
            </label>
          </div>
          @if(isset($repository))
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="repos"
                id="search-realm-repo"
                value="{{ $repository->id }}">
              <label class="form-check-label" for="search-realm-repo">
                {!! __('Search <span>%1%</span>', ['%1%' => render_title($repository)]) !!}
              </label>
            </div>
          @endif
          @if(isset($altRepository))
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="repos"
                id="search-realm-alt-repo"
                value="{{ $altRepository->id }}">
              <label class="form-check-label" for="search-realm-alt-repo">
                {!! __('Search <span>%1%</span>', ['%1%' => render_title($altRepository)]) !!}
              </label>
            </div>
          @endif
        </div>
        <div class="dropdown-divider"></div>
      @endif
      <a class="dropdown-item" href="{{ route('informationobject.browse', ['showAdvanced' => true, 'topLod' => 0]) }}">
        {{ __('Advanced search') }}
      </a>
    </div>
    <input
      id="search-box-input"
      class="form-control form-control-sm dropdown-toggle"
      type="search"
      name="query"
      autocomplete="off"
      value="{{ request('query') }}"
      placeholder="{{ config('app.ui_label_globalSearch', 'Search') }}"
      data-url="{{ route('search.autocomplete') }}"
      data-bs-toggle="dropdown"
      aria-label="{{ config('app.ui_label_globalSearch', 'Search') }}"
      aria-expanded="false">
    <ul id="search-box-results" class="dropdown-menu mt-2" aria-labelledby="search-box-input"></ul>
    <button class="btn btn-sm atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search in browse page') }}</span>
    </button>
  </div>
</form>
