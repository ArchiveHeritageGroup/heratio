/**
 * Heratio Clipboard — client-side localStorage clipboard manager.
 *
 * Stores clipboard items by type (informationObject, actor, repository, accession)
 * in localStorage under the key "clipboard".
 *
 * Clipboard toggle buttons must have:
 *   class="clipboard"
 *   data-clipboard-slug="..."
 *   data-clipboard-type="informationObject|actor|repository|accession"
 *   data-title="Add"
 *   data-alt-title="Remove"
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'clipboard';
  var TYPES = ['informationObject', 'actor', 'repository', 'accession'];

  function getItems() {
    try {
      var data = JSON.parse(localStorage.getItem(STORAGE_KEY));
      if (data && typeof data === 'object') {
        TYPES.forEach(function (t) {
          if (!Array.isArray(data[t])) data[t] = [];
        });
        return data;
      }
    } catch (e) { /* ignore */ }
    return initItems();
  }

  function initItems() {
    var items = {};
    TYPES.forEach(function (t) { items[t] = []; });
    return items;
  }

  function saveItems(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }

  function totalCount(items) {
    var total = 0;
    TYPES.forEach(function (t) {
      if (Array.isArray(items[t])) total += items[t].length;
    });
    return total;
  }

  function updateMenuBadge(items) {
    var count = totalCount(items);
    var menuBtn = document.getElementById('clipboard-menu');
    if (!menuBtn) return;

    // Badge on the clipboard icon (matching AtoM)
    var badge = menuBtn.querySelector('.clipboard-count');
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'clipboard-count position-absolute top-0 start-0 badge rounded-pill bg-primary';
        var sr = document.createElement('span');
        sr.className = 'visually-hidden';
        sr.textContent = menuBtn.getAttribute('data-total-count-label') || 'items in clipboard';
        badge.appendChild(sr);
        menuBtn.style.position = 'relative';
        menuBtn.appendChild(badge);
      }
      // Set text before the sr span
      badge.childNodes[0].nodeType === 3
        ? badge.childNodes[0].textContent = count
        : badge.insertBefore(document.createTextNode(count), badge.firstChild);
    } else if (badge) {
      badge.remove();
    }

    // Update counts-block in dropdown (matching AtoM)
    var countsBlock = document.getElementById('counts-block');
    if (countsBlock) {
      var ioLabel = countsBlock.getAttribute('data-information-object-label') || 'Archival description';
      var actorLabel = countsBlock.getAttribute('data-actor-object-label') || 'Authority record';
      var repoLabel = countsBlock.getAttribute('data-repository-object-label') || 'Archival institution';
      var ioCount = items.informationObject ? items.informationObject.length : 0;
      var actorCount = items.actor ? items.actor.length : 0;
      var repoCount = items.repository ? items.repository.length : 0;
      countsBlock.innerHTML =
        ioLabel + ' ' + ioCount + '<br>' +
        actorLabel + ' ' + actorCount + '<br>' +
        repoLabel + ' ' + repoCount;
    }
  }

  function updateButton(btn, added) {
    var isActive = btn.classList.contains('active');
    if ((!isActive && added) || (isActive && !added)) {
      var label = btn.getAttribute('data-title');
      var altLabel = btn.getAttribute('data-alt-title');
      btn.setAttribute('data-alt-title', label);
      btn.setAttribute('data-title', altLabel);
      var span = btn.querySelector('span');
      if (span) span.textContent = altLabel;
      btn.classList.toggle('active');
      btn.setAttribute('title', altLabel);
    }
  }

  function updateAllButtons(items) {
    document.querySelectorAll('button.clipboard').forEach(function (btn) {
      var type = btn.getAttribute('data-clipboard-type');
      var slug = btn.getAttribute('data-clipboard-slug');
      if (type && slug && items[type]) {
        updateButton(btn, items[type].indexOf(slug) !== -1);
      }
    });
  }

  function init() {
    var items = getItems();

    // Update all clipboard buttons on page load
    updateAllButtons(items);
    updateMenuBadge(items);

    // Fetch server-side clipboard and merge (for logged-in users)
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
      fetch('/clipboard/count', {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.items) {
          // Merge server items into localStorage
          var localItems = getItems();
          var changed = false;
          TYPES.forEach(function(t) {
            if (Array.isArray(data.items[t])) {
              data.items[t].forEach(function(slug) {
                if (localItems[t].indexOf(slug) === -1) {
                  localItems[t].push(slug);
                  changed = true;
                }
              });
            }
          });
          if (changed) {
            saveItems(localItems);
            updateAllButtons(localItems);
            updateMenuBadge(localItems);
          }
        }
      })
      .catch(function() { /* silent — not logged in or endpoint unavailable */ });
    }

    // Toggle clipboard item on button click
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('button.clipboard');
      if (!btn) return;
      e.preventDefault();

      // Re-read from storage (may have changed in another tab)
      items = getItems();

      var type = btn.getAttribute('data-clipboard-type');
      var slug = btn.getAttribute('data-clipboard-slug');
      if (!type || !slug) return;

      var idx = items[type].indexOf(slug);
      if (idx === -1) {
        items[type].push(slug);
        updateButton(btn, true);
      } else {
        items[type].splice(idx, 1);
        updateButton(btn, false);
      }

      saveItems(items);
      updateMenuBadge(items);

      // Sync to server session (fire-and-forget)
      var csrfToken = document.querySelector('meta[name="csrf-token"]');
      if (csrfToken) {
        fetch('/clipboard/sync', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
            'Accept': 'application/json'
          },
          body: JSON.stringify({ items: items })
        }).catch(function () { /* silent */ });
      }
    });

    // Listen for storage changes from other tabs
    window.addEventListener('storage', function (e) {
      if (e.key === STORAGE_KEY) {
        items = getItems();
        updateAllButtons(items);
        updateMenuBadge(items);
      }
    });
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
