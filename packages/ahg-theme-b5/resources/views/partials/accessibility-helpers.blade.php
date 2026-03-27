{{--
  Global Accessibility Helpers — WCAG 2.1 AA compliance

  Provides:
   - ARIA live region + ahgAnnounce() / ahgFocusTo()
   - Escape key modal close
   - Auto scope="col"/"row" on table headers
   - Auto aria-required on required inputs
   - Auto aria-label on batch checkboxes lacking labels
   - MutationObserver for aria-invalid on .is-invalid fields
   - Heading hierarchy warnings (admin/edit only, console)
--}}
<!-- ARIA Live Region for dynamic announcements -->
<div id="ahgLiveRegion" class="visually-hidden" aria-live="polite" aria-atomic="true" role="status"></div>

<script>
(function() {
    'use strict';

    // ── Announce to screen readers via live region ──
    window.ahgAnnounce = function(message, priority) {
        var region = document.getElementById('ahgLiveRegion');
        if (!region) return;
        region.setAttribute('aria-live', priority || 'polite');
        region.textContent = '';
        setTimeout(function() { region.textContent = message; }, 100);
    };

    // ── Programmatic focus management ──
    window.ahgFocusTo = function(selector) {
        var el = document.querySelector(selector);
        if (el) {
            el.setAttribute('tabindex', '-1');
            el.focus();
        }
    };

    // ── Escape key closes open modals ──
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var openModal = document.querySelector('.modal.show');
            if (openModal) {
                var closeBtn = openModal.querySelector('[data-bs-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
            }
        }
    });

    // ── Run on DOMContentLoaded ──
    function initA11y() {
        // Auto-add scope="col" to thead th, scope="row" to tbody th
        document.querySelectorAll('thead th:not([scope])').forEach(function(th) {
            th.setAttribute('scope', 'col');
        });
        document.querySelectorAll('tbody th:not([scope])').forEach(function(th) {
            th.setAttribute('scope', 'row');
        });

        // Auto-add aria-required on inputs with required attribute
        document.querySelectorAll('input[required]:not([aria-required]), select[required]:not([aria-required]), textarea[required]:not([aria-required])').forEach(function(el) {
            el.setAttribute('aria-required', 'true');
        });

        // Auto-label batch checkboxes lacking aria-label
        document.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
            if (cb.getAttribute('aria-label') || cb.id && document.querySelector('label[for="' + cb.id + '"]')) return;
            var row = cb.closest('tr');
            if (row) {
                var text = row.querySelector('td:nth-child(2), td:nth-child(3)');
                if (text) {
                    cb.setAttribute('aria-label', 'Select ' + text.textContent.trim());
                }
            }
        });

        // Sync aria-invalid with Bootstrap .is-invalid
        document.querySelectorAll('.is-invalid').forEach(function(el) {
            el.setAttribute('aria-invalid', 'true');
        });
    }

    // ── MutationObserver: track .is-invalid changes for aria-invalid ──
    function observeValidation() {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.type === 'attributes' && m.attributeName === 'class') {
                    var el = m.target;
                    if (el.classList.contains('is-invalid')) {
                        el.setAttribute('aria-invalid', 'true');
                        // Link to error message if sibling .invalid-feedback exists
                        var feedback = el.parentNode && el.parentNode.querySelector('.invalid-feedback');
                        if (feedback) {
                            if (!feedback.id) feedback.id = 'err-' + (el.id || el.name || Math.random().toString(36).substr(2, 6));
                            el.setAttribute('aria-describedby', feedback.id);
                        }
                    } else {
                        el.removeAttribute('aria-invalid');
                        el.removeAttribute('aria-describedby');
                    }
                }
            });
        });
        document.querySelectorAll('input, select, textarea').forEach(function(el) {
            observer.observe(el, { attributes: true, attributeFilter: ['class'] });
        });
    }

    // ── Heading hierarchy warnings (admin/edit pages only) ──
    function checkHeadingHierarchy() {
        if (!document.body.classList.contains('edit') && !document.body.classList.contains('admin')) return;
        var headings = document.querySelectorAll('h1, h2, h3, h4, h5, h6');
        var prev = 0;
        headings.forEach(function(h) {
            var level = parseInt(h.tagName.charAt(1), 10);
            if (prev > 0 && level > prev + 1) {
                console.warn('[A11y] Heading hierarchy skip: <' + h.tagName.toLowerCase() + '> follows <h' + prev + '> — "' + h.textContent.trim().substring(0, 40) + '"');
            }
            prev = level;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { initA11y(); observeValidation(); checkHeadingHierarchy(); });
    } else {
        initA11y(); observeValidation(); checkHeadingHierarchy();
    }
})();
</script>
