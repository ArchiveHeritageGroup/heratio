{{--
    Museum vocabulary autocomplete - drop-in BS5 widget.

    Issue: #739

    Usage:
        @include('ahg-museum::_autocomplete-script')

        <input type="text"
               class="form-control"
               data-museum-autocomplete="getty-aat"
               data-target-uri="#field_aat_uri"
               name="material_label">

        Supported data-museum-autocomplete values:
          - getty-aat                              (Getty AAT SPARQL, cached 24h)
          - vocabulary:<group>                     (ahg_dropdown taxonomy)
          - authority:actor | authority:term       (internal authorities)

        data-target-uri / data-target-code is optional - when present the
        chosen result's uri / code / id is written into that hidden input.

    Copyright (C) 2026 Plain Sailing Information Systems
    Licensed under the GNU AGPL v3 or later.
--}}
<link rel="stylesheet" href="data:text/css;base64,{{ base64_encode(
'.museum-ac-results{position:absolute;z-index:1080;background:#fff;border:1px solid #ced4da;border-radius:.25rem;box-shadow:0 4px 12px rgba(0,0,0,.08);max-height:320px;overflow-y:auto;min-width:280px}'
. '.museum-ac-results .museum-ac-item{padding:.45rem .65rem;cursor:pointer;border-bottom:1px solid #f1f3f5;font-size:.9rem}'
. '.museum-ac-results .museum-ac-item:last-child{border-bottom:0}'
. '.museum-ac-results .museum-ac-item:hover,.museum-ac-results .museum-ac-item.active{background:#e7f1ff}'
. '.museum-ac-results .museum-ac-note{font-size:.75rem;color:#6c757d;margin-top:2px}'
. '.museum-ac-empty{padding:.45rem .65rem;color:#6c757d;font-style:italic;font-size:.85rem}'
) }}">
<script>
(function () {
    if (window.__museumAutocompleteWired) return;
    window.__museumAutocompleteWired = true;

    var DEBOUNCE_MS = 250;
    var MIN_CHARS = 2;
    var endpoints = {
        getty: '{{ route('museum.api.getty-aat') }}',
        vocab: '{{ route('museum.api.vocabulary-search') }}',
        auth:  '{{ route('museum.api.authority-search') }}'
    };

    function debounce(fn, wait) {
        var t = null;
        return function () {
            var args = arguments, ctx = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    function buildUrl(input) {
        var kind = (input.getAttribute('data-museum-autocomplete') || '').trim();
        var q = encodeURIComponent(input.value || '');
        if (kind === 'getty-aat') {
            return endpoints.getty + '?q=' + q;
        }
        if (kind.indexOf('vocabulary:') === 0) {
            return endpoints.vocab + '?group=' + encodeURIComponent(kind.slice('vocabulary:'.length)) + '&q=' + q;
        }
        if (kind.indexOf('authority:') === 0) {
            return endpoints.auth + '?type=' + encodeURIComponent(kind.slice('authority:'.length)) + '&q=' + q;
        }
        return null;
    }

    function clearPanel(panel) {
        while (panel.firstChild) panel.removeChild(panel.firstChild);
    }

    function attachPanel(input) {
        var panel = input.__museumPanel;
        if (panel) return panel;
        panel = document.createElement('div');
        panel.className = 'museum-ac-results';
        panel.style.display = 'none';
        document.body.appendChild(panel);
        input.__museumPanel = panel;
        return panel;
    }

    function positionPanel(input, panel) {
        var r = input.getBoundingClientRect();
        panel.style.top = (window.scrollY + r.bottom + 2) + 'px';
        panel.style.left = (window.scrollX + r.left) + 'px';
        panel.style.minWidth = r.width + 'px';
    }

    function renderResults(input, panel, payload) {
        clearPanel(panel);
        var rows = (payload && payload.results) || [];
        if (!rows.length) {
            var empty = document.createElement('div');
            empty.className = 'museum-ac-empty';
            empty.innerHTML = '<i class="bi bi-search me-1"></i>No matches';
            panel.appendChild(empty);
            panel.style.display = 'block';
            positionPanel(input, panel);
            return;
        }
        rows.forEach(function (row) {
            var item = document.createElement('div');
            item.className = 'museum-ac-item';
            var label = row.label || row.code || row.uri || '';
            var note  = row.definition || row.note || row.uri || '';
            item.innerHTML = '<div><strong>' + escapeHtml(label) + '</strong></div>'
                + (note ? '<div class="museum-ac-note">' + escapeHtml(note) + '</div>' : '');
            item.addEventListener('mousedown', function (e) {
                // mousedown so we fire before the input's blur
                e.preventDefault();
                input.value = label;
                var targetUri  = input.getAttribute('data-target-uri');
                var targetCode = input.getAttribute('data-target-code');
                var targetId   = input.getAttribute('data-target-id');
                if (targetUri && row.uri) setHiddenValue(targetUri, row.uri);
                if (targetCode && (row.code || row.id)) setHiddenValue(targetCode, row.code || row.id);
                if (targetId && row.id) setHiddenValue(targetId, row.id);
                panel.style.display = 'none';
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            panel.appendChild(item);
        });
        panel.style.display = 'block';
        positionPanel(input, panel);
    }

    function setHiddenValue(selector, value) {
        var el = document.querySelector(selector);
        if (el) el.value = value;
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    var doFetch = debounce(function (input) {
        var url = buildUrl(input);
        if (!url) return;
        var panel = attachPanel(input);
        if ((input.value || '').trim().length < MIN_CHARS) {
            panel.style.display = 'none';
            return;
        }
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { results: [] }; })
            .then(function (json) { renderResults(input, panel, json); })
            .catch(function () { panel.style.display = 'none'; });
    }, DEBOUNCE_MS);

    function wire(input) {
        if (input.__museumWired) return;
        input.__museumWired = true;
        input.setAttribute('autocomplete', 'off');
        input.addEventListener('input',  function () { doFetch(input); });
        input.addEventListener('focus',  function () { doFetch(input); });
        input.addEventListener('blur',   function () {
            setTimeout(function () {
                if (input.__museumPanel) input.__museumPanel.style.display = 'none';
            }, 150);
        });
    }

    function scan(root) {
        (root || document).querySelectorAll('[data-museum-autocomplete]').forEach(wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(document); });
    } else {
        scan(document);
    }

    // Re-scan when a dynamic form is injected (best-effort).
    var mo = new MutationObserver(function (muts) {
        muts.forEach(function (m) {
            m.addedNodes && m.addedNodes.forEach(function (n) {
                if (n.nodeType === 1) {
                    if (n.matches && n.matches('[data-museum-autocomplete]')) wire(n);
                    if (n.querySelectorAll) scan(n);
                }
            });
        });
    });
    mo.observe(document.body, { childList: true, subtree: true });

    // Expose a tiny PATCH helper for Spectrum edit forms.
    window.museumPatchProcedure = function (id, procedureType, field, value) {
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        return fetch('/spectrum/procedure/' + encodeURIComponent(id), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                procedure_type: procedureType,
                field: field,
                value: value
            })
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, body: j }; }); });
    };
})();
</script>
