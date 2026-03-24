@extends('theme::layouts.1col')

@section('title', 'Semantic Search')
@section('body-class', 'admin ric')

@push('styles')
<link rel="stylesheet" href="/vendor/ahg-ric/css/ric-explorer.css">
@endpush

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-brain me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Semantic Search</h1>
      <span class="small text-muted">Search the archives using natural language</span>
    </div>
  </div>

  {{-- Search Card --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0">
        <i class="fas fa-project-diagram me-2"></i>
        Semantic Search
      </h5>
      <small>Search the archives using natural language</small>
    </div>
    <div class="card-body">
      {{-- Search Input --}}
      <div class="row mb-3">
        <div class="col">
          <div class="input-group input-group-lg">
            <input
              type="text"
              id="ric-search-input"
              class="form-control"
              placeholder="e.g., records created by Hennie Pieterse"
              autocomplete="off"
              value="{{ request('q') }}"
            />
            <button type="button" id="ric-search-btn" class="btn atom-btn-primary">
              <i class="fas fa-search me-1"></i>
              Search
            </button>
          </div>
        </div>
      </div>

      {{-- Quick Examples --}}
      <div class="mb-3">
        <small class="text-muted me-2">Try:</small>
        <button class="btn btn-sm btn-outline-secondary ric-example" data-query="all fonds">All fonds</button>
        <button class="btn btn-sm btn-outline-secondary ric-example" data-query="records from 1948-1994">Apartheid era</button>
        <button class="btn btn-sm btn-outline-secondary ric-example" data-query="records about mining">Mining</button>
        <button class="btn btn-sm btn-outline-secondary ric-example" data-query="heritage assets">Heritage assets</button>
      </div>
    </div>
  </div>

  {{-- Suggestions Dropdown --}}
  <div id="ric-search-suggestions" class="ric-suggestions-dropdown"></div>

  {{-- Results --}}
  <div id="ric-results-container" class="card" style="display: none;">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span id="ric-result-count">0 results</span>
      <button type="button" id="ric-clear-btn" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-times me-1"></i>Clear
      </button>
    </div>
    <div class="card-body">
      <div id="ric-results-list"></div>
      <div id="ric-results-facets" class="ric-facets"></div>
    </div>
  </div>

  {{-- Loading --}}
  <div id="ric-loading" class="text-center py-5" style="display: none;">
    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
    <p class="mt-2 text-muted">Searching...</p>
  </div>

  {{-- Help Section --}}
  <div id="ric-help" class="row mt-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <i class="fas fa-user fa-2x mb-3" style="color:var(--ahg-primary)"></i>
          <h6>By Creator</h6>
          <p class="small text-muted">Find records by who created them</p>
          <code class="small">records created by John Smith</code>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <i class="fas fa-book fa-2x mb-3" style="color:var(--ahg-primary)"></i>
          <h6>By Subject</h6>
          <p class="small text-muted">Find records about a topic</p>
          <code class="small">records about agriculture</code>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body text-center">
          <i class="fas fa-calendar fa-2x mb-3" style="color:var(--ahg-primary)"></i>
          <h6>By Date</h6>
          <p class="small text-muted">Find records from a time period</p>
          <code class="small">records between 1960-1980</code>
        </div>
      </div>
    </div>
  </div>

  {{-- SPARQL Display --}}
  <div class="mt-4">
    <button type="button" id="ric-sparql-toggle" class="btn btn-sm btn-link text-muted">
      <i class="fas fa-code me-1"></i>View SPARQL Query
    </button>
    <pre id="ric-sparql-code" class="bg-dark text-light p-3 rounded mt-2" style="display: none; font-size: 12px;"></pre>
  </div>

  {{-- Navigation --}}
  <div class="mt-4 mb-3">
    <a href="{{ route('ric.index') }}" class="btn atom-btn-secondary">
      <i class="fas fa-arrow-left me-1"></i> Back to RiC Dashboard
    </a>
    <a href="{{ route('ric.explorer') }}" class="btn atom-btn-secondary">
      <i class="fas fa-project-diagram me-1"></i> RiC Explorer
    </a>
  </div>
@endsection

@push('scripts')
<script>
(function() {
  'use strict';

  var API_URL = @json($searchApiUrl);
  var input = document.getElementById('ric-search-input');
  var btn = document.getElementById('ric-search-btn');
  var resultsContainer = document.getElementById('ric-results-container');
  var resultsList = document.getElementById('ric-results-list');
  var resultCount = document.getElementById('ric-result-count');
  var resultsFacets = document.getElementById('ric-results-facets');
  var loading = document.getElementById('ric-loading');
  var help = document.getElementById('ric-help');
  var sparqlCode = document.getElementById('ric-sparql-code');
  var suggestionsDropdown = document.getElementById('ric-search-suggestions');
  var suggestionTimeout = null;

  // Type CSS classes
  var typeClasses = {
    'RecordSet': 'ric-type-recordset',
    'Record': 'ric-type-record',
    'RecordPart': 'ric-type-record',
    'Person': 'ric-type-person',
    'CorporateBody': 'ric-type-corporatebody',
    'Family': 'ric-type-family',
    'Place': 'ric-type-place'
  };

  function search(query) {
    if (!query.trim()) return;

    loading.style.display = 'block';
    resultsContainer.style.display = 'none';
    help.style.display = 'none';
    suggestionsDropdown.style.display = 'none';

    fetch(API_URL + '/search', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({query: query})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      loading.style.display = 'none';
      displayResults(data);
      sparqlCode.textContent = data.sparql || '';
    })
    .catch(function(err) {
      loading.style.display = 'none';
      resultsContainer.style.display = 'block';
      resultsList.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Search failed. The semantic search API may be unavailable. Please try again later.</div>';
    });
  }

  function displayResults(data) {
    resultsContainer.style.display = 'block';
    resultCount.textContent = (data.count || 0) + ' result' + ((data.count || 0) !== 1 ? 's' : '') + ' found';

    if (!data.results || data.results.length === 0) {
      resultsList.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No results found. Try different keywords.</div>';
      resultsFacets.innerHTML = '';
      return;
    }

    var html = '';
    data.results.forEach(function(r) {
      var typeClass = typeClasses[r.type] || 'ric-type-recordset';

      html += '<div class="ric-result-item">';
      html += '<a href="' + escapeHtml(r.atomUrl || '#') + '" class="ric-result-title">' + escapeHtml(r.title) + '</a>';
      html += '<div class="ric-result-meta">';
      html += '<span class="ric-result-type ' + typeClass + '">' + escapeHtml(r.type) + '</span>';

      if (r.identifier) {
        html += '<span><i class="fas fa-tag me-1"></i>' + escapeHtml(r.identifier) + '</span>';
      }
      if (r.date) {
        html += '<span><i class="fas fa-calendar me-1"></i>' + escapeHtml(r.date) + '</span>';
      }
      if (r.creator) {
        html += '<span><i class="fas fa-user me-1"></i>' + escapeHtml(r.creator) + '</span>';
      }
      if (r.place) {
        html += '<span><i class="fas fa-map-marker-alt me-1"></i>' + escapeHtml(r.place) + '</span>';
      }

      html += '</div></div>';
    });

    resultsList.innerHTML = html;

    // Build facets
    if (data.facets && Object.keys(data.facets).length > 0) {
      var facetHtml = '';

      if (data.facets.type) {
        facetHtml += '<div class="ric-facet-group"><div class="ric-facet-label">Types</div><div class="ric-facet-values">';
        for (var type in data.facets.type) {
          facetHtml += '<span class="ric-facet-item">' + type + ' (' + data.facets.type[type] + ')</span>';
        }
        facetHtml += '</div></div>';
      }

      if (data.facets.decade) {
        facetHtml += '<div class="ric-facet-group"><div class="ric-facet-label">Decades</div><div class="ric-facet-values">';
        var decades = Object.keys(data.facets.decade).sort();
        decades.forEach(function(decade) {
          facetHtml += '<span class="ric-facet-item">' + decade + ' (' + data.facets.decade[decade] + ')</span>';
        });
        facetHtml += '</div></div>';
      }

      resultsFacets.innerHTML = facetHtml;
    } else {
      resultsFacets.innerHTML = '';
    }
  }

  // Suggestions
  function fetchSuggestions(query) {
    clearTimeout(suggestionTimeout);
    if (query.length < 2) {
      suggestionsDropdown.style.display = 'none';
      return;
    }

    suggestionTimeout = setTimeout(function() {
      fetch(API_URL + '/suggest?q=' + encodeURIComponent(query))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.suggestions && data.suggestions.length > 0) {
            var html = '';
            data.suggestions.forEach(function(s) {
              html += '<div class="ric-suggestion-item" data-query="' + escapeHtml(s.text) + '">';
              html += '<span>' + escapeHtml(s.text) + '</span>';
              html += '<span class="ric-suggestion-type">' + escapeHtml(s.type) + '</span>';
              html += '</div>';
            });
            suggestionsDropdown.innerHTML = html;
            suggestionsDropdown.style.display = 'block';

            suggestionsDropdown.querySelectorAll('.ric-suggestion-item').forEach(function(item) {
              item.addEventListener('click', function() {
                input.value = this.dataset.query;
                suggestionsDropdown.style.display = 'none';
                search(this.dataset.query);
              });
            });
          } else {
            suggestionsDropdown.style.display = 'none';
          }
        })
        .catch(function() {
          suggestionsDropdown.style.display = 'none';
        });
    }, 200);
  }

  function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Event listeners
  btn.addEventListener('click', function() { search(input.value); });
  input.addEventListener('keypress', function(e) { if (e.key === 'Enter') search(input.value); });
  input.addEventListener('input', function() { fetchSuggestions(this.value); });

  document.addEventListener('click', function(e) {
    if (!suggestionsDropdown.contains(e.target) && e.target !== input) {
      suggestionsDropdown.style.display = 'none';
    }
  });

  document.querySelectorAll('.ric-example').forEach(function(el) {
    el.addEventListener('click', function() {
      input.value = this.dataset.query;
      search(this.dataset.query);
    });
  });

  document.getElementById('ric-clear-btn').addEventListener('click', function() {
    resultsContainer.style.display = 'none';
    help.style.display = 'flex';
    resultsFacets.innerHTML = '';
    input.value = '';
    input.focus();
  });

  document.getElementById('ric-sparql-toggle').addEventListener('click', function() {
    var code = document.getElementById('ric-sparql-code');
    if (code.style.display === 'none') {
      code.style.display = 'block';
      this.innerHTML = '<i class="fas fa-code me-1"></i>Hide SPARQL Query';
    } else {
      code.style.display = 'none';
      this.innerHTML = '<i class="fas fa-code me-1"></i>View SPARQL Query';
    }
  });

  // Auto-search if query parameter is present
  if (input.value.trim()) {
    search(input.value.trim());
  } else {
    input.focus();
  }
})();
</script>
@endpush
