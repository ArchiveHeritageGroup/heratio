@php /**
 * Display Settings Section for AHG Settings Dashboard
 * 
 * Include in your settings dashboard:
 * <?php include_partial('sfAHGPlugin/displaySettingsSection'); @endphp
 */
?>

<div class="settings-section" id="displaySettingsSection">
    <div class="settings-section-header d-flex justify-content-between align-items-center" 
         data-bs-toggle="collapse" 
         data-bs-target="#displaySettingsContent"
         aria-expanded="false"
         role="button">
        <h5 class="mb-0">
            <i class="bi bi-display me-2"></i>
            {{ __('Display Mode Settings') }}
        </h5>
        <div>
            <span class="badge bg-primary me-2">Global</span>
            <i class="bi bi-chevron-down collapse-icon"></i>
        </div>
    </div>
    
    <div class="collapse" id="displaySettingsContent">
        <div class="settings-section-body">
            @php include_partial('sfAHGPlugin/displaySettingsForm'); @endphp
        </div>
    </div>
</div>
