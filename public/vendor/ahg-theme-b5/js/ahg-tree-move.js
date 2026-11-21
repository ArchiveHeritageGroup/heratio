/**
 * ahg-tree-move.js - move a description in the archival tree (heratio#1582).
 *
 * One client for every tree surface: the sidebar hierarchy, the full-width
 * tree page and the theme bundle's full-width tree. Drag-and-drop and the
 * keyboard "Move..." picker (WCAG 2.2 SC 2.5.7) both POST to the same route,
 * which re-checks update permission on the record and both parents.
 *
 * Configure with data attributes on the script tag:
 *   data-move-url, data-targets-url, data-children-url (":id" is replaced),
 *   data-strings (JSON of UI text, translated by the page).
 *
 * API:
 *   AhgTreeMove.move(body, done)        body = {id, new_parent_id, position?}
 *   AhgTreeMove.open({id, title}, done) open the picker for one record
 *   AhgTreeMove.bindJstree($tree)       route a jstree's move_node to the move
 *
 * done(result) runs after a move and after its undo; result.ok is false when
 * the move failed or was cancelled.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
(function () {
  'use strict';

  if (window.AhgTreeMove) { return; }

  var script = document.currentScript || {};
  var ds = script.dataset || {};
  var T = {};
  try { T = JSON.parse(ds.strings || '{}'); } catch (e) { T = {}; }
  function t(key, fallback) { return T[key] || fallback; }

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // ---- status line with Undo ---------------------------------------------
  var toast = null;
  function status(text, isError, undoBody, done) {
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'ahg-tree-move-status alert shadow-sm mb-0 py-2 px-3';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      toast.style.cssText = 'position:fixed;bottom:16px;left:16px;right:16px;max-width:32rem;z-index:1090;display:none;';
      document.body.appendChild(toast);
    }
    toast.className = 'ahg-tree-move-status alert shadow-sm mb-0 py-2 px-3 ' + (isError ? 'alert-danger' : 'alert-success');
    toast.innerHTML = '<span class="me-2">' + esc(text) + '</span>';
    if (undoBody) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm btn-outline-dark py-0';
      b.textContent = t('undo', 'Undo');
      b.addEventListener('click', function () {
        b.disabled = true;
        post(undoBody).then(function (res) {
          status(res.ok ? t('undone', 'Move undone.') : (res.d.error || t('failed', 'Could not move this description.')), !res.ok);
          if (done) { done(res.d); }
        });
      });
      toast.appendChild(b);
    }
    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-sm ms-2 align-middle';
    close.setAttribute('aria-label', t('close', 'Close'));
    close.addEventListener('click', function () { toast.style.display = 'none'; });
    toast.appendChild(close);
    toast.style.display = 'block';
  }

  function post(body) {
    var form = new URLSearchParams();
    Object.keys(body).forEach(function (k) {
      if (body[k] !== null && body[k] !== undefined && body[k] !== '') { form.append(k, body[k]); }
    });
    return fetch(ds.moveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
      body: form
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok && d.ok !== false, code: r.status, d: d }; });
    }).catch(function () { return { ok: false, code: 0, d: {} }; });
  }

  /** Move, asking first when it leaves the fonds; then offer undo. */
  function move(body, done) {
    post(body).then(function (res) {
      if (!res.ok && res.code === 409 && res.d.code === 'confirm') {
        if (!window.confirm(res.d.error)) {
          status(t('cancelled', 'Move cancelled.'), false);
          if (done) { done({ ok: false, cancelled: true }); }
          return;
        }
        body = Object.assign({}, body, { confirm: 1 });
        return move(body, done);
      }
      if (!res.ok) {
        status(res.d.error || t('failed', 'Could not move this description.'), true);
        if (done) { done(Object.assign({ ok: false }, res.d)); }
        return;
      }
      status(t('moved', 'Moved.'), false, res.d.undo, done);
      if (done) { done(res.d); }
    });
  }

  // ---- the "Move..." picker ---------------------------------------------
  var dlg = null;
  function buildDialog() {
    dlg = document.createElement('dialog');
    dlg.className = 'ahg-tree-move-dialog border rounded shadow p-0';
    dlg.setAttribute('aria-labelledby', 'ahg-tm-title');
    dlg.style.cssText = 'width:min(34rem, calc(100vw - 32px));';
    dlg.innerHTML =
      '<form method="dialog" class="p-3">' +
        '<h2 class="h5" id="ahg-tm-title"></h2>' +
        '<label class="form-label" for="ahg-tm-q">' + esc(t('searchLabel', 'Find the new parent by title or identifier')) + '</label>' +
        '<input type="text" class="form-control" id="ahg-tm-q" autocomplete="off" aria-describedby="ahg-tm-hint" aria-controls="ahg-tm-results">' +
        '<div class="form-text" id="ahg-tm-hint">' + esc(t('searchHint', 'Type at least two letters, then choose a parent from the list.')) + '</div>' +
        '<fieldset class="mt-2"><legend class="visually-hidden">' + esc(t('parentLegend', 'New parent')) + '</legend>' +
          '<div id="ahg-tm-results" class="list-group" style="max-height:14rem;overflow-y:auto;"></div></fieldset>' +
        '<label class="form-label mt-3" for="ahg-tm-pos">' + esc(t('positionLabel', 'Position')) + '</label>' +
        '<select class="form-select" id="ahg-tm-pos" disabled></select>' +
        '<div class="small text-danger mt-2" id="ahg-tm-err" role="alert"></div>' +
        '<div class="d-flex justify-content-end gap-2 mt-3">' +
          '<button type="button" class="btn btn-outline-secondary" value="cancel" id="ahg-tm-cancel">' + esc(t('cancel', 'Cancel')) + '</button>' +
          '<button type="button" class="btn btn-primary" id="ahg-tm-go" disabled>' + esc(t('move', 'Move')) + '</button>' +
        '</div>' +
      '</form>';
    document.body.appendChild(dlg);
    dlg.querySelector('#ahg-tm-cancel').addEventListener('click', function () { dlg.close(); });
    return dlg;
  }

  function open(rec, done) {
    var d = dlg || buildDialog();
    var q = d.querySelector('#ahg-tm-q');
    var results = d.querySelector('#ahg-tm-results');
    var pos = d.querySelector('#ahg-tm-pos');
    var go = d.querySelector('#ahg-tm-go');
    var err = d.querySelector('#ahg-tm-err');
    var parentId = null;
    var timer = null;
    var opener = document.activeElement;

    d.querySelector('#ahg-tm-title').textContent = t('title', 'Move') + ': ' + (rec.title || '');
    q.value = '';
    results.innerHTML = '';
    pos.innerHTML = '';
    pos.disabled = true;
    go.disabled = true;
    err.textContent = '';

    q.oninput = function () {
      clearTimeout(timer);
      var term = q.value.trim();
      if (term.length < 2) { results.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch(ds.targetsUrl + '?q=' + encodeURIComponent(term) + '&id=' + encodeURIComponent(rec.id), {
          credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
          results.innerHTML = '';
          (data.items || []).forEach(function (it, i) {
            var lab = document.createElement('label');
            lab.className = 'list-group-item list-group-item-action d-flex gap-2';
            lab.innerHTML = '<input class="form-check-input flex-shrink-0" type="radio" name="ahg-tm-parent" value="' + it.id + '">' +
              '<span><span class="d-block">' + esc(it.title) + (it.identifier ? ' <small class="text-muted">(' + esc(it.identifier) + ')</small>' : '') + '</span>' +
              (it.fonds ? '<small class="text-muted">' + esc(t('inFonds', 'In') + ' ' + it.fonds) + '</small>' : '') + '</span>';
            lab.querySelector('input').addEventListener('change', function () { pickParent(it.id); });
            results.appendChild(lab);
          });
          if (!results.children.length) {
            results.innerHTML = '<div class="list-group-item text-muted">' + esc(t('noMatches', 'No matches')) + '</div>';
          }
        }).catch(function () { results.innerHTML = ''; });
      }, 250);
    };

    function pickParent(id) {
      parentId = id;
      go.disabled = true;
      pos.disabled = true;
      pos.innerHTML = '<option>' + esc(t('loading', 'Loading...')) + '</option>';
      fetch(ds.childrenUrl.replace(':id', id), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var items = (data.items || []).filter(function (c) { return String(c.id) !== String(rec.id); });
          pos.innerHTML = '';
          pos.appendChild(new Option(t('atEnd', 'At the end'), ''));
          if (items.length) { pos.appendChild(new Option(t('atStart', 'At the start'), '0')); }
          items.forEach(function (c, i) {
            var label = (c.identifier ? c.identifier + ' - ' : '') + (c.title || '');
            pos.appendChild(new Option(t('after', 'After') + ': ' + label, String(i + 1)));
          });
          pos.disabled = false;
          go.disabled = false;
        })
        .catch(function () { err.textContent = t('failed', 'Could not move this description.'); });
    }

    go.onclick = function () {
      if (!parentId) { return; }
      d.close();
      move({ id: rec.id, new_parent_id: parentId, position: pos.value }, done);
    };
    d.onclose = function () { if (opener && opener.focus) { opener.focus(); } };

    d.showModal();
    q.focus();
  }

  // ---- jstree adapter -----------------------------------------------------
  function realId(node) {
    if (!node || node.id === '#') { return null; }
    if (node.data && node.data.real_id) { return node.data.real_id; }
    var m = String(node.id).match(/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
  }

  function bindJstree($tree) {
    // Replace any earlier handler (the theme bundle's posts to a route that does not exist).
    $tree.off('move_node.jstree').on('move_node.jstree', function (e, data) {
      var inst = data.instance || $tree.jstree(true);
      var id = realId(data.node);
      var parentId = realId(inst.get_node(data.parent));
      if (!id || !parentId) { inst.refresh(); return; }
      move({ id: id, new_parent_id: parentId, position: data.position }, function (res) {
        // Failed, cancelled or undone: redraw from the server.
        if (!res || res.ok === false || res.new_parent_id !== parentId) { inst.refresh(); }
      });
    });
  }

  window.AhgTreeMove = { move: move, open: open, bindJstree: bindJstree };

  // The theme bundle's full-width tree binds its own move_node handler when
  // it builds the tree; replace it once that tree is ready.
  if (window.jQuery) {
    window.jQuery(document).on('ready.jstree', '#fullwidth-treeview', function () {
      bindJstree(window.jQuery(this));
    });
  }
})();
