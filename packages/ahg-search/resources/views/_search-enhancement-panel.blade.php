@php /**
 * Search Enhancement Panel
 *
 * Include in browse templates:
 * @include('ahg-search::_search-enhancement-panel', ['entityType' => 'informationobject'])
 */

$searchService = app(\App\Services\AdvancedSearchService::class);

$entityType = $entityType ?? 'informationobject';
$user = Auth::user();
$isAuthenticated = Auth::check();
$userId = $isAuthenticated ? $user->id : null;
$sessionId = session()->getId();

// Get data
$history = $searchService->getUserHistory($userId, $sessionId, 5);
$templates = $searchService->getFeaturedTemplates();
$popular = $searchService->getPopularSearches(5, $entityType);
$savedSearches = $isAuthenticated ? $searchService->getSavedSearches($userId) : [];
@endphp

<div class="search-enhancement-panel mb-4">
  <!-- Quick Search Templates -->
  @if(!empty($templates))
  <div class="mb-3">
    <label class="form-label small text-muted">{{ __('Quick Searches') }} <span class="badge bg-secondary ms-1">Optional</span></label>
    <div class="d-flex flex-wrap gap-2">
      @foreach($templates as $template)
      <a href="{{ route('searchEnhancement.runTemplate', ['id' => $template->id]) }}"
         class="btn btn-sm btn-outline-{{ e($template->color) }}">
        <i class="fa {{ e($template->icon) }} me-1"></i>
        {{ e($template->name) }}
      </a>
      @endforeach
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
          @foreach(array_slice($history, 0, 5) as $item)
          <li class="mb-1">
            @php $params = json_decode($item->search_params, true) ?: []; @endphp
            <a href="{{ route($entityType . '.browse') . '?' . http_build_query($params) }}"
               class="text-decoration-none">
              {{ e($item->search_query ?: __('(Advanced)')) }}
              <span class="text-muted">({{ $item->result_count }})</span>
            </a>
          </li>
          @endforeach
        </ul>
        <a href="{{ route('searchEnhancement.history') }}" class="small">
          {{ __('View all') }} &rarr;
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
          @foreach(array_slice($savedSearches, 0, 5) as $saved)
          <li class="mb-1">
            <a href="{{ route('searchEnhancement.runSavedSearch', ['id' => $saved->id]) }}"
               class="text-decoration-none">
              {{ e($saved->name) }}
              @if($saved->notify_new_results)
                <i class="fa fa-bell text-info" title="{{ __('Notifications enabled') }}"></i>
              @endif
            </a>
          </li>
          @endforeach
        </ul>
        <a href="{{ route('searchEnhancement.savedSearches') }}" class="small">
          {{ __('Manage saved') }} &rarr;
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
      @foreach($popular as $p)
      @php $params = json_decode($p->search_params, true) ?: []; @endphp
      <a href="{{ route($entityType . '.browse') . '?' . http_build_query($params) }}"
         class="badge bg-light text-dark text-decoration-none">
        {{ e($p->search_query) }}
        <span class="text-muted">({{ $p->search_count }})</span>
      </a>
      @endforeach
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
            <i class="fa fa-link me-1"></i>{{ __('Make public (shareable link)') }} <span class="badge bg-secondary ms-1">Optional</span>
          </label>
        </div>
        @if($user && $user->hasRole('administrator'))
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

<script nonce="{{ csp_nonce() }}">
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
    entity_type: '{{ $entityType }}'
  };
  fetch('{{ route('searchEnhancement.saveSearch') }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
    },
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
    btn.className = 'btn atom-btn-outline-primary btn-sm ms-2';
    btn.innerHTML = '<i class="fa fa-bookmark me-1"></i>{{ __('Save Search') }}';
    btn.setAttribute('data-bs-toggle', 'modal');
    btn.setAttribute('data-bs-target', '#saveSearchModal');
    resultsHeader.appendChild(btn);
  }
});
</script>
@endif
