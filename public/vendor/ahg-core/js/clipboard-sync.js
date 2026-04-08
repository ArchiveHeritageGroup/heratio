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
  var TYPES = ['informationObject', 'actor', 'repository'];

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
    // Normalize BEFORE the bundle reads localStorage (this script loads after bundle)
    // But bundle initializes on DOMContentLoaded too, so normalize ASAP
    var items = normalize();

    // Sync to server on page load
    var hasItems = false;
    TYPES.forEach(function (t) {
      if (items[t] && items[t].length > 0) hasItems = true;
    });
    if (hasItems) {
      syncToServer(items);
    }
  }

  // Run immediately (before DOMContentLoaded if possible)
  init();

  // Also listen for storage changes and sync
  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY) {
      var items = normalize();
      syncToServer(items);
    }
  });
})();
