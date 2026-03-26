@php /**
 * Enhanced Simple Search Options Dropdown
 * Includes: Global search, Advanced search, Search Templates, Saved Searches, History
 */

$searchService = app(\App\Services\AdvancedSearchService::class);

$user = Auth::user();
$isAuthenticated = Auth::check();
$userId = $isAuthenticated ? $user->id : null;
$sessionId = session()->getId();

// Get data
$templates = $searchService->getFeaturedTemplates();
$history = $searchService->getUserHistory($userId, $sessionId, 5);
$savedSearches = $isAuthenticated ? array_slice($searchService->getSavedSearches($userId), 0, 5) : [];
$popular = $searchService->getPopularSearches(5); @endphp

<div class="dropdown-menu search-options-dropdown" id="search-options-dropdown">
  <!-- Global Search -->
  <div class="form-check px-3 py-2">
    <input class="form-check-input" type="radio" name="searchType" id="globalSearch" value="global" checked>
    <label class="form-check-label" for="globalSearch">
      <i class="fa fa-globe me-1"></i>{{ __('Global search') }}
    </label>
  </div>

  <!-- Advanced Search -->
  <a class="dropdown-item" href="{{ route('glam.browse') . '?showAdvanced=true' }}">
    <i class="fa fa-sliders-h me-2"></i>{{ __('Advanced search') }}
  </a>

  <div class="dropdown-divider"></div>

  <!-- Quick Search Templates -->
  @if(!empty($templates))
  <h6 class="dropdown-header">
    <i class="fa fa-bolt me-1"></i>{{ __('Quick Searches') }}
  </h6>
  @foreach($templates as $template)
  <a class="dropdown-item" href="{{ route('searchEnhancement.runTemplate', ['id' => $template->id]) }}">
    <i class="fa {{ e($template->icon) }} me-2 text-{{ e($template->color) }}"></i>
    {{ e($template->name) }}
  </a>
  @endforeach
  <a class="dropdown-item text-muted small" href="{{ route('searchEnhancement.adminTemplates') }}">
    <i class="fa fa-cog me-2"></i>{{ __('Manage templates') }}
  </a>
  <div class="dropdown-divider"></div>
  @endif

  <!-- Saved Searches (Authenticated users) -->
  @if($isAuthenticated && !empty($savedSearches))
  <h6 class="dropdown-header">
    <i class="fa fa-bookmark me-1"></i>{{ __('Saved Searches') }}
  </h6>
  @foreach($savedSearches as $saved)
  <a class="dropdown-item" href="{{ route('searchEnhancement.runSavedSearch', ['id' => $saved->id]) }}">
    <i class="fa fa-bookmark-o me-2"></i>
    {{ e($saved->name) }}
    @if($saved->notify_new_results)
      <i class="fa fa-bell text-info ms-1" title="{{ __('Notifications on') }}"></i>
    @endif
  </a>
  @endforeach
  <a class="dropdown-item text-muted small" href="{{ route('searchEnhancement.savedSearches') }}">
    <i class="fa fa-list me-2"></i>{{ __('All saved searches') }}
  </a>
  <div class="dropdown-divider"></div>
  @endif

  <!-- Recent Searches -->
  @if(!empty($history))
  <h6 class="dropdown-header">
    <i class="fa fa-history me-1"></i>{{ __('Recent Searches') }}
  </h6>
  @foreach($history as $item)
  @php $params = json_decode($item->search_params, true) ?: [];
    $searchUrl = route('glam.browse') . '?' . http_build_query($params); @endphp
  <a class="dropdown-item" href="{{ $searchUrl }}">
    <i class="fa fa-search me-2 text-muted"></i>
    {{ e(mb_substr($item->search_query ?: __('(Advanced)'), 0, 30)) }}
    <small class="text-muted">({{ $item->result_count }})</small>
  </a>
  @endforeach
  <a class="dropdown-item text-muted small" href="{{ route('searchEnhancement.history') }}">
    <i class="fa fa-clock me-2"></i>{{ __('View all history') }}
  </a>
  <div class="dropdown-divider"></div>
  @endif

  <!-- Popular Searches -->
  @if(!empty($popular))
  <h6 class="dropdown-header">
    <i class="fa fa-fire me-1"></i>{{ __('Popular') }}
  </h6>
  @foreach(array_slice($popular, 0, 3) as $p)
  @php $params = json_decode($p->search_params, true) ?: [];
    $searchUrl = route('glam.browse') . '?' . http_build_query($params); @endphp
  <a class="dropdown-item" href="{{ $searchUrl }}">
    <i class="fa fa-trending-up me-2 text-warning"></i>
    {{ e($p->search_query) }}
    <small class="text-muted">({{ $p->search_count }})</small>
  </a>
  @endforeach
  @endif
</div>

<style nonce="{{ csp_nonce() }}">
.search-options-dropdown {
  min-width: 280px;
  max-height: 70vh;
  overflow-y: auto;
}
.search-options-dropdown .dropdown-header {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #6c757d;
  padding-top: 0.75rem;
}
.search-options-dropdown .dropdown-item {
  padding: 0.4rem 1rem;
  font-size: 0.9rem;
}
.search-options-dropdown .dropdown-item.text-muted {
  font-size: 0.8rem;
}
.search-options-dropdown .dropdown-item:hover {
  background-color: #f8f9fa;
}
</style>
