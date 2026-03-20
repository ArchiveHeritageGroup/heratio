<form id="search-box" class="d-flex my-1 my-lg-0 ms-lg-3 flex-grow-1" action="{{ route('glam.browse') }}" method="get" role="search" style="max-width:600px;">
  <input type="hidden" name="topLod" value="0">
  <input type="hidden" name="sort" value="relevance">
  <h2 class="visually-hidden">Search</h2>
  <div class="input-group input-group-sm flex-nowrap">
    {{-- Search options dropdown --}}
    <button id="search-box-options" class="btn atom-btn-secondary dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="height:40px;font-size:.7rem;">
      <i class="fas fa-cog" aria-hidden="true"></i>
      <span class="visually-hidden">Search options</span>
    </button>
    <div class="dropdown-menu mt-2" aria-labelledby="search-box-options">
      <div class="px-3 py-2">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="searchScope" id="search-realm-global" checked value="">
          <label class="form-check-label" for="search-realm-global">Global search</label>
        </div>
      </div>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item" href="{{ route('search.advanced') }}">
        <i class="fas fa-sliders-h me-1"></i>Advanced search
      </a>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item" href="#" onclick="event.preventDefault(); var m=document.getElementById('semanticSearchModal'); if(m) new bootstrap.Modal(m).show();">
        <i class="fas fa-brain me-1"></i>Semantic search
      </a>
      <div class="dropdown-divider"></div>
      <div class="px-3 py-2">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="semantic-search-toggle" name="semantic" value="1" {{ request('semantic') == '1' ? 'checked' : '' }}>
          <label class="form-check-label" for="semantic-search-toggle">Expand search with synonyms</label>
        </div>
      </div>
    </div>
    {{-- Search input with autocomplete --}}
    <input id="search-box-input" type="search" class="form-control dropdown-toggle py-0" name="query" autocomplete="off" placeholder="Search..." aria-label="Search" value="{{ request('query') }}" data-url="{{ url('/search/autocomplete') }}" data-bs-toggle="dropdown" aria-expanded="false" style="height:40px;font-size:.75rem;padding:0 6px;">
    <ul id="search-box-results" class="dropdown-menu mt-2" aria-labelledby="search-box-input"></ul>
    <button class="btn atom-btn-secondary py-0 px-2" type="submit" style="height:40px;font-size:.7rem;">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">Search</span>
    </button>
  </div>
</form>
