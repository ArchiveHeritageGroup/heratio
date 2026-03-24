{{-- Block: GLAM Browser (migrated from ahgLandingPagePlugin) --}}
@php
$blockId = 'glam-browser-' . ($block->id ?? uniqid());
$browseUrl = route('informationobject.browse');
$defaultView = $config['default_view'] ?? 'card';
$defaultLimit = $config['items_per_page'] ?? 10;
$showSidebar = $config['show_sidebar'] ?? true;
@endphp

<div id="{{ $blockId }}" class="glam-browser-block"
     data-browse-url="{{ $browseUrl }}"
     data-default-view="{{ $defaultView }}"
     data-default-limit="{{ $defaultLimit }}"
     data-show-sidebar="{{ $showSidebar ? '1' : '0' }}">

  <!-- Loading state -->
  <div class="glam-browser-loading text-center py-5">
    <div class="spinner-border text-success mb-3" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <p class="text-muted">Loading collections...</p>
  </div>

  <!-- Content loaded via AJAX -->
  <div class="glam-browser-content" style="display: none;"></div>

  <!-- Error state -->
  <div class="glam-browser-error" style="display: none;">
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      Unable to load browse interface.
      <a href="{{ route('informationobject.browse') }}" class="alert-link">
        Click here to browse collections
      </a>
    </div>
  </div>
</div>

<script nonce="{{ csp_nonce() }}">
(function() {
  var container = document.getElementById('{{ $blockId }}');
  if (!container) return;

  var browseUrl = container.dataset.browseUrl;
  var loading = container.querySelector('.glam-browser-loading');
  var content = container.querySelector('.glam-browser-content');
  var error = container.querySelector('.glam-browser-error');

  var urlParams = new URLSearchParams(window.location.search);
  var params = new URLSearchParams();

  ['type', 'level', 'repo', 'subject', 'place', 'creator', 'genre', 'media',
   'hasDigital', 'parent', 'view', 'limit', 'sort', 'dir', 'page', 'query', 'topLevel'].forEach(function(key) {
    if (urlParams.has(key)) {
      params.set(key, urlParams.get(key));
    }
  });

  if (!params.has('view')) params.set('view', container.dataset.defaultView);
  if (!params.has('limit')) params.set('limit', container.dataset.defaultLimit);
  params.set('showSidebar', container.dataset.showSidebar);
  params.set('embedded', '1');

  var fetchUrl = browseUrl + '?' + params.toString();

  fetch(fetchUrl)
    .then(function(response) {
      if (!response.ok) throw new Error('Network response was not ok');
      return response.text();
    })
    .then(function(html) {
      loading.style.display = 'none';
      content.innerHTML = html;
      content.style.display = 'block';

      content.querySelectorAll('a[href]').forEach(function(link) {
        var href = link.getAttribute('href');
        if (href.includes('/informationobject/') || href.includes('/slug/')) return;
        if (href.includes('browse') || href.includes('type=') || href.includes('level=')) {
          link.addEventListener('click', function(e) {
            e.preventDefault();
            var newUrl = new URL(href, window.location.origin);
            history.pushState({}, '', newUrl.pathname + newUrl.search);
            loadBrowseContent(newUrl.search);
          });
        }
      });
    })
    .catch(function(err) {
      console.error('GLAM Browser load error:', err);
      loading.style.display = 'none';
      error.style.display = 'block';
    });

  function loadBrowseContent(search) {
    loading.style.display = 'block';
    content.style.display = 'none';
    error.style.display = 'none';

    var params = new URLSearchParams(search);
    params.set('showSidebar', container.dataset.showSidebar);
    params.set('embedded', '1');

    fetch(browseUrl + '?' + params.toString())
      .then(function(response) { return response.text(); })
      .then(function(html) {
        loading.style.display = 'none';
        content.innerHTML = html;
        content.style.display = 'block';
      })
      .catch(function() {
        loading.style.display = 'none';
        error.style.display = 'block';
      });
  }

  window.addEventListener('popstate', function() {
    loadBrowseContent(window.location.search);
  });
})();
</script>
