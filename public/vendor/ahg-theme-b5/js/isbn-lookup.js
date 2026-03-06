/**
 * ISBN Lookup Module
 *
 * Provides WorldCat/Open Library/Google Books ISBN lookup
 * functionality for AtoM information object edit forms.
 */
const IsbnLookup = (function() {
    'use strict';

    // Configuration
    const config = {
        endpoint: '/isbn/lookup',
        debounceMs: 300,
        minIsbnLength: 10
    };

    // State
    let modal = null;
    let currentObjectId = null;

    /**
     * Initialize ISBN lookup functionality.
     */
    function init(options = {}) {
        Object.assign(config, options);

        // Find ISBN input fields
        document.querySelectorAll('[data-isbn-lookup]').forEach(setupField);

        // Create modal if not exists
        if (!document.getElementById('isbnLookupModal')) {
            createModal();
        }

        console.log('[ISBN Lookup] Initialized');
    }

    /**
     * Setup ISBN lookup for a field.
     */
    function setupField(field) {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-group';

        // Wrap the input
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(field);

        // Add lookup button
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary';
        button.innerHTML = '<i class="fas fa-search"></i> Lookup';
        button.title = 'Lookup ISBN metadata';
        button.addEventListener('click', () => performLookup(field.value));

        wrapper.appendChild(button);

        // Add validation feedback
        field.addEventListener('input', debounce(function() {
            validateIsbn(this.value, this);
        }, config.debounceMs));

        // Enter key triggers lookup
        field.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performLookup(this.value);
            }
        });
    }

    /**
     * Validate ISBN format.
     */
    function validateIsbn(isbn, field) {
        const normalized = isbn.replace(/[\s-]/g, '');

        if (normalized.length < config.minIsbnLength) {
            field.classList.remove('is-valid', 'is-invalid');
            return false;
        }

        const isValid = isValidIsbn10(normalized) || isValidIsbn13(normalized);

        field.classList.toggle('is-valid', isValid);
        field.classList.toggle('is-invalid', !isValid);

        return isValid;
    }

    /**
     * Validate ISBN-10.
     */
    function isValidIsbn10(isbn) {
        if (!/^[0-9]{9}[0-9X]$/.test(isbn)) return false;

        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(isbn[i]) * (10 - i);
        }
        sum += isbn[9] === 'X' ? 10 : parseInt(isbn[9]);

        return sum % 11 === 0;
    }

    /**
     * Validate ISBN-13.
     */
    function isValidIsbn13(isbn) {
        if (!/^[0-9]{13}$/.test(isbn)) return false;

        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(isbn[i]) * (i % 2 === 0 ? 1 : 3);
        }
        const check = (10 - (sum % 10)) % 10;

        return check === parseInt(isbn[12]);
    }

    /**
     * Perform ISBN lookup.
     */
    async function performLookup(isbn) {
        const normalized = isbn.replace(/[\s-]/g, '');

        if (!isValidIsbn10(normalized) && !isValidIsbn13(normalized)) {
            showAlert('Invalid ISBN format. Please enter a valid ISBN-10 or ISBN-13.', 'warning');
            return;
        }

        showLoading(true);

        try {
            const params = new URLSearchParams({
                isbn: normalized,
                object_id: currentObjectId || ''
            });

            const response = await fetch(`${config.endpoint}?${params}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!data.success) {
                showAlert(data.error || 'Lookup failed', 'danger');
                return;
            }

            showResults(data);

        } catch (error) {
            console.error('[ISBN Lookup] Error:', error);
            showAlert('Network error. Please try again.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    /**
     * Create lookup modal.
     */
    function createModal() {
        const html = `
            <div class="modal fade" id="isbnLookupModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-book me-2"></i>ISBN Lookup Results
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="isbnLookupLoading" class="text-center py-4 d-none">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Searching metadata sources...</p>
                            </div>
                            <div id="isbnLookupResults"></div>
                        </div>
                        <div class="modal-footer">
                            <span id="isbnLookupSource" class="text-muted me-auto"></span>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="isbnApplyBtn" disabled>
                                <i class="fas fa-check me-1"></i>Apply Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
        modal = new bootstrap.Modal(document.getElementById('isbnLookupModal'));

        // Apply button handler
        document.getElementById('isbnApplyBtn').addEventListener('click', applySelected);
    }

    /**
     * Show/hide loading state.
     */
    function showLoading(show) {
        const loading = document.getElementById('isbnLookupLoading');
        const results = document.getElementById('isbnLookupResults');

        if (show) {
            modal.show();
            loading.classList.remove('d-none');
            results.innerHTML = '';
            document.getElementById('isbnApplyBtn').disabled = true;
        } else {
            loading.classList.add('d-none');
        }
    }

    /**
     * Show lookup results.
     */
    function showResults(data) {
        const container = document.getElementById('isbnLookupResults');
        const preview = data.preview;

        let html = `
            <div class="row">
                <div class="col-md-${preview.cover_url ? '9' : '12'}">
                    <table class="table table-sm">
                        <tbody>
        `;

        // Title
        if (preview.title) {
            html += `
                <tr>
                    <th style="width: 150px;">
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="title" data-value="${escapeHtml(preview.title)}" checked>
                        Title
                    </th>
                    <td><strong>${escapeHtml(preview.title)}</strong></td>
                </tr>
            `;
        }

        // Creators
        if (preview.creators) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="creators" data-value="${escapeHtml(preview.creators)}" checked>
                        Creator(s)
                    </th>
                    <td>${escapeHtml(preview.creators)}</td>
                </tr>
            `;
        }

        // Publication
        if (preview.publication) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="publication" data-value="${escapeHtml(preview.publication)}" checked>
                        Publication
                    </th>
                    <td>${escapeHtml(preview.publication)}</td>
                </tr>
            `;
        }

        // Extent
        if (preview.extent) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="extentAndMedium" data-value="${escapeHtml(preview.extent)}" checked>
                        Extent
                    </th>
                    <td>${escapeHtml(preview.extent)}</td>
                </tr>
            `;
        }

        // Subjects
        if (preview.subjects && preview.subjects.length > 0) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="subjects" data-value="${escapeHtml(preview.subjects.join('; '))}" checked>
                        Subjects
                    </th>
                    <td>${preview.subjects.map(s => `<span class="badge bg-secondary me-1">${escapeHtml(s)}</span>`).join('')}</td>
                </tr>
            `;
        }

        // Language
        if (preview.language) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="language" data-value="${escapeHtml(preview.language)}" checked>
                        Language
                    </th>
                    <td>${escapeHtml(preview.language)}</td>
                </tr>
            `;
        }

        // Identifiers
        if (preview.identifiers && Object.keys(preview.identifiers).length > 0) {
            const idHtml = Object.entries(preview.identifiers)
                .map(([type, value]) => `<strong>${type}:</strong> ${escapeHtml(value)}`)
                .join('<br>');

            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="identifiers" data-value='${JSON.stringify(preview.identifiers)}' checked>
                        Identifiers
                    </th>
                    <td>${idHtml}</td>
                </tr>
            `;
        }

        // Description
        if (preview.description) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="scopeAndContent" data-value="${escapeHtml(preview.description)}">
                        Description
                    </th>
                    <td><small>${escapeHtml(preview.description)}</small></td>
                </tr>
            `;
        }

        html += '</tbody></table></div>';

        // Cover image
        if (preview.cover_url) {
            html += `
                <div class="col-md-3 text-center">
                    <img src="${escapeHtml(preview.cover_url)}" 
                         alt="Cover" class="img-fluid rounded shadow-sm" 
                         style="max-height: 200px;">
                </div>
            `;
        }

        html += '</div>';

        container.innerHTML = html;

        // Update source info
        const sourceLabel = data.cached ? `${data.source} (cached)` : data.source;
        document.getElementById('isbnLookupSource').innerHTML = 
            `<i class="fas fa-database me-1"></i>Source: ${sourceLabel}`;

        // Enable apply button
        document.getElementById('isbnApplyBtn').disabled = false;

        // Store mapping data
        container.dataset.mapping = JSON.stringify(data.mapping);
    }

    /**
     * Apply selected fields to form.
     */
    function applySelected() {
        const fields = document.querySelectorAll('.isbn-field:checked');
        const fieldMap = getFieldMap();

        fields.forEach(checkbox => {
            const fieldName = checkbox.dataset.field;
            const value = checkbox.dataset.value;

            if (fieldMap[fieldName]) {
                applyFieldValue(fieldMap[fieldName], value);
            }
        });

        modal.hide();
        showAlert('Metadata applied successfully', 'success');
    }

    /**
     * Get form field mapping.
     */
    function getFieldMap() {
        return {
            'title': 'input[name="title"], input[name*="[title]"], #title',
            'creators': 'input[name="creators[0][name]"], [name*="[creators]"], #creators',
            'publication': '[name*="[publication]"]',
            'publisher': 'input[name="publisher"]',
            'publication_date': 'input[name="publication_date"]',
            'publication_place': 'input[name="publication_place"]',
            'extentAndMedium': 'input[name="pagination"], textarea[name*="[extentAndMedium]"], #extentAndMedium',
            'subjects': 'input[name="subjects[0][heading]"], [name*="[subjectAccessPoints]"]',
            'language': 'select[name="language"], select[name*="[language]"]',
            'scopeAndContent': 'textarea[name="summary"], textarea[name="scope_and_content"], textarea[name*="[scopeAndContent]"], #scopeAndContent',
            'lccn': 'input[name="lccn"]',
            'oclc_number': 'input[name="oclc_number"]',
            'openlibrary_url': 'input[name="openlibrary_url"]',
            'cover_url': 'input[name="cover_url"]'
        };
    }

    /**
     * Apply value to form field.
     */
    function applyFieldValue(selector, value) {
        const field = document.querySelector(selector);

        if (!field) {
            console.warn(`[ISBN Lookup] Field not found: ${selector}`);
            return;
        }

        if (field.tagName === 'SELECT') {
            // Find matching option
            const option = Array.from(field.options).find(
                opt => opt.text.toLowerCase() === value.toLowerCase()
            );
            if (option) {
                field.value = option.value;
            }
        } else if (field.tagName === 'TEXTAREA') {
            // Append to existing content
            if (field.value.trim()) {
                field.value += '\n\n' + value;
            } else {
                field.value = value;
            }
        } else {
            field.value = value;
        }

        // Trigger change event
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Show alert message.
     */
    function showAlert(message, type = 'info') {
        // Use existing AtoM alert system if available
        if (typeof Atom !== 'undefined' && Atom.showAlert) {
            Atom.showAlert(message, type);
            return;
        }

        // Fallback toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 5000);
    }

    /**
     * Escape HTML entities.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debounce function.
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Public API
    return {
        init,
        lookup: performLookup,
        validate: validateIsbn,
        setObjectId: (id) => { currentObjectId = id; }
    };
})();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => IsbnLookup.init());
/**
 * ISBN Lookup Module
 *
 * Provides WorldCat/Open Library/Google Books ISBN lookup
 * functionality for AtoM information object edit forms.
 */
const IsbnLookup = (function() {
    'use strict';

    // Configuration
    const config = {
        endpoint: '/isbn/lookup',
        debounceMs: 300,
        minIsbnLength: 10
    };

    // State
    let modal = null;
    let currentObjectId = null;

    /**
     * Initialize ISBN lookup functionality.
     */
    function init(options = {}) {
        Object.assign(config, options);

        // Find ISBN input fields
        document.querySelectorAll('[data-isbn-lookup]').forEach(setupField);

        // Create modal if not exists
        if (!document.getElementById('isbnLookupModal')) {
            createModal();
        }

        console.log('[ISBN Lookup] Initialized');
    }

    /**
     * Setup ISBN lookup for a field.
     */
    function setupField(field) {
        const wrapper = document.createElement('div');
        wrapper.className = 'input-group';

        // Wrap the input
        field.parentNode.insertBefore(wrapper, field);
        wrapper.appendChild(field);

        // Add lookup button
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary';
        button.innerHTML = '<i class="fas fa-search"></i> Lookup';
        button.title = 'Lookup ISBN metadata';
        button.addEventListener('click', () => performLookup(field.value));

        wrapper.appendChild(button);

        // Add validation feedback
        field.addEventListener('input', debounce(function() {
            validateIsbn(this.value, this);
        }, config.debounceMs));

        // Enter key triggers lookup
        field.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performLookup(this.value);
            }
        });
    }

    /**
     * Validate ISBN format.
     */
    function validateIsbn(isbn, field) {
        const normalized = isbn.replace(/[\s-]/g, '');

        if (normalized.length < config.minIsbnLength) {
            field.classList.remove('is-valid', 'is-invalid');
            return false;
        }

        const isValid = isValidIsbn10(normalized) || isValidIsbn13(normalized);

        field.classList.toggle('is-valid', isValid);
        field.classList.toggle('is-invalid', !isValid);

        return isValid;
    }

    /**
     * Validate ISBN-10.
     */
    function isValidIsbn10(isbn) {
        if (!/^[0-9]{9}[0-9X]$/.test(isbn)) return false;

        let sum = 0;
        for (let i = 0; i < 9; i++) {
            sum += parseInt(isbn[i]) * (10 - i);
        }
        sum += isbn[9] === 'X' ? 10 : parseInt(isbn[9]);

        return sum % 11 === 0;
    }

    /**
     * Validate ISBN-13.
     */
    function isValidIsbn13(isbn) {
        if (!/^[0-9]{13}$/.test(isbn)) return false;

        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(isbn[i]) * (i % 2 === 0 ? 1 : 3);
        }
        const check = (10 - (sum % 10)) % 10;

        return check === parseInt(isbn[12]);
    }

    /**
     * Perform ISBN lookup.
     */
    async function performLookup(isbn) {
        const normalized = isbn.replace(/[\s-]/g, '');

        if (!isValidIsbn10(normalized) && !isValidIsbn13(normalized)) {
            showAlert('Invalid ISBN format. Please enter a valid ISBN-10 or ISBN-13.', 'warning');
            return;
        }

        showLoading(true);

        try {
            const params = new URLSearchParams({
                isbn: normalized,
                object_id: currentObjectId || ''
            });

            const response = await fetch(`${config.endpoint}?${params}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!data.success) {
                showAlert(data.error || 'Lookup failed', 'danger');
                return;
            }

            showResults(data);

        } catch (error) {
            console.error('[ISBN Lookup] Error:', error);
            showAlert('Network error. Please try again.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    /**
     * Create lookup modal.
     */
    function createModal() {
        const html = `
            <div class="modal fade" id="isbnLookupModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-book me-2"></i>ISBN Lookup Results
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="isbnLookupLoading" class="text-center py-4 d-none">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Searching metadata sources...</p>
                            </div>
                            <div id="isbnLookupResults"></div>
                        </div>
                        <div class="modal-footer">
                            <span id="isbnLookupSource" class="text-muted me-auto"></span>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="isbnApplyBtn" disabled>
                                <i class="fas fa-check me-1"></i>Apply Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', html);
        modal = new bootstrap.Modal(document.getElementById('isbnLookupModal'));

        // Apply button handler
        document.getElementById('isbnApplyBtn').addEventListener('click', applySelected);
    }

    /**
     * Show/hide loading state.
     */
    function showLoading(show) {
        const loading = document.getElementById('isbnLookupLoading');
        const results = document.getElementById('isbnLookupResults');

        if (show) {
            modal.show();
            loading.classList.remove('d-none');
            results.innerHTML = '';
            document.getElementById('isbnApplyBtn').disabled = true;
        } else {
            loading.classList.add('d-none');
        }
    }

    /**
     * Show lookup results.
     */
    function showResults(data) {
        const container = document.getElementById('isbnLookupResults');
        const preview = data.preview;

        let html = `
            <div class="row">
                <div class="col-md-${preview.cover_url ? '9' : '12'}">
                    <table class="table table-sm">
                        <tbody>
        `;

        // Title
        if (preview.title) {
            html += `
                <tr>
                    <th style="width: 150px;">
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="title" data-value="${escapeHtml(preview.title)}" checked>
                        Title
                    </th>
                    <td><strong>${escapeHtml(preview.title)}</strong></td>
                </tr>
            `;
        }

        // Creators
        if (preview.creators) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="creators" data-value="${escapeHtml(preview.creators)}" checked>
                        Creator(s)
                    </th>
                    <td>${escapeHtml(preview.creators)}</td>
                </tr>
            `;
        }

        // Publication
        if (preview.publication) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="publication" data-value="${escapeHtml(preview.publication)}" checked>
                        Publication
                    </th>
                    <td>${escapeHtml(preview.publication)}</td>
                </tr>
            `;
        }

        // Extent
        if (preview.extent) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="extentAndMedium" data-value="${escapeHtml(preview.extent)}" checked>
                        Extent
                    </th>
                    <td>${escapeHtml(preview.extent)}</td>
                </tr>
            `;
        }

        // Subjects
        if (preview.subjects && preview.subjects.length > 0) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="subjects" data-value="${escapeHtml(preview.subjects.join('; '))}" checked>
                        Subjects
                    </th>
                    <td>${preview.subjects.map(s => `<span class="badge bg-secondary me-1">${escapeHtml(s)}</span>`).join('')}</td>
                </tr>
            `;
        }

        // Language
        if (preview.language) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="language" data-value="${escapeHtml(preview.language)}" checked>
                        Language
                    </th>
                    <td>${escapeHtml(preview.language)}</td>
                </tr>
            `;
        }

        // Identifiers
        if (preview.identifiers && Object.keys(preview.identifiers).length > 0) {
            const idHtml = Object.entries(preview.identifiers)
                .map(([type, value]) => `<strong>${type}:</strong> ${escapeHtml(value)}`)
                .join('<br>');

            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="identifiers" data-value='${JSON.stringify(preview.identifiers)}' checked>
                        Identifiers
                    </th>
                    <td>${idHtml}</td>
                </tr>
            `;
        }

        // Description
        if (preview.description) {
            html += `
                <tr>
                    <th>
                        <input type="checkbox" class="form-check-input isbn-field" 
                               data-field="scopeAndContent" data-value="${escapeHtml(preview.description)}">
                        Description
                    </th>
                    <td><small>${escapeHtml(preview.description)}</small></td>
                </tr>
            `;
        }

        html += '</tbody></table></div>';

        // Cover image
        if (preview.cover_url) {
            html += `
                <div class="col-md-3 text-center">
                    <img src="${escapeHtml(preview.cover_url)}" 
                         alt="Cover" class="img-fluid rounded shadow-sm" 
                         style="max-height: 200px;">
                </div>
            `;
        }

        html += '</div>';

        container.innerHTML = html;

        // Update source info
        const sourceLabel = data.cached ? `${data.source} (cached)` : data.source;
        document.getElementById('isbnLookupSource').innerHTML = 
            `<i class="fas fa-database me-1"></i>Source: ${sourceLabel}`;

        // Enable apply button
        document.getElementById('isbnApplyBtn').disabled = false;

        // Store mapping data
        container.dataset.mapping = JSON.stringify(data.mapping);
    }

    /**
     * Apply selected fields to form.
     */
    function applySelected() {
        const fields = document.querySelectorAll('.isbn-field:checked');
        const fieldMap = getFieldMap();

        fields.forEach(checkbox => {
            const fieldName = checkbox.dataset.field;
            const value = checkbox.dataset.value;

            if (fieldMap[fieldName]) {
                applyFieldValue(fieldMap[fieldName], value);
            }
        });

        modal.hide();
        showAlert('Metadata applied successfully', 'success');
    }

    /**
     * Get form field mapping.
     */
    function getFieldMap() {
        return {
            'title': 'input[name="title"], input[name*="[title]"], #title',
            'creators': 'input[name="creators[0][name]"], [name*="[creators]"], #creators',
            'publication': '[name*="[publication]"]',
            'publisher': 'input[name="publisher"]',
            'publication_date': 'input[name="publication_date"]',
            'publication_place': 'input[name="publication_place"]',
            'extentAndMedium': 'input[name="pagination"], textarea[name*="[extentAndMedium]"], #extentAndMedium',
            'subjects': 'input[name="subjects[0][heading]"], [name*="[subjectAccessPoints]"]',
            'language': 'select[name="language"], select[name*="[language]"]',
            'scopeAndContent': 'textarea[name="summary"], textarea[name="scope_and_content"], textarea[name*="[scopeAndContent]"], #scopeAndContent',
            'lccn': 'input[name="lccn"]',
            'oclc_number': 'input[name="oclc_number"]',
            'openlibrary_url': 'input[name="openlibrary_url"]',
            'cover_url': 'input[name="cover_url"]'
        };
    }

    /**
     * Apply value to form field.
     */
    function applyFieldValue(selector, value) {
        const field = document.querySelector(selector);

        if (!field) {
            console.warn(`[ISBN Lookup] Field not found: ${selector}`);
            return;
        }

        if (field.tagName === 'SELECT') {
            // Find matching option
            const option = Array.from(field.options).find(
                opt => opt.text.toLowerCase() === value.toLowerCase()
            );
            if (option) {
                field.value = option.value;
            }
        } else if (field.tagName === 'TEXTAREA') {
            // Append to existing content
            if (field.value.trim()) {
                field.value += '\n\n' + value;
            } else {
                field.value = value;
            }
        } else {
            field.value = value;
        }

        // Trigger change event
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Show alert message.
     */
    function showAlert(message, type = 'info') {
        // Use existing AtoM alert system if available
        if (typeof Atom !== 'undefined' && Atom.showAlert) {
            Atom.showAlert(message, type);
            return;
        }

        // Fallback toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);

        setTimeout(() => toast.remove(), 5000);
    }

    /**
     * Escape HTML entities.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debounce function.
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Public API
    return {
        init,
        lookup: performLookup,
        validate: validateIsbn,
        setObjectId: (id) => { currentObjectId = id; }
    };
})();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => IsbnLookup.init());
