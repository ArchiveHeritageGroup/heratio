@php /**
 * Admin Display Mode Settings
 * 
 * Accessible at: /admin/display-settings (or via AHG Settings Dashboard)
 */

use AtomExtensions\Services\DisplayModeService;

$displayService = new DisplayModeService();
$allSettings = $displayService->getAllGlobalSettings();

$allModes = [
    'tree' => ['name' => 'Hierarchy', 'icon' => 'bi-diagram-3'],
    'grid' => ['name' => 'Grid', 'icon' => 'bi-grid-3x3-gap'],
    'gallery' => ['name' => 'Gallery', 'icon' => 'bi-images'],
    'list' => ['name' => 'List', 'icon' => 'bi-list-ul'],
    'timeline' => ['name' => 'Timeline', 'icon' => 'bi-clock-history'],
];

$moduleLabels = [
    'informationobject' => 'Archival Descriptions',
    'actor' => 'Authority Records',
    'repository' => 'Archival Institutions',
    'digitalobject' => 'Digital Objects',
    'library' => 'Library',
    'gallery' => 'Gallery',
    'dam' => 'Digital Asset Management',
    'search' => 'Search Results',
    'accession' => 'Accessions',
    'function' => 'Functions',
    'term' => 'Terms/Subjects',
    'physicalobject' => 'Physical Storage',
]; @endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-display me-2"></i>
            {{ __('Display Mode Settings') }}
        </h5>
        <span class="badge bg-info">Global Defaults</span>
    </div>
    
    <div class="card-body">
        <p class="text-muted mb-4">
            Configure default display modes for each module. Users can override these settings 
            unless "Lock user override" is enabled.
        </p>
        
        <form id="globalDisplaySettingsForm" method="post" action="/atom-framework/public/api/admin/display-settings.php">
            <input type="hidden" name="form_action" value="update">
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%;">{{ __('Module') }}</th>
                            <th style="width: 20%;">{{ __('Default Mode') }}</th>
                            <th style="width: 20%;">{{ __('Available Modes') }}</th>
                            <th style="width: 10%;">{{ __('Per Page') }}</th>
                            <th style="width: 15%;">{{ __('Options') }}</th>
                            <th style="width: 15%;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php foreach ($allSettings as $setting): @endphp
                            @php $module = $setting['module'];
                            $availableModes = $setting['available_modes'] ?? []; @endphp
                            <tr data-module="@php echo $module; @endphp">
                                <td>
                                    <strong>@php echo $moduleLabels[$module] ?? ucfirst($module); @endphp</strong>
                                    <br>
                                    <small class="text-muted">@php echo $module; @endphp</small>
                                </td>
                                
                                <td>
                                    <select name="settings[@php echo $module; @endphp][display_mode]" 
                                            class="form-select form-select-sm default-mode-select"
                                            data-module="@php echo $module; @endphp">
                                        @php foreach ($allModes as $mode => $meta): @endphp
                                            @if(in_array($mode, $availableModes))
                                                <option value="@php echo $mode; @endphp" 
                                                    @php echo $setting['display_mode'] === $mode ? 'selected' : ''; @endphp>
                                                    @php echo $meta['name']; @endphp
                                                </option>
                                            @endif
                                        @php endforeach; @endphp
                                    </select>
                                </td>
                                
                                <td>
                                    <div class="available-modes-checkboxes">
                                        @php foreach ($allModes as $mode => $meta): @endphp
                                            <div class="form-check form-check-inline">
                                                <input type="checkbox" 
                                                       class="form-check-input available-mode-check"
                                                       name="settings[@php echo $module; @endphp][available_modes][]"
                                                       value="@php echo $mode; @endphp"
                                                       id="mode_@php echo $module; @endphp_@php echo $mode; @endphp"
                                                       data-module="@php echo $module; @endphp"
                                                       @php echo in_array($mode, $availableModes) ? 'checked' : ''; @endphp>
                                                <label class="form-check-label"
                                                       for="mode_@php echo $module; @endphp_@php echo $mode; @endphp"
                                                       title="{{ __("@php echo $meta['name']; @endphp") }}">
                                                    <i class="bi @php echo $meta['icon']; @endphp"></i> <span class="badge bg-secondary ms-1">Optional</span>
                                                </label>
                                            </div>
                                        @php endforeach; @endphp
                                    </div>
                                </td>
                                
                                <td>
                                    <select name="settings[@php echo $module; @endphp][items_per_page]" 
                                            class="form-select form-select-sm">
                                        @php foreach ([10, 20, 30, 50, 100] as $count): @endphp
                                            <option value="@php echo $count; @endphp"
                                                @php echo ($setting['items_per_page'] ?? 30) == $count ? 'selected' : ''; @endphp>
                                                @php echo $count; @endphp
                                            </option>
                                        @php endforeach; @endphp
                                    </select>
                                </td>
                                
                                <td>
                                    <div class="form-check form-switch mb-1">
                                        <input type="checkbox" 
                                               class="form-check-input"
                                               name="settings[@php echo $module; @endphp][show_thumbnails]"
                                               value="1"
                                               id="thumb_@php echo $module; @endphp"
                                               @php echo ($setting['show_thumbnails'] ?? 1) ? 'checked' : ''; @endphp>
                                        <label class="form-check-label small" for="thumb_@php echo $module; @endphp">
                                            Thumbnails <span class="badge bg-secondary ms-1">Optional</span>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch">
                                        <input type="checkbox" 
                                               class="form-check-input"
                                               name="settings[@php echo $module; @endphp][allow_user_override]"
                                               value="1"
                                               id="override_@php echo $module; @endphp"
                                               @php echo ($setting['allow_user_override'] ?? 1) ? 'checked' : ''; @endphp>
                                        <label class="form-check-label small" for="override_@php echo $module; @endphp">
                                            Allow user override <span class="badge bg-secondary ms-1">Optional</span>
                                        </label>
                                    </div>
                                </td>
                                
                                <td>
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-sm save-module-btn"
                                            data-module="@php echo $module; @endphp">
                                        <i class="bi bi-check"></i> Save
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-warning btn-sm reset-module-btn"
                                            data-module="@php echo $module; @endphp"
                                            title="{{ __('Reset to defaults') }}">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </td>
                            </tr>
                        @php endforeach; @endphp
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-outline-danger" id="resetAllBtn">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Reset All to Defaults
                </button>
                
                <button type="submit" class="btn atom-btn-white" id="saveAllBtn">
                    <i class="bi bi-save me-1"></i>
                    Save All Settings
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Audit Log Section -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>
            {{ __('Recent Changes') }}
        </h5>
    </div>
    <div class="card-body">
        <div id="auditLogContainer">
            <p class="text-muted">Loading audit log...</p>
        </div>
    </div>
</div>

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
document.addEventListener('DOMContentLoaded', function() {
    const API_BASE = '/atom-framework/public/api/admin/display-settings.php';
    
    // Save individual module
    document.querySelectorAll('.save-module-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const module = this.dataset.module;
            const row = document.querySelector(`tr[data-module="${module}"]`);
            
            const data = new FormData();
            data.append('action', 'update');
            data.append('module', module);
            
            // Collect values from row
            row.querySelectorAll('select, input').forEach(input => {
                if (input.type === 'checkbox') {
                    if (input.name.includes('available_modes')) {
                        if (input.checked) {
                            data.append(input.name, input.value);
                        }
                    } else {
                        data.append(input.name.replace(`settings[${module}]`, ''), input.checked ? 1 : 0);
                    }
                } else {
                    data.append(input.name.replace(`settings[${module}]`, ''), input.value);
                }
            });
            
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    body: data
                });
                const result = await response.json();
                
                if (result.success) {
                    this.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
                    setTimeout(() => {
                        this.innerHTML = '<i class="bi bi-check"></i> Save';
                        this.disabled = false;
                    }, 1500);
                } else {
                    throw new Error(result.error || 'Save failed');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                this.innerHTML = '<i class="bi bi-check"></i> Save';
                this.disabled = false;
            }
        });
    });
    
    // Reset individual module
    document.querySelectorAll('.reset-module-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const module = this.dataset.module;
            
            if (!confirm(`Reset ${module} display settings to defaults?`)) {
                return;
            }
            
            const data = new FormData();
            data.append('action', 'reset');
            data.append('module', module);
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    body: data
                });
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    throw new Error(result.error || 'Reset failed');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });
    });
    
    // Update default mode dropdown when available modes change
    document.querySelectorAll('.available-mode-check').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const module = this.dataset.module;
            const modeSelect = document.querySelector(`.default-mode-select[data-module="${module}"]`);
            const checkedModes = [];
            
            document.querySelectorAll(`.available-mode-check[data-module="${module}"]:checked`).forEach(cb => {
                checkedModes.push(cb.value);
            });
            
            // Update select options
            Array.from(modeSelect.options).forEach(option => {
                option.disabled = !checkedModes.includes(option.value);
            });
            
            // If current selection is now disabled, select first available
            if (modeSelect.selectedOptions[0]?.disabled) {
                const firstEnabled = Array.from(modeSelect.options).find(o => !o.disabled);
                if (firstEnabled) {
                    modeSelect.value = firstEnabled.value;
                }
            }
        });
    });
    
    // Load audit log
    loadAuditLog();
    
    async function loadAuditLog() {
        try {
            const response = await fetch(API_BASE + '?action=audit&scope=global&limit=20');
            const result = await response.json();
            
            if (result.success && result.entries.length > 0) {
                let html = '<table class="table table-sm"><thead><tr>';
                html += '<th>Date</th><th>Module</th><th>Action</th><th>Changed By</th>';
                html += '</tr></thead><tbody>';
                
                result.entries.forEach(entry => {
                    html += `<tr>
                        <td><small>${new Date(entry.created_at).toLocaleString()}</small></td>
                        <td><code>${entry.module}</code></td>
                        <td><span class="badge bg-${entry.action === 'reset' ? 'warning' : 'info'}">${entry.action}</span></td>
                        <td>${entry.changed_by || 'System'}</td>
                    </tr>`;
                });
                
                html += '</tbody></table>';
                document.getElementById('auditLogContainer').innerHTML = html;
            } else {
                document.getElementById('auditLogContainer').innerHTML = 
                    '<p class="text-muted">No recent changes recorded.</p>';
            }
        } catch (error) {
            document.getElementById('auditLogContainer').innerHTML = 
                '<p class="text-danger">Failed to load audit log.</p>';
        }
    }
});
</script>
