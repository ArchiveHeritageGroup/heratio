@php /**
 * Search Enhancement Panel
 * 
 * Include in browse templates:
 * <?php include_partial('search/searchEnhancementPanel', ['entityType' => 'informationobject']); @endphp
 * 
 * Path: ' . sfConfig::get('sf_plugins_dir') . '/ahgThemeB5Plugin/modules/search/templates/_searchEnhancementPanel.php
 */

// Load service
\AhgCore\Core\AhgDb::init();
$searchService = new \App\Services\AdvancedSearchService();

$entityType = $entityType ?? 'informationobject';
$user = sfContext::getInstance()->getUser();
$isAuthenticated = $user->isAuthenticated();
$userId = $isAuthenticated ? $user->getAttribute('user_id') : null;
$sessionId = session_id();

// Get data
$history = $searchService->getUserHistory($userId, $sessionId, 5);
$templates = $searchService->getFeaturedTemplates();
$popular = $searchService->getPopularSearches(5, $entityType);
$savedSearches = $isAuthenticated ? $searchService->getSavedSearches($userId) : [];
?>

<div class="search-enhancement-panel mb-4">
  <!-- Quick Search Templates -->
  @if(!empty($templates))
  <div class="mb-3">
    <label class="form-label small text-muted">{{ __('Quick Searches') }} <span class="badge bg-secondary ms-1">Optional</span></label>
    <div class="d-flex flex-wrap gap-2">
      @php foreach ($templates as $template): @endphp
      <a href="@php echo url_for(['module' => 'searchEnhancement', 'action' => 'runTemplate', 'id' => $template->id]); @endphp" 
         class="btn btn-sm btn-outline-@php echo esc_entities($template->color); @endphp">
        <i class="fa @php echo esc_entities($template->icon); @endphp me-1"></i>
        @php echo esc_entities($template->name); @endphp
      </a>
      @php endforeach; @endphp
    </div>
  </div>
  @endif

  <!-- Search History & Saved -->
  <div class="row">
    @if(!empty($history))
    <div class="col-md-6 mb-3">
      <div class="card card-body bg-light">
        <h6 class="card-title mb-2">
          <i class="fa fa-history me-1"></i>{{ __('Recent Searches') }}
        </h6>
        <ul class="list-unstyled mb-0 small">
          @php foreach (array_slice($history, 0, 5) as $item): @endphp
          <li class="mb-1">
            <a href="@php echo url_for(['module' => $entityType, 'action' => 'browse']) . '?' . http_build_query(json_decode($item->search_params, true)); @endphp" 
               class="text-decoration-none">
              @php echo esc_entities($item->search_query ?: __('(Advanced)')); @endphp
              <span class="text-muted">(@php echo $item->result_count; @endphp)</span>
            </a>
          </li>
          @php endforeach; @endphp
        </ul>
        <a href="@php echo route('searchEnhancement.history'); @endphp" class="small">
          {{ __('View all') }} →
        </a>
      </div>
    </div>
    @endif

    @if($isAuthenticated && !empty($savedSearches))
    <div class="col-md-6 mb-3">
      <div class="card card-body bg-light">
        <h6 class="card-title mb-2">
          <i class="fa fa-bookmark me-1"></i>{{ __('Saved Searches') }}
        </h6>
        <ul class="list-unstyled mb-0 small">
          @php foreach (array_slice($savedSearches, 0, 5) as $saved): @endphp
          <li class="mb-1">
            <a href="@php echo url_for(['module' => 'searchEnhancement', 'action' => 'runSavedSearch', 'id' => $saved->id]); @endphp" 
               class="text-decoration-none">
              @php echo esc_entities($saved->name); @endphp
              @if($saved->notify_new_results)
                <i class="fa fa-bell text-info" title="{{ __('Notifications enabled') }}"></i>
              @endif
            </a>
          </li>
          @php endforeach; @endphp
        </ul>
        <a href="@php echo route('searchEnhancement.savedSearches'); @endphp" class="small">
          {{ __('Manage saved') }} →
        </a>
      </div>
    </div>
    @endif
  </div>

  <!-- Popular Searches -->
  @if(!empty($popular))
  <div class="mb-3">
    <label class="form-label small text-muted">{{ __('Popular Searches') }} <span class="badge bg-secondary ms-1">Optional</span></label>
    <div class="d-flex flex-wrap gap-1">
      @php foreach ($popular as $p): @endphp
      <a href="@php echo url_for(['module' => $entityType, 'action' => 'browse']) . '?' . http_build_query(json_decode($p->search_params, true)); @endphp" 
         class="badge bg-light text-dark text-decoration-none">
        @php echo esc_entities($p->search_query); @endphp
        <span class="text-muted">(@php echo $p->search_count; @endphp)</span>
      </a>
      @php endforeach; @endphp
    </div>
  </div>
  @endif
</div>

<!-- Save Search Modal -->
@if($isAuthenticated)
<div class="modal fade" id="saveSearchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-bookmark me-2"></i>{{ __('Save This Search') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Name') }} * <span class="badge bg-danger ms-1">Required</span></label>
          <input type="text" id="save-search-name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Description') }} <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea id="save-search-description" class="form-control" rows="2"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Tags') }} <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" id="save-search-tags" class="form-control" placeholder="{{ __('comma-separated') }}">
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="save-search-public">
          <label class="form-check-label" for="save-search-public">
            {{ __('Make public (shareable link)') }} <span class="badge bg-secondary ms-1">Optional</span>
          </label>
        </div>
	<div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="save-search-public">
          <label class="form-check-label" for="save-search-public">
            <i class="fa fa-link me-1"></i>{{ __('Make public (shareable link)') }} <span class="badge bg-secondary ms-1">Optional</span>
          </label>
        </div>
        @if($user->isAdministrator())
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="save-search-global">
          <label class="form-check-label" for="save-search-global">
            <i class="fa fa-globe me-1"></i>{{ __('Global (visible to all users)') }} <span class="badge bg-secondary ms-1">Optional</span>
          </label>
        </div>
        @endif
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="save-search-notify">
          <label class="form-check-label" for="save-search-notify">
            {{ __('Notify me of new results') }} <span class="badge bg-secondary ms-1">Optional</span>
          </label>
        </div>
        <div class="mb-3" id="notify-frequency-group" style="display:none;">
          <label class="form-label">{{ __('Notification frequency') }} <span class="badge bg-secondary ms-1">Optional</span></label>
          <select id="save-search-frequency" class="form-select">
            <option value="daily">{{ __('Daily') }}</option>
            <option value="weekly" selected>{{ __('Weekly') }}</option>
            <option value="monthly">{{ __('Monthly') }}</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn atom-btn-white" onclick="saveCurrentSearch()">
          <i class="fa fa-save me-1"></i>{{ __('Save Search') }}
        </button>
      </div>
    </div>
  </div>
</div>

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
// Toggle notification frequency
document.getElementById('save-search-notify')?.addEventListener('change', function() {
  document.getElementById('notify-frequency-group').style.display = this.checked ? 'block' : 'none';
});

// Save search function
function saveCurrentSearch() {
  const name = document.getElementById('save-search-name').value;
  if (!name) {
    alert('{{ __('Please enter a name') }}');
    return;
  }
  
const data = {
    name: name,
    description: document.getElementById('save-search-description')?.value || '',
    tags: document.getElementById('save-search-tags')?.value || '',
    is_public: document.getElementById('save-search-public')?.checked ? 1 : 0,
    is_global: document.getElementById('save-search-global')?.checked ? 1 : 0,
    notify: document.getElementById('save-search-notify')?.checked ? 1 : 0,
    frequency: document.getElementById('save-search-frequency')?.value || 'weekly',
    search_params: window.location.search.substring(1),
    entity_type: '@php echo $entityType; @endphp'
  };  
  fetch('@php echo route('searchEnhancement.saveSearch'); @endphp', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams(data)
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      bootstrap.Modal.getInstance(document.getElementById('saveSearchModal')).hide();
      alert('{{ __('Search saved!') }}');
    } else {
      alert(result.error || '{{ __('Error saving search') }}');
    }
  });
}

// Add Save Search button to search results
document.addEventListener('DOMContentLoaded', function() {
  const resultsHeader = document.querySelector('.search-results-header, .browse-header, h1');
  if (resultsHeader && window.location.search) {
    const btn = document.createElement('button');
    btn.className = 'btn btn-outline-primary btn-sm ms-2';
    btn.innerHTML = '<i class="fa fa-bookmark me-1"></i>{{ __('Save Search') }}';
    btn.setAttribute('data-bs-toggle', 'modal');
    btn.setAttribute('data-bs-target', '#saveSearchModal');
    resultsHeader.appendChild(btn);
  }
});
</script>
@endif
