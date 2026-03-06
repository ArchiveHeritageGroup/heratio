/**
 * Plugin Protection - Disable toggle when records exist
 * Injects into AHG Settings plugin management page
 */
(function() {
    'use strict';

    // Only run on plugin admin pages
    var path = window.location.pathname;
    if (path.indexOf('/admin/ahg-settings/plugins') === -1 && 
        path.indexOf('/sfPluginAdminPlugin/plugins') === -1) {
        return;
    }

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(fetchProtectionStatus, 500);
    });

    function fetchProtectionStatus() {
        fetch('/api/plugin-protection', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success && data.plugins) {
                applyProtection(data.plugins);
            }
        })
        .catch(function(error) {
            console.error('Plugin protection error:', error);
        });
    }

    function applyProtection(plugins) {
        // Find all plugin cards or rows
        var cards = document.querySelectorAll('.card, .plugin-card, tr[data-plugin], .list-group-item');
        
        cards.forEach(function(card) {
            var pluginName = findPluginName(card);
            if (!pluginName || !plugins[pluginName]) return;
            
            var info = plugins[pluginName];
            
            // Skip if plugin can be disabled or is not enabled
            if (info.can_disable || !info.is_enabled) return;
            
            // Find disable button or toggle in this card
            var disableBtn = card.querySelector('button[data-action="disable"], .btn-danger, .btn-outline-danger, input[type="checkbox"]');
            if (disableBtn) {
                protectElement(disableBtn, pluginName, info, card);
            }
        });

        // Also check for table rows
        var rows = document.querySelectorAll('table tbody tr');
        rows.forEach(function(row) {
            var pluginName = findPluginName(row);
            if (!pluginName || !plugins[pluginName]) return;
            
            var info = plugins[pluginName];
            if (info.can_disable || !info.is_enabled) return;
            
            var disableBtn = row.querySelector('button[data-action="disable"], .btn-danger, .btn-outline-danger');
            if (disableBtn) {
                protectElement(disableBtn, pluginName, info, row);
            }
        });
    }

    function findPluginName(element) {
        // Check data attribute
        if (element.dataset && element.dataset.plugin) {
            return element.dataset.plugin;
        }
        
        // Check button data attribute
        var btn = element.querySelector('[data-plugin]');
        if (btn && btn.dataset.plugin) {
            return btn.dataset.plugin;
        }
        
        // Check text content for plugin name pattern
        var text = element.textContent || element.innerText;
        var match = text.match(/(ahg\w+Plugin|ar\w+Plugin|sf\w+Plugin|qt\w+Plugin)/);
        if (match) return match[1];
        
        return null;
    }

    function protectElement(element, pluginName, info, container) {
        // Disable the button/checkbox
        element.disabled = true;
        element.classList.add('disabled');
        element.style.opacity = '0.5';
        element.style.cursor = 'not-allowed';
        element.dataset.protected = 'true';
        element.dataset.pluginName = pluginName;
        element.dataset.reason = info.reason;
        element.dataset.recordCount = info.record_count;

        // Update button text if it's a button
        if (element.tagName === 'BUTTON') {
            element.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Protected';
            element.classList.remove('btn-danger', 'btn-outline-danger');
            element.classList.add('btn-secondary');
        }

        // Add warning badge to container
        if (!container.querySelector('.protection-badge')) {
            var badge = document.createElement('span');
            badge.className = 'protection-badge badge bg-warning text-dark ms-2';
            badge.style.cssText = 'font-size:11px;';
            badge.innerHTML = '<i class="bi bi-database-fill"></i> ' + formatNumber(info.record_count) + ' records';
            badge.title = info.reason;
            
            // Find best place to insert badge
            var title = container.querySelector('.card-title, h5, h4, td:first-child, strong');
            if (title) {
                title.appendChild(badge);
            } else {
                container.insertBefore(badge, container.firstChild);
            }
        }

        // Highlight container
        container.style.backgroundColor = '#fff3cd';

        // Prevent click
        element.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            alert('Cannot disable ' + pluginName + ':\n\n' + info.reason + '\n\nUse CLI with --force to override:\nphp bin/atom extension:disable ' + pluginName + ' --force');
            return false;
        });
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
})();
