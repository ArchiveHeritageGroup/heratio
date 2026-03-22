{{-- Accessibility Helpers - Migrated from AtoM: _accessibilityHelpers.php --}}
{{-- WCAG 2.1 AA compliance: skip nav, aria-live, focus management --}}

<!-- Skip Navigation -->
<a href="#main-content" class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-primary text-white z-3">
    Skip to main content
</a>

<!-- ARIA Live Region for AJAX announcements -->
<div id="ahgLiveRegion" class="visually-hidden" aria-live="polite" aria-atomic="true" role="status"></div>

<!-- Focus management + keyboard shortcuts -->
<script>
(function() {
    'use strict';
    window.ahgAnnounce = function(message, priority) {
        var region = document.getElementById('ahgLiveRegion');
        if (!region) return;
        region.setAttribute('aria-live', priority || 'polite');
        region.textContent = '';
        setTimeout(function() { region.textContent = message; }, 100);
    };
    window.ahgFocusTo = function(selector) {
        var el = document.querySelector(selector);
        if (el) { el.setAttribute('tabindex', '-1'); el.focus(); }
    };
    if (!document.getElementById('ahgA11yStyles')) {
        var style = document.createElement('style');
        style.id = 'ahgA11yStyles';
        style.textContent =
            '*:focus-visible { outline: 3px solid #0d6efd !important; outline-offset: 2px; }' +
            '.ahg-sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }';
        document.head.appendChild(style);
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var openModal = document.querySelector('.modal.show');
            if (openModal) {
                var closeBtn = openModal.querySelector('[data-bs-dismiss="modal"]');
                if (closeBtn) closeBtn.click();
            }
        }
    });
    document.querySelectorAll('input[type="checkbox"][name="request_ids[]"]').forEach(function(cb) {
        if (!cb.getAttribute('aria-label')) {
            var row = cb.closest('tr');
            if (row) {
                var text = row.querySelector('td:nth-child(2), td:nth-child(3)');
                if (text) cb.setAttribute('aria-label', 'Select ' + text.textContent.trim() + ' for batch action');
            }
        }
    });
})();
</script>
