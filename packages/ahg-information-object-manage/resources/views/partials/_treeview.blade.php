{{--
  Treeview partial for IO show page.
  Expects: $io (current information object with ->id, ->title, ->slug)
  Loads initial data via inline JSON, then lazy-loads via AJAX.

  #118 treeview_type dispatcher: GlobalSettings::treeviewType() picks
  between four layouts. All share the same #treeview-tree element + AJAX
  loader so the JS below runs identically; only the surrounding chrome
  changes.

   - 'sidebar'      (default) card-wrapped tree, fits a sidebar column
   - 'full'         chrome-free wide variant, two-column dense flow
   - 'accordion'    Bootstrap accordion - tree collapses behind a header
                    button so the IO show page reclaims the vertical space
                    when the operator doesn't need the hierarchy
   - 'nested-list'  bare semantic <ul>/<li> with zero card/border chrome
                    + zero column tricks, for the operator who wants the
                    leanest possible markup (e.g. embedding in print views)

  The 'sidebar' default keeps existing IO show pages bit-for-bit
  identical. Layouts beyond the first two ship in #118 phase 2.
--}}
@php
  $__treeviewType = \AhgCore\Support\GlobalSettings::treeviewType();
@endphp

@switch($__treeviewType)
  @case('full')
    <div class="mb-3" id="treeview-card">
      <h5 class="mb-2 fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-sitemap me-1"></i> {{ __('Hierarchy') }}</span>
        <button class="btn btn-sm btn-link p-0 text-muted" id="treeview-refresh" title="{{ __('Refresh') }}">
          <i class="fas fa-sync-alt"></i>
        </button>
      </h5>
      <div class="border rounded p-2" id="treeview-container" style="min-height:200px;">
        <div class="text-center py-3 text-muted" id="treeview-loading">
          <i class="fas fa-spinner fa-spin me-1"></i> {{ __('Loading hierarchy...') }}
        </div>
        <ul class="list-unstyled ps-0 mb-0" id="treeview-tree" style="display:none; column-count: 2; column-gap: 1.5rem;"></ul>
      </div>
    </div>
    @break

  @case('accordion')
    <div class="accordion mb-3" id="treeview-card" data-default-closed>
      <div class="accordion-item">
        <h2 class="accordion-header" id="treeview-accordion-header">
          <button class="accordion-button collapsed fw-bold" type="button"
                  data-bs-toggle="collapse" data-bs-target="#treeview-accordion-body"
                  aria-expanded="false" aria-controls="treeview-accordion-body">
            <i class="fas fa-sitemap me-2"></i> {{ __('Hierarchy') }}
          </button>
        </h2>
        <div id="treeview-accordion-body" class="accordion-collapse collapse"
             aria-labelledby="treeview-accordion-header">
          <div class="accordion-body p-2" id="treeview-container">
            <div class="d-flex justify-content-end mb-2">
              <button class="btn btn-sm btn-link p-0 text-muted" id="treeview-refresh" title="{{ __('Refresh') }}">
                <i class="fas fa-sync-alt"></i>
              </button>
            </div>
            <div class="text-center py-3 text-muted" id="treeview-loading">
              <i class="fas fa-spinner fa-spin me-1"></i> {{ __('Loading hierarchy...') }}
            </div>
            <ul class="list-unstyled ps-0 mb-0" id="treeview-tree" style="display:none;"></ul>
          </div>
        </div>
      </div>
    </div>
    @break

  @case('nested-list')
    <div class="mb-3" id="treeview-card">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <strong><i class="fas fa-sitemap me-1"></i> {{ __('Hierarchy') }}</strong>
        <button class="btn btn-sm btn-link p-0 text-muted" id="treeview-refresh" title="{{ __('Refresh') }}">
          <i class="fas fa-sync-alt"></i>
        </button>
      </div>
      <div id="treeview-container">
        <div class="text-muted small" id="treeview-loading">
          <i class="fas fa-spinner fa-spin me-1"></i> {{ __('Loading hierarchy...') }}
        </div>
        <ul class="list-unstyled ps-0 mb-0" id="treeview-tree" style="display:none;"></ul>
      </div>
    </div>
    @break

  @default
    {{-- 'sidebar' (default) --}}
    <div class="card mb-3" id="treeview-card">
      <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-sitemap me-1"></i> {{ __('Hierarchy') }}</span>
        <button class="btn btn-sm btn-link p-0 text-muted" id="treeview-refresh" title="{{ __('Refresh') }}">
          <i class="fas fa-sync-alt"></i>
        </button>
      </div>
      <div class="card-body p-2" id="treeview-container">
        <div class="text-center py-3 text-muted" id="treeview-loading">
          <i class="fas fa-spinner fa-spin me-1"></i> {{ __('Loading hierarchy...') }}
        </div>
        <ul class="list-unstyled ps-0 mb-0" id="treeview-tree" style="display:none;"></ul>
      </div>
    </div>
@endswitch

<style>
  #treeview-tree li { padding: 2px 0; }
  #treeview-tree .tv-node { cursor: pointer; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
  #treeview-tree .tv-node:hover { background: rgba(0,0,0,.04); border-radius: 3px; }
  #treeview-tree .tv-node.tv-active { font-weight: 700; color: #0d6efd; }
  #treeview-tree .tv-toggle { cursor: pointer; display: inline-block; width: 16px; text-align: center; color: #6c757d; }
  #treeview-tree .tv-toggle:hover { color: #0d6efd; }
  #treeview-tree .tv-children { padding-left: 15px; }
  #treeview-tree .tv-load-more { cursor: pointer; color: #6c757d; font-style: italic; }
  #treeview-tree .tv-load-more:hover { color: #0d6efd; }
</style>

<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function() {
  var currentId = {{ $io->id }};
  var treeviewUrl = '{{ route("io.treeview") }}';
  var treeviewDataUrl = '{{ route("io.treeviewData") }}';
  var showUrl = '{{ url("/") }}/';
  var tree = document.getElementById('treeview-tree');
  var loading = document.getElementById('treeview-loading');

  function loadTreeview() {
    loading.style.display = 'block';
    tree.style.display = 'none';

    fetch(treeviewDataUrl + '?id=' + currentId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.error) {
          loading.innerHTML = '<span class="text-danger small">' + data.error + '</span>';
          return;
        }
        renderTree(data);
        loading.style.display = 'none';
        tree.style.display = 'block';
      })
      .catch(function(err) {
        loading.innerHTML = '<span class="text-danger small">Failed to load hierarchy</span>';
      });
  }

  function renderTree(data) {
    tree.innerHTML = '';
    var ancestors = data.ancestors || [];
    var current = data.current;
    var children = data.children || [];
    var prevSiblings = data.prevSiblings || [];
    var nextSiblings = data.nextSiblings || [];
    var hasMore = data.hasMore || false;
    var hasPrevSiblings = data.hasPrevSiblings || false;
    var hasNextSiblings = data.hasNextSiblings || false;

    // Build the nested ancestor chain
    var parentEl = tree;

    // Render ancestors
    for (var i = 0; i < ancestors.length; i++) {
      var anc = ancestors[i];
      var li = createNodeLi(anc, true, true);
      parentEl.appendChild(li);

      // Create children container for next level
      var childUl = document.createElement('ul');
      childUl.className = 'list-unstyled tv-children';
      li.appendChild(childUl);
      parentEl = childUl;
    }

    // Render previous siblings (if current is a leaf showing siblings)
    if (hasPrevSiblings) {
      var morePrevLi = document.createElement('li');
      morePrevLi.innerHTML = '<span class="tv-load-more small" data-id="' + currentId + '" data-dir="prev">' +
        '<i class="fas fa-ellipsis-h me-1"></i>Load previous...</span>';
      parentEl.appendChild(morePrevLi);
      morePrevLi.querySelector('.tv-load-more').addEventListener('click', function() {
        loadSiblings(this, currentId, 'prev');
      });
    }
    for (var s = 0; s < prevSiblings.length; s++) {
      parentEl.appendChild(createNodeLi(prevSiblings[s], false, false));
    }

    // Render current node
    var currentLi = createNodeLi(current, data.hasChildren, data.hasChildren);
    currentLi.querySelector('.tv-node').classList.add('tv-active');
    parentEl.appendChild(currentLi);

    // Render children under current node
    if (children.length > 0) {
      var childrenUl = document.createElement('ul');
      childrenUl.className = 'list-unstyled tv-children';
      currentLi.appendChild(childrenUl);

      for (var c = 0; c < children.length; c++) {
        childrenUl.appendChild(createNodeLi(children[c], children[c].hasChildren, false));
      }

      if (hasMore) {
        var moreChildLi = document.createElement('li');
        moreChildLi.innerHTML = '<span class="tv-load-more small" data-parent="' + currentId + '" data-offset="' + children.length + '">' +
          '<i class="fas fa-ellipsis-h me-1"></i>Load more...</span>';
        childrenUl.appendChild(moreChildLi);
        moreChildLi.querySelector('.tv-load-more').addEventListener('click', function() {
          loadMoreChildren(this);
        });
      }
    }

    // Render next siblings
    for (var n = 0; n < nextSiblings.length; n++) {
      parentEl.appendChild(createNodeLi(nextSiblings[n], false, false));
    }
    if (hasNextSiblings && children.length === 0) {
      var moreNextLi = document.createElement('li');
      moreNextLi.innerHTML = '<span class="tv-load-more small" data-id="' + currentId + '" data-dir="next">' +
        '<i class="fas fa-ellipsis-h me-1"></i>Load more...</span>';
      parentEl.appendChild(moreNextLi);
      moreNextLi.querySelector('.tv-load-more').addEventListener('click', function() {
        loadSiblings(this, currentId, 'next');
      });
    }
  }

  function createNodeLi(node, hasChildren, expanded) {
    var li = document.createElement('li');
    var toggleIcon = '';

    if (hasChildren) {
      toggleIcon = '<span class="tv-toggle" data-id="' + node.id + '">' +
        '<i class="fas fa-caret-' + (expanded ? 'down' : 'right') + '"></i></span>';
    } else {
      toggleIcon = '<span class="tv-toggle" style="visibility:hidden"><i class="fas fa-caret-right"></i></span>';
    }

    var titleText = node.title || '[Untitled]';
    if (node.identifier) {
      titleText = node.identifier + ' - ' + titleText;
    }

    li.innerHTML = '<span class="tv-node small" data-id="' + node.id + '">' +
      toggleIcon +
      ' <a href="' + showUrl + node.slug + '" class="text-decoration-none">' +
      escapeHtml(titleText) + '</a></span>';

    // Toggle click handler for expanding/collapsing
    var toggle = li.querySelector('.tv-toggle');
    if (hasChildren && toggle) {
      toggle.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleChildren(this, node.id);
      });
    }

    return li;
  }

  function toggleChildren(toggleEl, nodeId) {
    var li = toggleEl.closest('li');
    var childrenUl = li.querySelector(':scope > ul.tv-children');
    var icon = toggleEl.querySelector('i');

    if (childrenUl) {
      // Toggle visibility
      if (childrenUl.style.display === 'none') {
        childrenUl.style.display = '';
        icon.className = 'fas fa-caret-down';
      } else {
        childrenUl.style.display = 'none';
        icon.className = 'fas fa-caret-right';
      }
    } else {
      // Load children via AJAX
      icon.className = 'fas fa-spinner fa-spin';
      fetch(treeviewUrl + '?id=' + nodeId + '&show=children&limit=10')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          icon.className = 'fas fa-caret-down';
          var ul = document.createElement('ul');
          ul.className = 'list-unstyled tv-children';

          var items = data.items || [];
          for (var i = 0; i < items.length; i++) {
            ul.appendChild(createNodeLi(items[i], items[i].hasChildren, false));
          }

          if (data.hasMore) {
            var moreLi = document.createElement('li');
            moreLi.innerHTML = '<span class="tv-load-more small" data-parent="' + nodeId + '" data-offset="' + items.length + '">' +
              '<i class="fas fa-ellipsis-h me-1"></i>Load more...</span>';
            ul.appendChild(moreLi);
            moreLi.querySelector('.tv-load-more').addEventListener('click', function() {
              loadMoreChildren(this);
            });
          }

          li.appendChild(ul);
        })
        .catch(function() {
          icon.className = 'fas fa-caret-right';
        });
    }
  }

  function loadMoreChildren(el) {
    var parentId = el.dataset.parent;
    var offset = parseInt(el.dataset.offset, 10);
    var li = el.closest('li');
    var ul = li.parentElement;

    el.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

    fetch(treeviewUrl + '?id=' + parentId + '&show=children&limit=10&offset=' + offset)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        // Remove the "load more" item
        ul.removeChild(li);

        var items = data.items || [];
        for (var i = 0; i < items.length; i++) {
          ul.appendChild(createNodeLi(items[i], items[i].hasChildren, false));
        }

        if (data.hasMore) {
          var moreLi = document.createElement('li');
          moreLi.innerHTML = '<span class="tv-load-more small" data-parent="' + parentId + '" data-offset="' + (offset + items.length) + '">' +
            '<i class="fas fa-ellipsis-h me-1"></i>Load more...</span>';
          ul.appendChild(moreLi);
          moreLi.querySelector('.tv-load-more').addEventListener('click', function() {
            loadMoreChildren(this);
          });
        }
      });
  }

  function loadSiblings(el, nodeId, direction) {
    var li = el.closest('li');
    var ul = li.parentElement;
    var showParam = direction === 'prev' ? 'prevSiblings' : 'nextSiblings';

    el.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

    fetch(treeviewUrl + '?id=' + nodeId + '&show=' + showParam + '&limit=10')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var items = data.items || [];
        var insertBefore = direction === 'prev' ? li : li.nextSibling;

        for (var i = 0; i < items.length; i++) {
          var newLi = createNodeLi(items[i], items[i].hasChildren, false);
          if (insertBefore) {
            ul.insertBefore(newLi, insertBefore);
          } else {
            ul.appendChild(newLi);
          }
        }

        // Remove the "load more" link
        ul.removeChild(li);
      });
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
  }

  // Refresh button
  document.getElementById('treeview-refresh').addEventListener('click', function() {
    loadTreeview();
  });

  // Initial load
  loadTreeview();
});
</script>
