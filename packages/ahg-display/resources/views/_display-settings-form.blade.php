@php /**
 * Display Settings Form (Compact version for dashboard)
 */

use AtomExtensions\Services\DisplayModeService;

$displayService = new DisplayModeService();
$allSettings = $displayService->getAllGlobalSettings();

$allModes = [
    'tree' => ['name' => 'Hierarchy', 'icon' => 'bi-diagram-3', 'color' => 'success'],
    'grid' => ['name' => 'Grid', 'icon' => 'bi-grid-3x3-gap', 'color' => 'primary'],
    'gallery' => ['name' => 'Gallery', 'icon' => 'bi-images', 'color' => 'info'],
    'list' => ['name' => 'List', 'icon' => 'bi-list-ul', 'color' => 'secondary'],
    'timeline' => ['name' => 'Timeline', 'icon' => 'bi-clock-history', 'color' => 'warning'],
];

$moduleLabels = [
    'informationobject' => ['label' => 'Archival Descriptions', 'icon' => 'bi-archive'],
    'actor' => ['label' => 'Authority Records', 'icon' => 'bi-people'],
    'repository' => ['label' => 'Institutions', 'icon' => 'bi-building'],
    'digitalobject' => ['label' => 'Digital Objects', 'icon' => 'bi-file-earmark-image'],
    'library' => ['label' => 'Library', 'icon' => 'bi-book'],
    'gallery' => ['label' => 'Gallery', 'icon' => 'bi-easel'],
    'dam' => ['label' => 'DAM', 'icon' => 'bi-folder2-open'],
    'search' => ['label' => 'Search Results', 'icon' => 'bi-search'],
]; @endphp

<div class="display-settings-dashboard">
    <!-- Quick Overview -->
    <div class="row g-3 mb-4">
        @php foreach ($allSettings as $setting): @endphp
            @php $module = $setting['module'];
            if (!isset($moduleLabels[$module])) continue;
            $moduleInfo = $moduleLabels[$module];
            $modeInfo = $allModes[$setting['display_mode']] ?? $allModes['list'];
            $availableModes = $setting['available_modes'] ?? [];
            $isLocked = !($setting['allow_user_override'] ?? 1); @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 display-module-card @php echo $isLocked ? 'border-warning' : ''; @endphp"
                     data-module="@php echo $module; @endphp">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="card-title mb-1">
                                    <i class="bi @php echo $moduleInfo['icon']; @endphp me-1"></i>
                                    @php echo $moduleInfo['label']; @endphp
                                </h6>
                                <small class="text-muted">@php echo $module; @endphp</small>
                            </div>
                            @if($isLocked)
                                <span class="badge bg-warning text-dark" title="{{ __('User override disabled') }}">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                            @endif
                        </div>
                        
                        <!-- Current Mode Display -->
                        <div class="current-mode mb-2">
                            <span class="badge bg-@php echo $modeInfo['color']; @endphp px-3 py-2">
                                <i class="bi @php echo $modeInfo['icon']; @endphp me-1"></i>
                                @php echo $modeInfo['name']; @endphp
                            </span>
                            <span class="text-muted small ms-2">
                                @php echo $setting['items_per_page'] ?? 30; @endphp/page
                            </span>
                        </div>
                        
                        <!-- Quick Mode Switcher -->
                        <div class="btn-group btn-group-sm w-100" role="group">
                            @php foreach ($allModes as $mode => $info): @endphp
                                @php $isAvailable = in_array($mode, $availableModes);
                                $isActive = $setting['display_mode'] === $mode; @endphp
                                <button type="button" 
                                        class="btn btn-outline-@php echo $info['color']; @endphp quick-mode-btn
                                               @php echo $isActive ? 'active' : ''; @endphp
                                               @php echo !$isAvailable ? 'disabled opacity-25' : ''; @endphp"
                                        data-module="@php echo $module; @endphp"
                                        data-mode="@php echo $mode; @endphp"
                                        title="{{ __("@php echo $info['name']; @endphp@php echo !$isAvailable ? ' (disabled)' : ''; @endphp") }}">
                                    <i class="bi @php echo $info['icon']; @endphp"></i>
                                </button>
                            @php endforeach; @endphp
                        </div>
                    </div>
                    
                    <div class="card-footer bg-transparent p-2">
                        <button type="button" 
                                class="btn btn-link btn-sm text-decoration-none p-0 edit-module-btn"
                                data-module="@php echo $module; @endphp"
                                data-bs-toggle="modal" 
                                data-bs-target="#editDisplayModal">
                            <i class="bi bi-pencil me-1"></i> {{ __('Edit Settings') }}
                        </button>
                    </div>
                </div>
            </div>
        @php endforeach; @endphp
    </div>
    
    <!-- Bulk Actions -->
    <div class="d-flex justify-content-between align-items-center border-top pt-3">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary btn-sm" id="setAllToList">
                <i class="bi bi-list-ul me-1"></i> {{ __('All to List') }}
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="setAllToGrid">
                <i class="bi bi-grid-3x3-gap me-1"></i> {{ __('All to Grid') }}
            </button>
        </div>
        
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="viewAuditLog"
                    data-bs-toggle="modal" data-bs-target="#auditLogModal">
                <i class="bi bi-clock-history me-1"></i> {{ __('View Changes') }}
            </button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="resetAllDefaults">
                <i class="bi bi-arrow-counterclockwise me-1"></i> {{ __('Reset All') }}
            </button>
        </div>
    </div>
</div>

<!-- Edit Display Settings Modal -->
<div class="modal fade" id="editDisplayModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-display me-2"></i>
                    Edit Display Settings: <span id="editModuleLabel"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editDisplayForm">
                    <input type="hidden" name="module" id="editModuleName">
                    
                    <div class="row g-3">
                        <!-- Default Display Mode -->
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Default Display Mode') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <div class="btn-group-vertical w-100" id="editModeGroup">
                                @php foreach ($allModes as $mode => $info): @endphp
                                    <input type="radio" class="btn-check" name="display_mode" 
                                           id="edit_mode_@php echo $mode; @endphp" value="@php echo $mode; @endphp">
                                    <label class="btn btn-outline-@php echo $info['color']; @endphp text-start" 
                                           for="edit_mode_@php echo $mode; @endphp">
                                        <i class="bi @php echo $info['icon']; @endphp me-2"></i>
                                        @php echo $info['name']; @endphp
                                     <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label>
                                @php endforeach; @endphp
                            </div>
                        </div>
                        
                        <!-- Available Modes -->
                        <div class="col-md-6">
                            <label class="form-label">{{ __('Available Modes') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <p class="text-muted small">Select which modes users can choose from</p>
                            
                            @php foreach ($allModes as $mode => $info): @endphp
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input available-mode-edit" 
                                           name="available_modes[]" value="@php echo $mode; @endphp"
                                           id="edit_avail_@php echo $mode; @endphp">
                                    <label class="form-check-label" for="edit_avail_@php echo $mode; @endphp">
                                        <i class="bi @php echo $info['icon']; @endphp me-1"></i>
                                        @php echo $info['name']; @endphp <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                                    </label>
                                </div>
                            @php endforeach; @endphp
                        </div>
                        
                        <!-- Items Per Page -->
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Items Per Page') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select name="items_per_page" id="editItemsPerPage" class="form-select">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        
                        <!-- Card Size -->
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Card Size') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select name="card_size" id="editCardSize" class="form-select">
                                <option value="small">{{ __('Small') }}</option>
                                <option value="medium">{{ __('Medium') }}</option>
                                <option value="large">{{ __('Large') }}</option>
                            </select>
                        </div>
                        
                        <!-- Sort Default -->
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Default Sort') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select name="sort_field" id="editSortField" class="form-select">
                                <option value="updated_at">{{ __('Last Updated') }}</option>
                                <option value="created_at">{{ __('Date Created') }}</option>
                                <option value="title">{{ __('Title') }}</option>
                                <option value="reference_code">{{ __('Reference Code') }}</option>
                            </select>
                        </div>
                        
                        <!-- Options -->
                        <div class="col-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="show_thumbnails" id="editShowThumbnails" value="1">
                                                <label class="form-check-label" for="editShowThumbnails">
                                                    Show Thumbnails <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="show_descriptions" id="editShowDescriptions" value="1">
                                                <label class="form-check-label" for="editShowDescriptions">
                                                    Show Descriptions <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="allow_user_override" id="editAllowOverride" value="1">
                                                <label class="form-check-label" for="editAllowOverride">
                                                    Allow User Override <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-warning" id="resetModuleBtn">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> {{ __('Reset to Default') }}
                </button>
                <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn atom-btn-white" id="saveDisplaySettings">
                    <i class="bi bi-save me-1"></i> {{ __('Save Settings') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history me-2"></i>
                    Display Settings Change Log
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="auditLogContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('Loading...') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
document.addEventListener('DOMContentLoaded', function() {
    const API_BASE = '/atom-framework/public/api/admin/display-settings.php';
    let currentModule = null;
    let moduleSettings = {};
    
    // Store settings data
    @php foreach ($allSettings as $setting): @endphp
    moduleSettings['@php echo $setting['module']; @endphp'] = @php echo json_encode($setting); @endphp;
    @php endforeach; @endphp
    
    // Quick mode switch
    document.querySelectorAll('.quick-mode-btn:not(.disabled)').forEach(btn => {
        btn.addEventListener('click', async function() {
            const module = this.dataset.module;
            const mode = this.dataset.mode;
            
            // Update UI immediately
            const card = this.closest('.display-module-card');
            card.querySelectorAll('.quick-mode-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Save to server
            const data = new FormData();
            data.append('action', 'update');
            data.append('module', module);
            data.append('display_mode', mode);
            
            try {
                const response = await fetch(API_BASE, { method: 'POST', body: data });
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error);
                }
                
                // Update badge
                const badge = card.querySelector('.current-mode .badge');
                const modeInfo = {
                    tree: { name: 'Hierarchy', color: 'success', icon: 'bi-diagram-3' },
                    grid: { name: 'Grid', color: 'primary', icon: 'bi-grid-3x3-gap' },
                    gallery: { name: 'Gallery', color: 'info', icon: 'bi-images' },
                    list: { name: 'List', color: 'secondary', icon: 'bi-list-ul' },
                    timeline: { name: 'Timeline', color: 'warning', icon: 'bi-clock-history' }
                }[mode];
                
                badge.className = `badge bg-${modeInfo.color} px-3 py-2`;
                badge.innerHTML = `<i class="bi ${modeInfo.icon} me-1"></i> ${modeInfo.name}`;
                
            } catch (error) {
                alert('Failed to save: ' + error.message);
                location.reload();
            }
        });
    });
    
    // Edit button click
    document.querySelectorAll('.edit-module-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            currentModule = this.dataset.module;
            const settings = moduleSettings[currentModule];
            
            // Update modal title
            document.getElementById('editModuleLabel').textContent = currentModule;
            document.getElementById('editModuleName').value = currentModule;
            
            // Set form values
            document.querySelector(`input[name="display_mode"][value="${settings.display_mode}"]`).checked = true;
            document.getElementById('editItemsPerPage').value = settings.items_per_page || 30;
            document.getElementById('editCardSize').value = settings.card_size || 'medium';
            document.getElementById('editSortField').value = settings.sort_field || 'updated_at';
            document.getElementById('editShowThumbnails').checked = settings.show_thumbnails == 1;
            document.getElementById('editShowDescriptions').checked = settings.show_descriptions == 1;
            document.getElementById('editAllowOverride').checked = settings.allow_user_override == 1;
            
            // Set available modes
            document.querySelectorAll('.available-mode-edit').forEach(cb => {
                cb.checked = (settings.available_modes || []).includes(cb.value);
            });
        });
    });
    
    // Save settings
    document.getElementById('saveDisplaySettings').addEventListener('click', async function() {
        const form = document.getElementById('editDisplayForm');
        const data = new FormData(form);
        data.append('action', 'update');
        
        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
        
        try {
            const response = await fetch(API_BASE, { method: 'POST', body: data });
            const result = await response.json();
            
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('editDisplayModal')).hide();
                location.reload();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            alert('Failed to save: ' + error.message);
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-save me-1"></i> Save Settings';
        }
    });
    
    // Reset module
    document.getElementById('resetModuleBtn').addEventListener('click', async function() {
        if (!confirm(`Reset ${currentModule} to default settings?`)) return;
        
        const data = new FormData();
        data.append('action', 'reset');
        data.append('module', currentModule);
        
        try {
            const response = await fetch(API_BASE, { method: 'POST', body: data });
            const result = await response.json();
            
            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('editDisplayModal')).hide();
                location.reload();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            alert('Failed to reset: ' + error.message);
        }
    });
    
    // Reset all
    document.getElementById('resetAllDefaults').addEventListener('click', async function() {
        if (!confirm('Reset ALL modules to default display settings? This cannot be undone.')) return;
        
        for (const module of Object.keys(moduleSettings)) {
            const data = new FormData();
            data.append('action', 'reset');
            data.append('module', module);
            await fetch(API_BASE, { method: 'POST', body: data });
        }
        
        location.reload();
    });
    
    // Bulk set all to list/grid
    document.getElementById('setAllToList').addEventListener('click', () => bulkSetMode('list'));
    document.getElementById('setAllToGrid').addEventListener('click', () => bulkSetMode('grid'));
    
    async function bulkSetMode(mode) {
        if (!confirm(`Set ALL modules to ${mode} view?`)) return;
        
        for (const module of Object.keys(moduleSettings)) {
            const data = new FormData();
            data.append('action', 'update');
            data.append('module', module);
            data.append('display_mode', mode);
            await fetch(API_BASE, { method: 'POST', body: data });
        }
        
        location.reload();
    }
    
    // Load audit log when modal opens
    document.getElementById('auditLogModal').addEventListener('show.bs.modal', async function() {
        const container = document.getElementById('auditLogContent');
        
        try {
            const response = await fetch(API_BASE + '?action=audit&limit=50');
            const result = await response.json();
            
            if (result.success && result.entries.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-sm table-hover">';
                html += '<thead><tr><th>Date</th><th>Module</th><th>Action</th><th>Scope</th><th>By</th></tr></thead><tbody>';
                
                result.entries.forEach(entry => {
                    const date = new Date(entry.created_at).toLocaleString();
                    const actionClass = {
                        create: 'success', update: 'info', delete: 'danger', reset: 'warning'
                    }[entry.action] || 'secondary';
                    
                    html += `<tr>
                        <td><small>${date}</small></td>
                        <td><code>${entry.module}</code></td>
                        <td><span class="badge bg-${actionClass}">${entry.action}</span></td>
                        <td><span class="badge bg-${entry.scope === 'global' ? 'primary' : 'secondary'}">${entry.scope}</span></td>
                        <td>${entry.changed_by || '-'}</td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = '<p class="text-muted text-center">No changes recorded yet.</p>';
            }
        } catch (error) {
            container.innerHTML = '<p class="text-danger text-center">Failed to load audit log.</p>';
        }
    });
});
</script>

<style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
.display-module-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.display-module-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.display-module-card.border-warning {
    border-width: 2px;
}
.quick-mode-btn {
    padding: 0.25rem 0.5rem;
}
.settings-section-header {
    padding: 1rem;
    background: var(--bs-light);
    border-radius: 0.375rem;
    cursor: pointer;
    transition: background 0.2s;
}
.settings-section-header:hover {
    background: var(--bs-gray-200);
}
.settings-section-header .collapse-icon {
    transition: transform 0.3s;
}
.settings-section-header[aria-expanded="true"] .collapse-icon {
    transform: rotate(180deg);
}
.settings-section-body {
    padding: 1.5rem;
    border: 1px solid var(--bs-border-color);
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
}
</style>
