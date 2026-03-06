/**
 * Identifier Validation
 *
 * Provides real-time validation of identifier fields against numbering schemes.
 * Shows warnings for format mismatches and errors for duplicates.
 */
(function() {
    'use strict';

    var debounceTimer = null;
    var lastValidatedValue = '';

    function init() {
        // Find identifier inputs on the page
        var identifierInputs = document.querySelectorAll(
            'input[name="identifier"], input[name="object_number"]'
        );

        identifierInputs.forEach(function(input) {
            setupValidation(input);
        });
    }

    function setupValidation(input) {
        var sector = detectSector();
        var excludeId = getExcludeId();
        var feedbackEl = createFeedbackElement(input);

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            var value = input.value.trim();

            if (value === lastValidatedValue) {
                return;
            }

            // Clear previous feedback immediately
            clearFeedback(feedbackEl);

            if (value === '') {
                showInfo(feedbackEl, 'Identifier will be auto-generated if left empty');
                return;
            }

            // Debounce the API call
            debounceTimer = setTimeout(function() {
                validateIdentifier(value, sector, excludeId, feedbackEl);
                lastValidatedValue = value;
            }, 500);
        });

        // Validate on blur as well
        input.addEventListener('blur', function() {
            var value = input.value.trim();
            if (value && value !== lastValidatedValue) {
                validateIdentifier(value, sector, excludeId, feedbackEl);
                lastValidatedValue = value;
            }
        });
    }

    function createFeedbackElement(input) {
        var feedbackEl = document.createElement('div');
        feedbackEl.className = 'identifier-validation-feedback mt-1';
        feedbackEl.style.fontSize = '0.875rem';
        input.parentNode.appendChild(feedbackEl);
        return feedbackEl;
    }

    function clearFeedback(feedbackEl) {
        feedbackEl.innerHTML = '';
        feedbackEl.className = 'identifier-validation-feedback mt-1';
    }

    function showInfo(feedbackEl, message) {
        feedbackEl.innerHTML = '<i class="fas fa-info-circle me-1"></i>' + message;
        feedbackEl.className = 'identifier-validation-feedback mt-1 text-muted';
    }

    function showWarning(feedbackEl, message, expectedFormat) {
        var html = '<i class="fas fa-exclamation-triangle me-1"></i>' + message;
        if (expectedFormat) {
            html += '<br><small class="text-muted">Expected: ' + expectedFormat + '</small>';
        }
        feedbackEl.innerHTML = html;
        feedbackEl.className = 'identifier-validation-feedback mt-1 text-warning';
    }

    function showError(feedbackEl, message) {
        feedbackEl.innerHTML = '<i class="fas fa-times-circle me-1"></i>' + message;
        feedbackEl.className = 'identifier-validation-feedback mt-1 text-danger';
    }

    function showSuccess(feedbackEl) {
        feedbackEl.innerHTML = '<i class="fas fa-check-circle me-1"></i>Identifier is available';
        feedbackEl.className = 'identifier-validation-feedback mt-1 text-success';
    }

    function validateIdentifier(value, sector, excludeId, feedbackEl) {
        var url = '/index.php/api/numbering/validate';
        var params = new URLSearchParams({
            identifier: value,
            sector: sector
        });

        if (excludeId) {
            params.append('exclude_id', excludeId);
        }

        fetch(url + '?' + params.toString(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.error) {
                // Server error - don't block the user
                clearFeedback(feedbackEl);
                return;
            }

            if (!data.valid && data.errors && data.errors.length > 0) {
                showError(feedbackEl, data.errors.join(', '));
            } else if (data.warnings && data.warnings.length > 0) {
                showWarning(feedbackEl, data.warnings.join(', '), data.expected_format);
            } else {
                showSuccess(feedbackEl);
            }
        })
        .catch(function(err) {
            // Network error - don't block the user
            console.log('Identifier validation error:', err);
            clearFeedback(feedbackEl);
        });
    }

    function detectSector() {
        // Try to detect sector from form data attribute
        var form = document.querySelector('form[data-sector]');
        if (form) {
            return form.dataset.sector;
        }

        // Try to detect from URL
        var path = window.location.pathname;
        if (path.indexOf('/museum/') !== -1 || path.indexOf('/cco/') !== -1) {
            return 'museum';
        }
        if (path.indexOf('/gallery/') !== -1) {
            return 'gallery';
        }
        if (path.indexOf('/library/') !== -1) {
            return 'library';
        }
        if (path.indexOf('/dam/') !== -1) {
            return 'dam';
        }

        // Default to archive
        return 'archive';
    }

    function getExcludeId() {
        // Check for hidden id field
        var idInput = document.querySelector('input[name="id"]');
        if (idInput && idInput.value) {
            return idInput.value;
        }

        // Check for slug in URL (editing existing)
        var slugMatch = window.location.pathname.match(/\/edit\/([^\/]+)/);
        if (slugMatch) {
            // We have a slug, but need the ID - will be handled server-side
            return null;
        }

        return null;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
