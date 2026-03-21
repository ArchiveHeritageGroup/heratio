<form
  id="search-box"
  class="d-flex flex-grow-1 my-2"
  role="search"
  action="{{ route('glam.browse') }}">
  <h2 class="visually-hidden">Search</h2>
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
      <span class="visually-hidden">Search options</span>
    </button>
    <div class="dropdown-menu mt-2" aria-labelledby="search-box-options">
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
            Global search
          </label>
        </div>
      </div>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item" href="{{ route('search.advanced') }}">
        Advanced search
      </a>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item" href="#" id="semantic-search-dropdown-link" onclick="event.preventDefault(); var btn = document.getElementById('openSemanticSearchBtn'); if (btn) { btn.click(); } else if (typeof openSemanticModal === 'function') { openSemanticModal(); }">
        <i class="fas fa-brain me-1" aria-hidden="true"></i>
        Semantic search
      </a>
      <div class="dropdown-divider"></div>
      <div class="px-3 py-2">
        <div class="form-check form-switch">
          <input
            class="form-check-input"
            type="checkbox"
            id="semantic-search-toggle"
            name="semantic"
            value="1"
            {{ request('semantic') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="semantic-search-toggle">
            Expand search with synonyms
          </label>
        </div>
      </div>
    </div>
    <input
      id="search-box-input"
      class="form-control form-control-sm dropdown-toggle"
      type="search"
      name="query"
      autocomplete="off"
      value="{{ request('query') }}"
      placeholder="Search"
      data-url="{{ url('/search/autocomplete') }}"
      data-bs-toggle="dropdown"
      aria-label="Search"
      aria-expanded="false">
    <ul id="search-box-results" class="dropdown-menu mt-2" aria-labelledby="search-box-input"></ul>
    <button class="btn btn-sm atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">Search in browse page</span>
    </button>
  </div>
</form>
