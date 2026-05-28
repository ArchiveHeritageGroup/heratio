/**
 * orcid-fetch.js - register-form "Fetch from ORCID" auto-populate.
 *
 * Bound to #orcid-fetch-btn (data-url = research.orcidFetchPublic endpoint,
 * data-csrf = token). On click it POSTs the entered ORCID iD, then fills the
 * matching form fields from the returned public record. Only fills EMPTY
 * fields by default so it never clobbers something the user already typed,
 * unless they confirm an overwrite.
 *
 * Shared by register.blade.php + public-register.blade.php. External file so
 * it is CSP-safe (no inline handler).
 *
 * Issue: ORCID researcher self-service auto-populate.
 */
(function () {
    'use strict';
    var btn = document.getElementById('orcid-fetch-btn');
    if (!btn) return;

    var status = document.getElementById('orcid-fetch-status');
    var url = btn.dataset.url;
    var csrf = btn.dataset.csrf;

    // form field id -> ORCID response key
    var MAP = {
        first_name: 'first_name',
        last_name: 'last_name',
        institution: 'institution',
        department: 'department',
        position: 'position',
        research_interests: 'research_interests',
        email: 'email'
    };

    function setStatus(msg, kind) {
        if (!status) return;
        status.textContent = msg || '';
        status.className = 'form-text ' + (kind === 'error' ? 'text-danger' : (kind === 'ok' ? 'text-success' : 'text-muted'));
    }

    function applyFields(fields) {
        var filled = [], skipped = [];
        Object.keys(MAP).forEach(function (fieldId) {
            var key = MAP[fieldId];
            var el = document.getElementById(fieldId);
            var val = fields[key];
            if (!el || val == null || val === '') return;
            if (el.value && el.value.trim() !== '' && el.value.trim() !== val.trim()) {
                // Field already has a different value - don't clobber silently.
                skipped.push(fieldId);
                return;
            }
            el.value = val;
            filled.push(fieldId);
        });
        // Normalise the orcid id field to the canonical form returned.
        if (fields.orcid_id) {
            var oel = document.getElementById('orcid_id');
            if (oel) oel.value = fields.orcid_id;
        }
        var msg = 'Filled ' + filled.length + ' field(s) from ORCID.';
        if (skipped.length) {
            msg += ' Left ' + skipped.length + ' field(s) you had already filled (' + skipped.join(', ') + ') - clear them and re-fetch to overwrite.';
        }
        setStatus(msg, 'ok');
    }

    btn.addEventListener('click', function () {
        var idEl = document.getElementById('orcid_id');
        var orcidId = idEl ? idEl.value.trim() : '';
        if (!orcidId) {
            setStatus('Enter your ORCID iD first (0000-0000-0000-0000).', 'error');
            if (idEl) idEl.focus();
            return;
        }

        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Fetching...';
        setStatus('Looking up ORCID record...', 'muted');

        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ orcid_id: orcidId })
        })
        .then(function (r) { return r.json().then(function (d) { return { status: r.status, body: d }; }); })
        .then(function (res) {
            if (res.body && res.body.ok) {
                applyFields(res.body.fields || {});
            } else {
                setStatus((res.body && res.body.error) || 'ORCID lookup failed.', 'error');
            }
        })
        .catch(function () {
            setStatus('Network error contacting ORCID lookup.', 'error');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = original;
        });
    });
})();
