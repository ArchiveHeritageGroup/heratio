<form
  id="search-box"
  class="d-flex flex-grow-1 my-2"
  role="search"
  action="@php echo url_for('@glam_browse'); @endphp">
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
      @if(sfConfig::get('app_multi_repository'))
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
              {{ __('Global search') }} <span class="badge bg-secondary ms-1">Optional</span>
            </label>
          </div>
          @if(isset($repository))
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="repos"
                id="search-realm-repo"
                value="@php echo $repository->id; @endphp">
              <label class="form-check-label" for="search-realm-repo">
                {{ __('Search <span>%1%</span>', ['%1%' => render_title($repository)]) }} <span class="badge bg-secondary ms-1">Optional</span>
              </label>
            </div>
          @endforeach
          @if(isset($altRepository))
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="repos"
                id="search-realm-alt-repo"
                value="@php echo $altRepository->id; @endphp">
              <label class="form-check-label" for="search-realm-alt-repo">
                {{ __('Search <span>%1%</span>', ['%1%' => render_title($altRepository)]) }} <span class="badge bg-secondary ms-1">Optional</span>
              </label>
            </div>
          @endforeach
        </div>
        <div class="dropdown-divider"></div>
      @endforeach
      <a class="dropdown-item" href="@php echo url_for('@glam_browse') . '?showAdvanced=true&topLevel=0'; @endphp">
        {{ __('Advanced search') }}
      </a>
      <div class="dropdown-divider"></div>
      <a class="dropdown-item" href="#" id="semantic-search-dropdown-link" onclick="event.preventDefault(); var btn = document.getElementById('openSemanticSearchBtn'); if (btn) { btn.click(); } else if (typeof openSemanticModal === 'function') { openSemanticModal(); }">
        <i class="fas fa-brain me-1" aria-hidden="true"></i>
        {{ __('Semantic search') }}
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
            @php echo ($sf_request->getParameter('semantic') == '1') ? 'checked' : ''; @endphp>
          <label class="form-check-label" for="semantic-search-toggle">
            {{ __('Expand search with synonyms') }} <span class="badge bg-secondary ms-1">Optional</span>
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
      value="@php echo $sf_request->query; @endphp"
      placeholder="@php echo sfConfig::get('app_ui_label_globalSearch'); @endphp"
      data-url="@php echo route('search.autocomplete'); @endphp"
      data-bs-toggle="dropdown"
      aria-label="@php echo sfConfig::get('app_ui_label_globalSearch'); @endphp"
      aria-expanded="false">
    <ul id="search-box-results" class="dropdown-menu mt-2" aria-labelledby="search-box-input"></ul>
    <button class="btn btn-sm atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search in browse page') }}</span>
    </button>
  </div>
</form>

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
(function() {
  var searchBox = document.getElementById('search-box');
  var queryInput = document.getElementById('search-box-input');
  var semanticToggle = document.getElementById('semantic-search-toggle');

  if (!searchBox || !queryInput || !semanticToggle) return;

  searchBox.addEventListener('submit', function(e) {
    var query = queryInput.value.trim();
    var semanticEnabled = semanticToggle.checked;

    if (!semanticEnabled || !query) return;

    // Prevent immediate submission
    e.preventDefault();

    // Fetch expansions and then submit
    fetch('@php echo route('semanticSearchAdmin.testExpand'); @endphp?query=' + encodeURIComponent(query))
      .then(function(response) { return response.json(); })
      .then(function(data) {
        if (data.success && Object.keys(data.expansions).length > 0) {
          // Build expanded query
          var expandedTerms = [];
          for (var term in data.expansions) {
            expandedTerms = expandedTerms.concat(data.expansions[term]);
          }

          if (expandedTerms.length > 0) {
            queryInput.value = query + ' ' + expandedTerms.join(' ');
          }
        }

        // Disable semantic param since we already expanded
        semanticToggle.disabled = true;

        // Submit the form
        searchBox.submit();
      })
      .catch(function(error) {
        // On error, submit with original query
        searchBox.submit();
      });
  });
})();
</script>
