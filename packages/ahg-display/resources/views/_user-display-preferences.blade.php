@php /**
 * User Display Preferences Component
 * 
 * Include in user profile/settings page:
 * <?php include_component('sfAHGPlugin', 'userDisplayPreferences'); @endphp
 */

use AtomExtensions\Services\DisplayModeService;

$displayService = new DisplayModeService();

$modules = [
    'informationobject' => 'Archival Descriptions',
    'actor' => 'Authority Records',
    'repository' => 'Archival Institutions',
    'digitalobject' => 'Digital Objects',
    'library' => 'Library',
    'gallery' => 'Gallery',
    'dam' => 'Digital Asset Management',
    'search' => 'Search Results',
];

$allModes = [
    'tree' => ['name' => 'Hierarchy', 'icon' => 'bi-diagram-3'],
    'grid' => ['name' => 'Grid', 'icon' => 'bi-grid-3x3-gap'],
    'gallery' => ['name' => 'Gallery', 'icon' => 'bi-images'],
    'list' => ['name' => 'List', 'icon' => 'bi-list-ul'],
    'timeline' => ['name' => 'Timeline', 'icon' => 'bi-clock-history'],
];
?>

<div class="user-display-preferences">
    <h4 class="mb-3">
        <i class="bi bi-display me-2"></i>
        {{ __('Display Preferences') }}
    </h4>
    
    <p class="text-muted small mb-4">
        {{ __('Customize how content is displayed when browsing different modules. 
        Your preferences will be remembered across sessions.') }}
    </p>
    
    <div class="accordion" id="displayPrefsAccordion">
        @php foreach ($modules as $module => $label): @endphp
            @php $settings = $displayService->getDisplaySettings($module);
            $source = $settings['_source'] ?? 'default';
            $canOverride = $displayService->canOverride($module);
            $hasCustom = $displayService->hasCustomPreference($module);
            $availableModes = $displayService->getModeMetas($module); @endphp
            
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" 
                            data-bs-toggle="collapse" 
                            data-bs-target="#pref_@php echo $module; @endphp">
                        <span class="me-auto">@php echo $label; @endphp</span>
                        
                        @if(!$canOverride)
                            <span class="badge bg-secondary me-2" title="Locked by administrator">
                                <i class="bi bi-lock"></i>
                            </span>
                        @elseif($hasCustom)
                            <span class="badge bg-primary me-2" title="Custom preference">
                                <i class="bi bi-person-check"></i>
                            </span>
                        @else
                            <span class="badge bg-light text-dark me-2" title="Using default">
                                Default
                            </span>
                        @endif
                        
                        <span class="badge bg-info">
                            <i class="bi @php echo $allModes[$settings['display_mode']]['icon'] ?? 'bi-list-ul'; @endphp"></i>
                            @php echo $allModes[$settings['display_mode']]['name'] ?? 'List'; @endphp
                        </span>
                    </button>
                </h2>
                
                <div id="pref_@php echo $module; @endphp" class="accordion-collapse collapse" 
                     data-bs-parent="#displayPrefsAccordion">
                    <div class="accordion-body">
                        @if(!$canOverride)
                            <div class="alert alert-secondary">
                                <i class="bi bi-lock me-2"></i>
                                Display mode for this module is set by the administrator.
                            </div>
                        @else
                            <form class="user-pref-form" data-module="@php echo $module; @endphp">
                                <div class="row g-3">
                                    <!-- Display Mode -->
                                    <div class="col-md-6">
                                        <label class="form-label">{{ __('Display Mode') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                                        <div class="btn-group d-flex" role="group">
                                            @php foreach ($availableModes as $mode => $meta): @endphp
                                                <input type="radio" class="btn-check" 
                                                       name="display_mode" 
                                                       id="dm_@php echo $module; @endphp_@php echo $mode; @endphp"
                                                       value="@php echo $mode; @endphp"
                                                       @php echo $meta['active'] ? 'checked' : ''; @endphp>
                                                <label class="btn btn-outline-primary" 
                                                       for="dm_@php echo $module; @endphp_@php echo $mode; @endphp"
                                                       title="@php echo $meta['description']; @endphp">
                                                    <i class="bi @php echo $meta['icon']; @endphp"></i>
                                                    <span class="d-none d-lg-inline ms-1">@php echo $meta['name']; @endphp</span>
                                                </label>
                                            @php endforeach; @endphp
                                        </div>
                                    </div>
                                    
                                    <!-- Items Per Page -->
                                    <div class="col-md-3">
                                        <label class="form-label">{{ __('Items Per Page') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                                        <select name="items_per_page" class="form-select">
                                            @php foreach ([10, 20, 30, 50, 100] as $count): @endphp
                                                <option value="@php echo $count; @endphp"
                                                    @php echo ($settings['items_per_page'] ?? 30) == $count ? 'selected' : ''; @endphp>
                                                    @php echo $count; @endphp
                                                </option>
                                            @php endforeach; @endphp
                                        </select>
                                    </div>
                                    
                                    <!-- Card Size -->
                                    <div class="col-md-3">
                                        <label class="form-label">{{ __('Card Size') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                                        <select name="card_size" class="form-select">
                                            <option value="small" @php echo ($settings['card_size'] ?? 'medium') === 'small' ? 'selected' : ''; @endphp>Small</option>
                                            <option value="medium" @php echo ($settings['card_size'] ?? 'medium') === 'medium' ? 'selected' : ''; @endphp>Medium</option>
                                            <option value="large" @php echo ($settings['card_size'] ?? 'medium') === 'large' ? 'selected' : ''; @endphp>Large</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Options -->
                                    <div class="col-12">
                                        <div class="form-check form-check-inline">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="show_thumbnails" value="1"
                                                   id="thumb_@php echo $module; @endphp"
                                                   @php echo ($settings['show_thumbnails'] ?? 1) ? 'checked' : ''; @endphp>
                                            <label class="form-check-label" for="thumb_@php echo $module; @endphp">
                                                Show thumbnails
                                            </label>
                                        </div>
                                        
                                        <div class="form-check form-check-inline">
                                            <input type="checkbox" class="form-check-input" 
                                                   name="show_descriptions" value="1"
                                                   id="desc_@php echo $module; @endphp"
                                                   @php echo ($settings['show_descriptions'] ?? 1) ? 'checked' : ''; @endphp>
                                            <label class="form-check-label" for="desc_@php echo $module; @endphp">
                                                Show descriptions
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="col-12 d-flex justify-content-between">
                                        @if($hasCustom)
                                            <button type="button" class="btn btn-outline-secondary btn-sm reset-pref-btn">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                                Reset to Default
                                            </button>
                                        @else
                                            <span></span>
                                        @endif
                                        
                                        <button type="submit" class="btn atom-btn-white btn-sm">
                                            <i class="bi bi-save me-1"></i>
                                            Save Preference
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @php endforeach; @endphp
    </div>
</div>

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
document.addEventListener('DOMContentLoaded', function() {
    const API_BASE = '/atom-framework/public/api/display-mode.php';
    
    // Save preference forms
    document.querySelectorAll('.user-pref-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const module = this.dataset.module;
            const data = new FormData(this);
            data.append('action', 'preferences');
            data.append('module', module);
            
            const submitBtn = this.querySelector('[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    body: data
                });
                const result = await response.json();
                
                if (result.success) {
                    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Saved!';
                    submitBtn.classList.remove('atom-btn-white');
                    submitBtn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.classList.remove('btn-success');
                        submitBtn.classList.add('atom-btn-white');
                        submitBtn.disabled = false;
                    }, 2000);
                } else {
                    throw new Error(result.error || 'Save failed');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    });
    
    // Reset buttons
    document.querySelectorAll('.reset-pref-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const form = this.closest('form');
            const module = form.dataset.module;
            
            if (!confirm('Reset display preferences for this module to default?')) {
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
});
</script>
