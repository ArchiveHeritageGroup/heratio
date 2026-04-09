/**
 * Heratio Clipboard Server Sync + localStorage normalizer
 *
 * The AtoM theme bundle handles clipboard toggle (localStorage + UI).
 * This script:
 * 1. Ensures localStorage clipboard has all required type keys (prevents bundle crash)
 * 2. Syncs localStorage clipboard to server session for persistence
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'clipboard';
  var TYPES = ['informationObject', 'actor', 'repository', 'accession'];

  // Update the nav clipboard badge to count ALL types (bundle only counts 3)
  function updateBadge(items) {
    var menuBtn = document.getElementById('clipboard-menu');
    if (!menuBtn) return;
    var total = 0;
    TYPES.forEach(function(t) {
      if (Array.isArray(items[t])) total += items[t].length;
    });
    var badge = menuBtn.querySelector('.clipboard-count');
    if (total > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'clipboard-count badge rounded-pill bg-primary';
        badge.style.cssText = 'position:absolute;top:2px;left:2px;font-size:0.65em;z-index:10;min-width:18px;';
        menuBtn.style.position = 'relative';
        menuBtn.appendChild(badge);
      }
      badge.textContent = total;
    } else if (badge) {
      badge.remove();
    }

    // Also update the dropdown counts-block (bundle only counts 3 types)
    var countsBlock = document.getElementById('counts-block');
    if (countsBlock) {
      var ioLabel = countsBlock.getAttribute('data-information-object-label') || 'Archival description';
      var actorLabel = countsBlock.getAttribute('data-actor-object-label') || 'Authority record';
      var repoLabel = countsBlock.getAttribute('data-repository-object-label') || 'Archival institution';
      var accessionLabel = countsBlock.getAttribute('data-accession-object-label') || 'Accession';
      var html = ioLabel + ' count: ' + (items.informationObject ? items.informationObject.length : 0) + '<br>'
        + actorLabel + ' count: ' + (items.actor ? items.actor.length : 0) + '<br>'
        + repoLabel + ' count: ' + (items.repository ? items.repository.length : 0);
      if (countsBlock.hasAttribute('data-accession-object-label')) {
        html += '<br>' + accessionLabel + ' count: ' + (items.accession ? items.accession.length : 0);
      }
      countsBlock.innerHTML = html;
    }
  }

  function normalize() {
    var raw = localStorage.getItem(STORAGE_KEY);
    var items;
    try {
      items = JSON.parse(raw);
    } catch (e) {
      items = null;
    }

    if (!items || typeof items !== 'object') {
      items = {};
    }

    var changed = false;
    TYPES.forEach(function (t) {
      if (!Array.isArray(items[t])) {
        items[t] = [];
        changed = true;
      }
    });

    if (changed || !raw) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    }

    return items;
  }

  function syncToServer(items) {
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) return;

    fetch('/clipboard/sync', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfMeta.getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({ items: items })
    }).catch(function () {});
  }

  function init() {
    var items = normalize();
    updateBadge(items);

    var hasItems = false;
    TYPES.forEach(function (t) {
      if (items[t] && items[t].length > 0) hasItems = true;
    });
    if (hasItems) {
      syncToServer(items);
    }
  }

  // Run immediately
  init();
  // Also re-run when DOM is ready (the menu may not exist yet on first run)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      updateBadge(normalize());
    });
  }

  // Listen for clicks on any clipboard button (including accession) and update badge after bundle handles it
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('button.clipboard');
    if (!btn) return;
    // Bundle's click handler runs first (synchronous), then we update badge after
    setTimeout(function() {
      var items = normalize();
      updateBadge(items);
      syncToServer(items);
    }, 50);
  });

  // Listen for storage changes from other tabs
  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY) {
      var items = normalize();
      updateBadge(items);
      syncToServer(items);
    }
  });
})();
