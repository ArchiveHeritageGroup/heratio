@extends('ahg-theme-b5::layout')

@section('title', 'Discovery')

@section('content')
<div class="container-fluid mt-3">

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-compass me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Discover') }}</h1>
      <span class="text-muted">Search across collections using natural language</span>
    </div>
  </div>

  {{-- Search Input --}}
  <div class="card mb-4 shadow-sm">
    <div class="card-body p-4">
      <form method="GET" id="discoveryForm" class="mb-0">
        <div class="input-group input-group-lg">
          <span class="input-group-text bg-white border-end-0">
            <i class="fas fa-search text-muted"></i>
          </span>
          <input type="text" name="q" id="discovery-query"
                 class="form-control border-start-0 ps-0"
                 placeholder="{{ __('Ask a question... e.g. &quot;photographs of District Six in the 1960s&quot;') }}"
                 value="{{ e($query ?? '') }}" autocomplete="off" autofocus>
          <select name="type" class="form-select" style="max-width: 200px;">
            <option value="all" {{ ($type ?? '') === 'all' ? 'selected' : '' }}>{{ __('All types') }}</option>
            <option value="information_object" {{ ($type ?? '') === 'information_object' ? 'selected' : '' }}>{{ __('Archival descriptions') }}</option>
            <option value="actor" {{ ($type ?? '') === 'actor' ? 'selected' : '' }}>{{ __('Authority records') }}</option>
            <option value="repository" {{ ($type ?? '') === 'repository' ? 'selected' : '' }}>{{ __('Repositories') }}</option>
          </select>
          <button id="discovery-search-btn" class="btn btn-primary px-4" type="submit">
            <i class="fas fa-search me-1"></i> Discover
          </button>
        </div>
      </form>

      {{-- Autocomplete dropdown --}}
      <div id="suggestDropdown" class="dropdown-menu w-100" style="display:none;"></div>

      {{-- Search Mode Selector --}}
      <div class="mt-3 d-flex align-items-center gap-2">
        <small class="text-muted me-1"><i class="fas fa-sliders-h me-1"></i>Search mode:</small>
        <div class="btn-group btn-group-sm" role="group" id="discovery-mode-group">
          <button type="button" class="btn btn-outline-primary active" data-mode="standard" title="{{ __('Keyword search') }}">
            <i class="fas fa-search me-1"></i>Standard
          </button>
          <button type="button" class="btn btn-outline-primary" data-mode="semantic" title="{{ __('Standard + NER entity matching') }}">
            <i class="fas fa-brain me-1"></i>Semantic
          </button>
          <button type="button" class="btn btn-outline-primary" data-mode="vector" title="{{ __('Standard + Semantic + vector similarity') }}">
            <i class="fas fa-project-diagram me-1"></i>Vector
          </button>
        </div>
      </div>

      {{-- Query Expansion Info --}}
      <div id="discovery-expansion" class="mt-3 d-none">
        <small class="text-muted">
          <i class="fas fa-lightbulb me-1"></i>
          <span id="expansion-text"></span>
        </small>
      </div>
    </div>
  </div>

  {{-- Popular Topics --}}
  @if (!empty($popularTopics))
  <div id="discovery-popular" class="mb-4">
    <h5 class="text-muted mb-3">
      <i class="fas fa-fire-alt me-1"></i> Popular searches
    </h5>
    <div class="d-flex flex-wrap gap-2">
      @foreach ($popularTopics as $topic)
        <button class="btn btn-outline-secondary btn-sm discovery-topic-btn rounded-pill"
                data-query="{{ e($topic['query']) }}">
          {{ e($topic['query']) }}
          <span class="badge bg-light text-muted ms-1">{{ (int) $topic['count'] }}</span>
        </button>
      @endforeach
    </div>
  </div>
  @endif

  {{-- Loading Spinner --}}
  <div id="discovery-loading" class="text-center py-5 d-none">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Searching...</span>
    </div>
    <p class="text-muted mt-3">Searching across collections...</p>
  </div>

  {{-- AJAX Results Summary --}}
  <div id="discovery-summary" class="d-none mb-3">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <span id="result-count" class="fw-bold"></span>
        <span class="text-muted ms-2" id="result-time"></span>
      </div>
      <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn btn-outline-secondary active" id="view-grouped" title="{{ __('Group by collection') }}">
          <i class="fas fa-layer-group"></i>
        </button>
        <button type="button" class="btn btn-outline-secondary" id="view-flat" title="{{ __('Flat list') }}">
          <i class="fas fa-list"></i>
        </button>
      </div>
    </div>
  </div>

  {{-- AJAX Results Containers --}}
  <div id="discovery-results-grouped"></div>
  <div id="discovery-results-flat" class="d-none"></div>

  {{-- No Results --}}
  <div id="discovery-no-results" class="text-center py-5 d-none">
    <i class="fas fa-search fa-3x text-muted mb-3"></i>
    <h4 class="text-muted">{{ __('No results found') }}</h4>
    <p class="text-muted">Try different keywords or a broader search term.</p>
  </div>

  {{-- AJAX Pagination --}}
  <div id="discovery-pagination" class="d-none">
    <nav aria-label="{{ __('Discovery results pagination') }}">
      <ul class="pagination justify-content-center" id="pagination-list"></ul>
    </nav>
  </div>

  {{-- Server-rendered browse results (shown when JS search not active) --}}
  @if (!empty($query))
  <div id="browse-results">
    <div class="row">
      @if (!empty($counts))
      <div class="col-md-3">
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0">{{ __('Entity Type') }}</h6></div>
          <div class="list-group list-group-flush">
            <a href="?q={{ urlencode($query) }}&type=all" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'all' ? 'active' : '' }}">
              All <span class="badge bg-secondary">{{ array_sum($counts) }}</span>
            </a>
            <a href="?q={{ urlencode($query) }}&type=information_object" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'information_object' ? 'active' : '' }}">
              Archival descriptions <span class="badge bg-secondary">{{ $counts['information_object'] ?? 0 }}</span>
            </a>
            <a href="?q={{ urlencode($query) }}&type=actor" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'actor' ? 'active' : '' }}">
              Authority records <span class="badge bg-secondary">{{ $counts['actor'] ?? 0 }}</span>
            </a>
            <a href="?q={{ urlencode($query) }}&type=repository" class="list-group-item list-group-item-action d-flex justify-content-between {{ ($type ?? '') === 'repository' ? 'active' : '' }}">
              Repositories <span class="badge bg-secondary">{{ $counts['repository'] ?? 0 }}</span>
            </a>
          </div>
        </div>
      </div>
      @endif

      <div class="{{ !empty($counts) ? 'col-md-9' : 'col-12' }}">
        <p class="text-muted">{{ $total }} result(s) for "{{ e($query) }}"</p>

        @forelse ($results as $result)
        <div class="card mb-2">
          <div class="card-body p-3">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="mb-1">
                  @if (!empty($result->slug))
                    <a href="/{{ $result->slug }}">{{ e($result->label ?? $result->title ?? 'Untitled') }}</a>
                  @else
                    {{ e($result->label ?? $result->title ?? 'Untitled') }}
                  @endif
                </h6>
                <span class="badge bg-info">{{ e($result->entity_type ?? '') }}</span>
                @if (!empty($result->identifier))
                  <span class="badge bg-secondary">{{ e($result->identifier) }}</span>
                @endif
              </div>
            </div>
          </div>
        </div>
        @empty
        <div class="alert alert-info">No results found.</div>
        @endforelse

        @if (($totalPages ?? 1) > 1)
        <nav class="mt-3">
          <ul class="pagination">
            @if ($page > 1)
              <li class="page-item"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'type' => $type, 'page' => $page - 1]) }}">Prev</a></li>
            @endif
            @for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++)
              <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'type' => $type, 'page' => $i]) }}">{{ $i }}</a></li>
            @endfor
            @if ($page < $totalPages)
              <li class="page-item"><a class="page-link" href="?{{ http_build_query(['q' => $query, 'type' => $type, 'page' => $page + 1]) }}">Next</a></li>
            @endif
          </ul>
        </nav>
        @endif
      </div>
    </div>
  </div>
  @endif

</div>
@endsection

@push('styles')
<style>
/* Discovery Page Styles (migrated from ahgDiscoveryPlugin) */
.discovery-collection { border: 1px solid #e9ecef; border-radius: 0.5rem; margin-bottom: 1.25rem; overflow: hidden; }
.discovery-collection-header { background: #f8f9fa; padding: 0.75rem 1rem; border-bottom: 1px solid #e9ecef; cursor: pointer; }
.discovery-collection-header:hover { background: #e9ecef; }
.discovery-collection-header h5 { margin: 0; font-size: 0.95rem; }
.discovery-result { padding: 0.875rem 1rem; border-bottom: 1px solid #f0f0f0; transition: background 0.15s; }
.discovery-result:last-child { border-bottom: none; }
.discovery-result:hover { background: #fafbfc; }
.discovery-result-title { font-weight: 600; color: #0d6efd; text-decoration: none; font-size: 0.95rem; }
.discovery-result-title:hover { text-decoration: underline; }
.discovery-result-meta { font-size: 0.8rem; color: #6c757d; margin-top: 0.25rem; }
.discovery-result-meta span + span::before { content: "\00b7"; margin: 0 0.4rem; }
.discovery-result-scope { font-size: 0.85rem; color: #495057; margin-top: 0.35rem; line-height: 1.5; }
.discovery-result-scope mark { background: #fff3cd; padding: 0 2px; border-radius: 2px; }
.discovery-result-reasons { margin-top: 0.35rem; }
.discovery-result-reasons .badge { font-weight: 400; font-size: 0.7rem; }
.discovery-result-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.entity-tag { font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 3px; display: inline-block; margin: 1px; }
.entity-tag-PERSON { background: #d1ecf1; color: #0c5460; }
.entity-tag-ORG { background: #d4edda; color: #155724; }
.entity-tag-GPE { background: #fff3cd; color: #856404; }
.entity-tag-DATE { background: #e2e3e5; color: #383d41; }
.entity-tag-LOC { background: #cce5ff; color: #004085; }
.discovery-topic-btn:hover { background: #0d6efd; color: white; border-color: #0d6efd; }
#view-grouped.active, #view-flat.active { background: #0d6efd; color: white; border-color: #0d6efd; }
#discovery-mode-group .btn.active { background: #0d6efd; color: white; border-color: #0d6efd; }
</style>
@endpush

@push('scripts')
<script>
(function() {
  'use strict';

  var searchInput  = document.getElementById('discovery-query');
  var searchBtn    = document.getElementById('discovery-search-btn');
  var loadingEl    = document.getElementById('discovery-loading');
  var summaryEl    = document.getElementById('discovery-summary');
  var groupedEl    = document.getElementById('discovery-results-grouped');
  var flatEl       = document.getElementById('discovery-results-flat');
  var noResultsEl  = document.getElementById('discovery-no-results');
  var paginationEl = document.getElementById('discovery-pagination');
  var paginationList = document.getElementById('pagination-list');
  var expansionEl  = document.getElementById('discovery-expansion');
  var expansionText = document.getElementById('expansion-text');
  var popularEl    = document.getElementById('discovery-popular');
  var browseResults = document.getElementById('browse-results');
  var viewGrouped  = document.getElementById('view-grouped');
  var viewFlat     = document.getElementById('view-flat');
  var dropdown     = document.getElementById('suggestDropdown');
  var form         = document.getElementById('discoveryForm');

  var currentPage = 1;
  var currentQuery = '';
  var currentMode = 'standard';
  var searchTimer = null;
  var suggestTimer = null;
  var sessionId = Math.random().toString(36).substr(2, 12);

  // Prevent form submit, use AJAX instead
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    doSearch(1);
  });

  // Debounced auto-search
  searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    clearTimeout(suggestTimer);

    // Autocomplete suggestions
    var val = this.value.trim();
    if (val.length >= 2) {
      suggestTimer = setTimeout(function() {
        fetch('/discovery/suggest?q=' + encodeURIComponent(val))
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var items = data.suggestions || data;
            if (!items || !items.length) { dropdown.style.display = 'none'; return; }
            dropdown.innerHTML = items.map(function(item) {
              return '<a class="dropdown-item" href="/' + (item.slug || '#') + '">' +
                '<small class="text-muted">' + (item.type || '') + '</small> ' + escHtml(item.label || item.text || '') + '</a>';
            }).join('');
            dropdown.style.display = 'block';
          });
      }, 200);
    } else {
      dropdown.style.display = 'none';
    }

    if (val.length >= 3) {
      searchTimer = setTimeout(function() { doSearch(1); }, 600);
    }
  });

  document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target)) dropdown.style.display = 'none';
  });

  // Popular topic click
  document.querySelectorAll('.discovery-topic-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      searchInput.value = this.dataset.query;
      doSearch(1);
    });
  });

  // Mode toggle
  document.querySelectorAll('#discovery-mode-group button').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('#discovery-mode-group button').forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');
      currentMode = this.dataset.mode;
      if (currentQuery) { doSearch(1); }
    });
  });

  // View toggle
  viewGrouped.addEventListener('click', function() {
    viewGrouped.classList.add('active'); viewFlat.classList.remove('active');
    groupedEl.classList.remove('d-none'); flatEl.classList.add('d-none');
  });
  viewFlat.addEventListener('click', function() {
    viewFlat.classList.add('active'); viewGrouped.classList.remove('active');
    flatEl.classList.remove('d-none'); groupedEl.classList.add('d-none');
  });

  function doSearch(page) {
    var q = searchInput.value.trim();
    if (!q) return;

    currentQuery = q;
    currentPage = page || 1;
    dropdown.style.display = 'none';

    // Update URL
    var url = new URL(window.location);
    url.searchParams.set('q', q);
    if (page > 1) { url.searchParams.set('page', page); } else { url.searchParams.delete('page'); }
    if (currentMode !== 'standard') { url.searchParams.set('mode', currentMode); } else { url.searchParams.delete('mode'); }
    history.replaceState(null, '', url);

    // Hide browse results, show AJAX
    if (browseResults) browseResults.style.display = 'none';
    loadingEl.classList.remove('d-none');
    summaryEl.classList.add('d-none');
    groupedEl.innerHTML = '';
    flatEl.innerHTML = '';
    noResultsEl.classList.add('d-none');
    paginationEl.classList.add('d-none');
    if (popularEl) popularEl.classList.add('d-none');

    var startTime = performance.now();

    fetch('/discovery/search?q=' + encodeURIComponent(q) + '&page=' + currentPage + '&limit=20&mode=' + currentMode)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        loadingEl.classList.add('d-none');
        var elapsed = Math.round(performance.now() - startTime);

        if (!data.success || data.total === 0) {
          noResultsEl.classList.remove('d-none');
          return;
        }

        summaryEl.classList.remove('d-none');
        var modeLabels = { standard: 'Standard', semantic: 'Semantic', vector: 'Vector' };
        document.getElementById('result-count').textContent = data.total + ' result' + (data.total !== 1 ? 's' : '');
        document.getElementById('result-time').textContent = '(' + (elapsed / 1000).toFixed(1) + 's \u2022 ' + (modeLabels[data.mode] || 'Standard') + ')';

        showExpansion(data.expanded);
        renderGrouped(data.collections || []);
        renderFlat(data.results || []);

        if (data.pages > 1) {
          renderPagination(data.page, data.pages);
          paginationEl.classList.remove('d-none');
        }
      })
      .catch(function(err) {
        loadingEl.classList.add('d-none');
        noResultsEl.classList.remove('d-none');
        console.error('[discovery]', err);
      });
  }

  function showExpansion(expanded) {
    if (!expanded) { expansionEl.classList.add('d-none'); return; }
    var parts = [];
    if (expanded.synonyms && expanded.synonyms.length) {
      parts.push('Also searching: ' + expanded.synonyms.slice(0, 5).join(', '));
    }
    if (expanded.entityTerms && expanded.entityTerms.length) {
      parts.push('Entities: ' + expanded.entityTerms.slice(0, 5).join(', '));
    }
    if (expanded.dateRange) {
      var dr = expanded.dateRange;
      if (dr.start && dr.end && dr.start !== dr.end) { parts.push('Date range: ' + dr.start + '\u2013' + dr.end); }
      else if (dr.start) { parts.push('Year: ' + dr.start); }
    }
    if (parts.length) {
      expansionText.textContent = parts.join(' \u2022 ');
      expansionEl.classList.remove('d-none');
    } else {
      expansionEl.classList.add('d-none');
    }
  }

  function renderGrouped(collections) {
    var html = '';
    collections.forEach(function(col) {
      var slug = col.fonds_slug || '';
      var title = escHtml(col.fonds_title || 'Ungrouped');
      var count = col.records ? col.records.length : 0;
      var colId = 'col-' + (col.fonds_id || 0);
      html += '<div class="discovery-collection">';
      html += '<div class="discovery-collection-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#' + colId + '">';
      html += '<h5><i class="fas fa-archive me-2 text-muted"></i>';
      if (slug) { html += '<a href="/' + encodeURI(slug) + '" class="text-decoration-none" onclick="event.stopPropagation()">' + title + '</a>'; }
      else { html += title; }
      html += '</h5><span class="badge bg-primary rounded-pill">' + count + '</span></div>';
      html += '<div class="collapse show" id="' + colId + '"><div class="discovery-collection-body">';
      (col.records || []).forEach(function(r) { html += renderResultCard(r); });
      html += '</div></div></div>';
    });
    groupedEl.innerHTML = html;
    bindClickTracking(groupedEl);
  }

  function renderFlat(results) {
    var html = '';
    results.forEach(function(r) { html += renderResultCard(r); });
    flatEl.innerHTML = html;
    bindClickTracking(flatEl);
  }

  function renderResultCard(r) {
    var slug = r.slug || '';
    var title = escHtml(r.title || 'Untitled');
    var scope = r.scope_and_content || '';
    var level = r.level_of_description || '';
    var dates = r.date_range || '';
    var creator = r.creator || '';
    var repo = r.repository || '';
    var thumb = r.thumbnail_url || '';
    var reasons = r.match_reasons || [];
    var entities = r.entities || [];

    var html = '<div class="discovery-result d-flex">';
    if (thumb) {
      html += '<div class="me-3 flex-shrink-0"><img src="' + escHtml(thumb) + '" class="discovery-result-thumb" alt="" loading="lazy"></div>';
    }
    html += '<div class="flex-grow-1 min-width-0">';

    html += '<div class="d-flex align-items-start justify-content-between">';
    html += '<a href="/' + encodeURI(slug) + '" class="discovery-result-title" data-object-id="' + (r.object_id || '') + '">' + title + '</a>';
    if (typeof r.score === 'number') {
      var pct = Math.round(r.score * 100);
      var barColor = pct >= 70 ? '#198754' : pct >= 40 ? '#fd7e14' : '#6c757d';
      html += '<span class="ms-2 flex-shrink-0 text-nowrap" style="min-width:80px;" title="Similarity: ' + pct + '%">';
      html += '<small class="fw-bold" style="color:' + barColor + '">' + pct + '%</small>';
      html += '<div style="height:4px;width:60px;background:#e9ecef;border-radius:2px;margin-top:2px;"><div style="height:100%;width:' + pct + '%;background:' + barColor + ';border-radius:2px;"></div></div></span>';
    }
    html += '</div>';

    var metaParts = [];
    if (level) metaParts.push('<span>' + escHtml(level) + '</span>');
    if (dates) metaParts.push('<span><i class="fas fa-calendar-alt me-1"></i>' + escHtml(dates) + '</span>');
    if (creator) metaParts.push('<span><i class="fas fa-user me-1"></i>' + escHtml(creator) + '</span>');
    if (repo) metaParts.push('<span><i class="fas fa-building me-1"></i>' + escHtml(repo) + '</span>');
    if (metaParts.length) { html += '<div class="discovery-result-meta">' + metaParts.join('') + '</div>'; }

    if (scope) { html += '<div class="discovery-result-scope">' + escHtml(scope) + '</div>'; }

    if (entities.length) {
      html += '<div class="discovery-result-reasons mt-1">';
      entities.slice(0, 5).forEach(function(ent) {
        html += '<span class="entity-tag entity-tag-' + (ent.type || 'DEFAULT') + '">' + escHtml(ent.value) + '</span> ';
      });
      html += '</div>';
    }

    if (reasons.length) {
      html += '<div class="discovery-result-reasons">';
      reasons.forEach(function(reason) {
        var bc = 'bg-light text-muted';
        if (reason === 'KEYWORD') bc = 'bg-info bg-opacity-10 text-info';
        else if (reason === 'SEMANTIC') bc = 'bg-primary bg-opacity-10 text-primary';
        else if (reason.startsWith('ENTITY:')) bc = 'bg-success bg-opacity-10 text-success';
        else if (reason === 'SIBLING' || reason === 'CHILD') bc = 'bg-warning bg-opacity-10 text-warning';
        var label = reason.startsWith('ENTITY:') ? reason.substring(7) : reason.toLowerCase();
        html += '<span class="badge ' + bc + ' me-1">' + escHtml(label) + '</span>';
      });
      html += '</div>';
    }
    html += '</div></div>';
    return html;
  }

  function renderPagination(page, pages) {
    var html = '';
    html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page-1) + '">&laquo;</a></li>';
    var start = Math.max(1, page - 3), end = Math.min(pages, start + 6);
    start = Math.max(1, end - 6);
    for (var i = start; i <= end; i++) {
      html += '<li class="page-item' + (i === page ? ' active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
    }
    html += '<li class="page-item' + (page >= pages ? ' disabled' : '') + '"><a class="page-link" href="#" data-page="' + (page+1) + '">&raquo;</a></li>';
    paginationList.innerHTML = html;

    paginationList.querySelectorAll('a.page-link').forEach(function(link) {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        var p = parseInt(this.dataset.page, 10);
        if (p >= 1) { doSearch(p); window.scrollTo({ top: 0, behavior: 'smooth' }); }
      });
    });
  }

  function bindClickTracking(container) {
    container.querySelectorAll('.discovery-result-title').forEach(function(link) {
      link.addEventListener('click', function() {
        var oid = this.dataset.objectId;
        if (oid && currentQuery) {
          navigator.sendBeacon('/discovery/click', new URLSearchParams({
            query: currentQuery, object_id: oid, session_id: sessionId
          }));
        }
      });
    });
  }

  function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str || ''));
    return div.innerHTML;
  }

  // Auto-search if query in URL
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('mode') && ['standard','semantic','vector'].indexOf(urlParams.get('mode')) !== -1) {
    currentMode = urlParams.get('mode');
    document.querySelectorAll('#discovery-mode-group button').forEach(function(b) { b.classList.remove('active'); });
    var modeBtn = document.querySelector('#discovery-mode-group button[data-mode="' + currentMode + '"]');
    if (modeBtn) modeBtn.classList.add('active');
  }
})();
</script>
@endpush
