@php /* DROPDOWN VERSION 2025-01-14 */ @endphp
@php error_log("PARTIAL LOADED: _advancedSearchEnhancements.php");
/**
 * Advanced Search Enhancements - Simplified
 */

$user = sfContext::getInstance()->getUser();
$isAuthenticated = $user->isAuthenticated();
$isAdmin = $user->isAdministrator();

// Get saved searches directly with simple query
$savedSearches = [];
$templates = [];

try {
    if ($isAuthenticated) {
        $userId = $user->getAttribute('user_id');
        $savedSearches = \Illuminate\Database\Capsule\Manager::table('saved_search')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    $templates = \Illuminate\Database\Capsule\Manager::table('search_template')
        ->where('is_active', 1)
        ->where('is_featured', 1)
        ->orderBy('sort_order')
        ->limit(6)
        ->get()
        ->toArray();
} catch (Exception $e) {
    // Silently fail if tables don't exist
} @endphp

<div class="advanced-search-enhancements mt-3 pt-2 border-top">
  @if(!empty($templates))
  <div class="mb-2">
    <span class="text-muted small me-2"><i class="fa fa-bolt me-1"></i>{{ __('Quick Searches') }}</span>
    @php foreach ($templates as $template): @endphp
    @php $params = json_decode($template->search_params, true) ?: []; @endphp
    <a href="@php echo url_for('@glam_browse') . '?' . http_build_query($params); @endphp"
       class="btn btn-sm btn-outline-@php echo esc_entities($template->color ?: 'secondary'); @endphp py-0 px-2">
      <i class="fa @php echo esc_entities($template->icon ?: 'fa-search'); @endphp me-1"></i>
      @php echo esc_entities($template->name); @endphp
    </a>
    @php endforeach; @endphp
  </div>
  @endif

  @if($isAuthenticated)
  <div class="d-flex align-items-center flex-wrap gap-2">
    @if(!empty($savedSearches))
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-success dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa fa-bookmark me-1"></i>{{ __('Saved Searches') }} (@php echo count($savedSearches); @endphp)
      </button>
      <ul class="dropdown-menu">
        @php foreach ($savedSearches as $saved): @endphp
        @php $params = json_decode($saved->search_params, true) ?: []; @endphp
        <li><a class="dropdown-item" href="@php echo url_for('@glam_browse') . '?' . http_build_query($params); @endphp">
          <i class="fa fa-search me-2 text-muted"></i>@php echo esc_entities($saved->name); @endphp
        </a></li>
        @php endforeach; @endphp
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="/index.php/searchEnhancement/savedSearches">
          <i class="fa fa-cog me-2 text-muted"></i>{{ __('Manage Saved Searches') }}
        </a></li>
      </ul>
    </div>
    @endif
    
    @if(!empty($_GET))
    <button type="button" class="btn btn-sm atom-btn-white py-0 px-2" data-bs-toggle="modal" data-bs-target="#saveSearchModal">
      <i class="fa fa-bookmark me-1"></i>{{ __('Save Search') }}
    </button>
    @endif
  </div>
  @endif
</div>

@if($isAuthenticated)
<div class="modal fade" id="saveSearchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Save This Search') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Name') }} * <span class="badge bg-danger ms-1">Required</span></label>
          <input type="text" id="save-search-name" class="form-control" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="save-search-public">
          <label class="form-check-label" for="save-search-public">
            <i class="fa fa-link me-1"></i>{{ __('Make public (shareable link)') }}
           <span class="badge bg-secondary ms-1">Optional</span></label>
        </div>
        @if($isAdmin)
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="save-search-global">
          <label class="form-check-label" for="save-search-global">
            <i class="fa fa-globe me-1"></i>{{ __('Global (visible to all users)') }}
           <span class="badge bg-secondary ms-1">Optional</span></label>
        </div>
        @endif
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="save-search-notify">
          <label class="form-check-label" for="save-search-notify">{{ __('Notify me of new results') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn atom-btn-white" onclick="saveCurrentSearch()">{{ __('Save') }}</button>
      </div>
    </div>
  </div>
</div>
<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
function saveCurrentSearch() {
  var name = document.getElementById('save-search-name').value;
  if (!name) { alert('Please enter a name'); return; }
  var notify = document.getElementById('save-search-notify').checked ? 1 : 0;
  var isPublic = document.getElementById('save-search-public').checked ? 1 : 0;
  var isGlobal = document.getElementById('save-search-global')?.checked ? 1 : 0;
  var params = window.location.search.substring(1);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '/index.php/searchEnhancement/saveSearch', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var result = JSON.parse(xhr.responseText);
        if (result.success) {
          var modal = bootstrap.Modal.getInstance(document.getElementById('saveSearchModal'));
          if (modal) modal.hide();
          alert('Search saved!');
          location.reload();
        } else {
          alert(result.error || 'Error saving');
        }
      } catch(e) {
        alert('Error: ' + e.message);
      }
    }
  };
  xhr.send('name=' + encodeURIComponent(name) + '&notify=' + notify + '&is_public=' + isPublic + '&is_global=' + isGlobal + '&search_params=' + encodeURIComponent(params) + '&entity_type=informationobject');
}
</script>
@endif
