/**
 * Display Mode Switching Module
 * 
 * Handles client-side display mode toggling with localStorage cache
 * and server sync for authenticated users.
 */
const DisplayMode = (function() {
    'use strict';

    const STORAGE_KEY = 'atom_display_prefs';
    const API_ENDPOINT = '/atom-framework/public/api/display-mode.php';

    let currentModule = '';
    let isAuthenticated = false;

    /**
     * Initialize display mode switching.
     * @param {Object} options Configuration options
     */
    function init(options = {}) {
        currentModule = options.module || detectModule();
        isAuthenticated = options.authenticated || false;

        bindToggleButtons();
        bindSettingsForm();
        
        // Apply saved preference on page load
        const savedMode = getLocalPreference(currentModule);
        if (savedMode && options.autoApply !== false) {
            applyModeVisually(savedMode);
        }
    }

    /**
     * Detect current module from URL.
     * @returns {string} Module name
     */
    function detectModule() {
        const path = window.location.pathname;
        
        if (path.includes('/informationobject')) return 'informationobject';
        if (path.includes('/actor')) return 'actor';
        if (path.includes('/repository')) return 'repository';
        if (path.includes('/digitalobject')) return 'digitalobject';
        if (path.includes('/library')) return 'library';
        if (path.includes('/gallery')) return 'gallery';
        if (path.includes('/dam')) return 'dam';
        if (path.includes('/search')) return 'search';
        
        return 'search';
    }

    /**
     * Bind click handlers to toggle buttons.
     */
    function bindToggleButtons() {
        document.querySelectorAll('.display-mode-toggle').forEach(group => {
            group.querySelectorAll('button[data-mode]').forEach(btn => {
                btn.addEventListener('click', handleToggleClick);
            });
        });
    }

    /**
     * Handle toggle button click.
     * @param {Event} e Click event
     */
    function handleToggleClick(e) {
        e.preventDefault();
        
        const btn = e.currentTarget;
        const mode = btn.dataset.mode;
        const group = btn.closest('.display-mode-toggle');
        const module = group?.dataset.module || currentModule;
        const useAjax = group?.dataset.ajax === 'true';

        // Update button states
        group.querySelectorAll('button').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-pressed', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-pressed', 'true');

        // Save preference
        setLocalPreference(module, mode);

        // Apply visual change
        if (useAjax) {
            switchModeAjax(module, mode);
        } else {
            applyModeVisually(mode);
            // If not using AJAX, might need to reload or navigate
            const url = btn.dataset.url;
            if (url && url !== '#') {
                window.location.href = url;
            }
        }

        // Sync to server if authenticated
        if (isAuthenticated) {
            syncToServer(module, mode);
        }
    }

    /**
     * Apply display mode visually without page reload.
     * @param {string} mode Display mode
     */
    function applyModeVisually(mode) {
        const resultsContainer = document.querySelector('.search-results, .browse-results, [data-display-container]');
        
        if (!resultsContainer) return;

        // Remove existing mode classes
        resultsContainer.className = resultsContainer.className
            .replace(/display-\w+-view/g, '')
            .replace(/row-cols-\w+-\d+/g, '')
            .trim();

        // Add new mode classes
        const classes = getContainerClasses(mode);
        resultsContainer.classList.add(...classes.split(' ').filter(c => c));

        // Update items if they have different layouts
        resultsContainer.querySelectorAll('.result-item, .browse-item').forEach(item => {
            item.dataset.displayMode = mode;
        });

        // Trigger custom event for other scripts
        document.dispatchEvent(new CustomEvent('displayModeChanged', {
            detail: { mode, container: resultsContainer }
        }));
    }

    /**
     * Get CSS classes for container based on mode.
     * @param {string} mode Display mode
     * @returns {string} CSS classes
     */
    function getContainerClasses(mode) {
        const classes = {
            tree: 'display-tree-view',
            grid: 'display-grid-view row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3',
            gallery: 'display-gallery-view row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4',
            list: 'display-list-view table-responsive',
            timeline: 'display-timeline-view'
        };
        
        return classes[mode] || classes.list;
    }

    /**
     * Switch mode via AJAX (reloads content).
     * @param {string} module Module name
     * @param {string} mode Display mode
     */
    async function switchModeAjax(module, mode) {
        const resultsContainer = document.querySelector('.search-results, .browse-results, [data-display-container]');
        
        if (!resultsContainer) {
            applyModeVisually(mode);
            return;
        }

        // Show loading state
        resultsContainer.classList.add('loading');
        resultsContainer.setAttribute('aria-busy', 'true');

        try {
            // Get current page URL with new mode
            const url = new URL(window.location.href);
            url.searchParams.set('display_mode', mode);
            url.searchParams.set('ajax', '1');

            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const html = await response.text();
                
                // Extract just the results content if full page returned
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.querySelector('.search-results, .browse-results, [data-display-container]');
                
                if (newContent) {
                    resultsContainer.innerHTML = newContent.innerHTML;
                }

                // Apply mode classes
                applyModeVisually(mode);

                // Update URL without reload
                history.replaceState(null, '', url.toString().replace('&ajax=1', '').replace('ajax=1&', ''));
            }
        } catch (error) {
            console.error('Display mode switch failed:', error);
            // Fall back to visual-only change
            applyModeVisually(mode);
        } finally {
            resultsContainer.classList.remove('loading');
            resultsContainer.setAttribute('aria-busy', 'false');
        }
    }

    /**
     * Sync preference to server.
     * @param {string} module Module name
     * @param {string} mode Display mode
     */
    async function syncToServer(module, mode) {
        try {
            const formData = new FormData();
            formData.append('action', 'switch');
            formData.append('module', module);
            formData.append('mode', mode);

            await fetch(API_ENDPOINT, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        } catch (error) {
            console.warn('Failed to sync display preference:', error);
        }
    }

    /**
     * Get preference from localStorage.
     * @param {string} module Module name
     * @returns {string|null} Display mode or null
     */
    function getLocalPreference(module) {
        try {
            const prefs = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            return prefs[module] || null;
        } catch {
            return null;
        }
    }

    /**
     * Save preference to localStorage.
     * @param {string} module Module name
     * @param {string} mode Display mode
     */
    function setLocalPreference(module, mode) {
        try {
            const prefs = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
            prefs[module] = mode;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
        } catch (error) {
            console.warn('Failed to save display preference:', error);
        }
    }

    /**
     * Bind settings form if present.
     */
    function bindSettingsForm() {
        const form = document.querySelector('#display-settings-form');
        if (!form) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            formData.append('action', 'preferences');

            try {
                const response = await fetch(API_ENDPOINT, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showNotification('Display settings saved', 'success');
                } else {
                    showNotification(result.error || 'Failed to save settings', 'error');
                }
            } catch (error) {
                showNotification('Failed to save settings', 'error');
            }
        });
    }

    /**
     * Show notification message.
     * @param {string} message Message text
     * @param {string} type Message type (success, error, info)
     */
    function showNotification(message, type = 'info') {
        // Use existing notification system if available
        if (typeof window.showAlert === 'function') {
            window.showAlert(message, type);
            return;
        }

        // Simple fallback
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 5000);
    }

    /**
     * Get current mode for a module.
     * @param {string} module Module name
     * @returns {string} Current mode
     */
    function getCurrentMode(module) {
        return getLocalPreference(module) || 'list';
    }

    // Public API
    return {
        init,
        switchMode: applyModeVisually,
        getCurrentMode,
        setPreference: setLocalPreference,
        getPreference: getLocalPreference
    };
})();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Look for toggle buttons and initialize
    if (document.querySelector('.display-mode-toggle')) {
        DisplayMode.init({
            authenticated: document.body.classList.contains('authenticated')
        });
    }
});

/**
 * Reset display preference to global default.
 * @param {string} module Module name
 */
DisplayMode.resetToDefault = async function(module) {
    try {
        const formData = new FormData();
        formData.append('action', 'reset');
        formData.append('module', module);

        const response = await fetch('/atom-framework/public/api/display-mode.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const result = await response.json();
        
        if (result.success) {
            // Clear local storage for this module
            const prefs = JSON.parse(localStorage.getItem('atom_display_prefs') || '{}');
            delete prefs[module];
            localStorage.setItem('atom_display_prefs', JSON.stringify(prefs));
            
            // Apply new default mode
            if (result.settings?.display_mode) {
                DisplayMode.switchMode(result.settings.display_mode);
            }
            
            return true;
        }
        return false;
    } catch (error) {
        console.error('Reset failed:', error);
        return false;
    }
};

// Bind reset buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.reset-display-mode').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const module = this.dataset.module;
            
            if (await DisplayMode.resetToDefault(module)) {
                // Reload to show default
                window.location.reload();
            }
        });
    });
});

// Sidebar toggle for gallery/fullwidth mode
(function() {
    const FULLWIDTH_MODES = ['gallery', 'timeline'];
    
    function initSidebarToggle() {
        // Check if toggle button exists, if not create it
        let toggleBtn = document.querySelector('.sidebar-toggle-btn');
        if (!toggleBtn) {
            toggleBtn = document.createElement('button');
            toggleBtn.className = 'sidebar-toggle-btn show-sidebar';
            toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            toggleBtn.title = 'Show filters';
            toggleBtn.addEventListener('click', toggleSidebar);
            document.body.appendChild(toggleBtn);
        }
        
        // Check current display mode
        const currentMode = localStorage.getItem('displayMode_informationobject') || 
                           new URLSearchParams(window.location.search).get('displayMode') ||
                           'list';
        
        if (FULLWIDTH_MODES.includes(currentMode)) {
            enableFullwidth();
        }
    }
    
    function enableFullwidth() {
        document.body.classList.add('browse-fullwidth');
        const toggleBtn = document.querySelector('.sidebar-toggle-btn');
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
            toggleBtn.title = 'Show filters';
            toggleBtn.classList.add('show-sidebar');
        }
    }
    
    function disableFullwidth() {
        document.body.classList.remove('browse-fullwidth');
        const toggleBtn = document.querySelector('.sidebar-toggle-btn');
        if (toggleBtn) {
            toggleBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            toggleBtn.title = 'Hide filters';
            toggleBtn.classList.remove('show-sidebar');
        }
    }
    
    function toggleSidebar() {
        if (document.body.classList.contains('browse-fullwidth')) {
            disableFullwidth();
        } else {
            enableFullwidth();
        }
    }
    
    // Hook into display mode changes
    const originalSetMode = window.setDisplayMode;
    window.setDisplayMode = function(mode) {
        if (originalSetMode) originalSetMode(mode);
        
        if (FULLWIDTH_MODES.includes(mode)) {
            enableFullwidth();
        } else {
            disableFullwidth();
        }
    };
    
    // Expose functions globally
    window.toggleBrowseSidebar = toggleSidebar;
    window.enableFullwidthBrowse = enableFullwidth;
    window.disableFullwidthBrowse = disableFullwidth;
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebarToggle);
    } else {
        initSidebarToggle();
    }
})();
