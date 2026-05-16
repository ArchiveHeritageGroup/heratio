/* citation-popover.js
 * Heratio research artefact citation popovers.
 *
 * Looks for [N] markers in any .markdown-body or .studio-citations-host
 * inside the page and wires them to:
 *   - hover -> popover with the source title + snippet
 *   - click -> smooth-scroll to the matching #studio-citations list item
 *              and open the "Open URL" link in a new tab
 *
 * Source data is read from #studio-citations <li data-citation-n="N">.
 */
(function () {
    'use strict';

    function buildCitationIndex() {
        var idx = {};
        document.querySelectorAll('#studio-citations [data-citation-n]').forEach(function (li) {
            var n = parseInt(li.getAttribute('data-citation-n'), 10);
            if (!n) return;
            var titleAnchor = li.querySelector('a');
            var snippetEl = li.querySelector('.small.text-muted');
            idx[n] = {
                title: titleAnchor ? titleAnchor.textContent.trim() : '',
                url:   titleAnchor ? titleAnchor.getAttribute('href') : '',
                snippet: snippetEl ? snippetEl.textContent.trim() : '',
                element: li,
            };
        });
        return idx;
    }

    function makePopover(source) {
        var pop = document.createElement('div');
        pop.className = 'citation-popover shadow rounded bg-white border p-2';
        pop.style.position = 'absolute';
        pop.style.zIndex = '5000';
        pop.style.maxWidth = '360px';
        pop.style.fontSize = '0.85rem';
        pop.innerHTML =
            '<div class="fw-semibold mb-1">' + escapeHtml(source.title) + '</div>'
            + '<div class="text-muted small mb-2">' + escapeHtml(source.snippet.substring(0, 220)) + (source.snippet.length > 220 ? '...' : '') + '</div>'
            + (source.url ? '<a href="' + escapeHtml(source.url) + '" target="_blank" class="small">Open source <i class="fas fa-external-link-alt ms-1"></i></a>' : '');
        return pop;
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function wirePopovers(rootSelector) {
        var index = buildCitationIndex();
        if (Object.keys(index).length === 0) return;

        document.querySelectorAll(rootSelector).forEach(function (root) {
            // Walk text nodes and wrap [N] markers
            var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
            var nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);
            nodes.forEach(function (textNode) {
                var text = textNode.nodeValue;
                if (!/\[\d+\]/.test(text)) return;
                var span = document.createElement('span');
                span.innerHTML = text.replace(/\[(\d+)\]/g, function (_, n) {
                    return '<a href="#" class="citation-marker text-decoration-none" data-citation-n="' + n + '">[' + n + ']</a>';
                });
                textNode.parentNode.replaceChild(span, textNode);
            });
        });

        document.querySelectorAll('.citation-marker').forEach(function (a) {
            var n = parseInt(a.getAttribute('data-citation-n'), 10);
            var src = index[n];
            if (!src) return;

            var hoverTimer;
            var current;

            a.addEventListener('mouseenter', function (e) {
                clearTimeout(hoverTimer);
                hoverTimer = setTimeout(function () {
                    current = makePopover(src);
                    document.body.appendChild(current);
                    var rect = a.getBoundingClientRect();
                    current.style.top  = (window.scrollY + rect.bottom + 6) + 'px';
                    current.style.left = (window.scrollX + rect.left) + 'px';
                }, 120);
            });
            a.addEventListener('mouseleave', function () {
                clearTimeout(hoverTimer);
                setTimeout(function () {
                    if (current && !current.matches(':hover')) {
                        current.remove();
                        current = null;
                    }
                }, 250);
            });

            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (current) { current.remove(); current = null; }
                src.element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                src.element.classList.add('bg-light');
                setTimeout(function () { src.element.classList.remove('bg-light'); }, 1600);
            });
        });
    }

    function init() {
        wirePopovers('.markdown-body, #studio-body, .studio-citations-host');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
