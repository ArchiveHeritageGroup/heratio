@php /**
 * Enhanced Simple Search Options Dropdown
 * Includes: Global search, Advanced search, Search Templates, Saved Searches, History
 * 
 * Overrides: apps/qubit/modules/search/templates/_simpleSearchOptions.php
 */

// Load search service
\AhgCore\Core\AhgDb::init();
$searchService = new \App\Services\AdvancedSearchService();

$user = sfContext::getInstance()->getUser();
$isAuthenticated = $user->isAuthenticated();
$userId = $isAuthenticated ? $user->getAttribute('user_id') : null;
$sessionId = session_id();

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
  <a class="dropdown-item" href="@php echo url_for('@glam_browse') . '?showAdvanced=true'; @endphp">
    <i class="fa fa-sliders-h me-2"></i>{{ __('Advanced search') }}
  </a>
  
  <div class="dropdown-divider"></div>
  
  <!-- Quick Search Templates -->
  @if(!empty($templates))
  <h6 class="dropdown-header">
    <i class="fa fa-bolt me-1"></i>{{ __('Quick Searches') }}
  </h6>
  @php foreach ($templates as $template): @endphp
  <a class="dropdown-item" href="@php echo url_for(['module' => 'searchEnhancement', 'action' => 'runTemplate', 'id' => $template->id]); @endphp">
    <i class="fa @php echo esc_entities($template->icon); @endphp me-2 text-@php echo esc_entities($template->color); @endphp"></i>
    @php echo esc_entities($template->name); @endphp
  </a>
  @php endforeach; @endphp
  <a class="dropdown-item text-muted small" href="@php echo route('searchEnhancement.adminTemplates'); @endphp">
    <i class="fa fa-cog me-2"></i>{{ __('Manage templates') }}
  </a>
  <div class="dropdown-divider"></div>
  @endif
  
  <!-- Saved Searches (Authenticated users) -->
  @if($isAuthenticated && !empty($savedSearches))
  <h6 class="dropdown-header">
    <i class="fa fa-bookmark me-1"></i>{{ __('Saved Searches') }}
  </h6>
  @php foreach ($savedSearches as $saved): @endphp
  <a class="dropdown-item" href="@php echo url_for(['module' => 'searchEnhancement', 'action' => 'runSavedSearch', 'id' => $saved->id]); @endphp">
    <i class="fa fa-bookmark-o me-2"></i>
    @php echo esc_entities($saved->name); @endphp
    @if($saved->notify_new_results)
      <i class="fa fa-bell text-info ms-1" title="{{ __('Notifications on') }}"></i>
    @endif
  </a>
  @php endforeach; @endphp
  <a class="dropdown-item text-muted small" href="@php echo route('searchEnhancement.savedSearches'); @endphp">
    <i class="fa fa-list me-2"></i>{{ __('All saved searches') }}
  </a>
  <div class="dropdown-divider"></div>
  @endif
  
  <!-- Recent Searches -->
  @if(!empty($history))
  <h6 class="dropdown-header">
    <i class="fa fa-history me-1"></i>{{ __('Recent Searches') }}
  </h6>
  @php foreach ($history as $item): @endphp
  @php $params = json_decode($item->search_params, true) ?: [];
    $searchUrl = url_for('@glam_browse') . '?' . http_build_query($params); @endphp
  <a class="dropdown-item" href="@php echo $searchUrl; @endphp">
    <i class="fa fa-search me-2 text-muted"></i>
    @php echo esc_entities(mb_substr($item->search_query ?: __('(Advanced)'), 0, 30)); @endphp
    <small class="text-muted">(@php echo $item->result_count; @endphp)</small>
  </a>
  @php endforeach; @endphp
  <a class="dropdown-item text-muted small" href="@php echo route('searchEnhancement.history'); @endphp">
    <i class="fa fa-clock me-2"></i>{{ __('View all history') }}
  </a>
  <div class="dropdown-divider"></div>
  @endif
  
  <!-- Popular Searches -->
  @if(!empty($popular))
  <h6 class="dropdown-header">
    <i class="fa fa-fire me-1"></i>{{ __('Popular') }}
  </h6>
  @php foreach (array_slice($popular, 0, 3) as $p): @endphp
  @php $params = json_decode($p->search_params, true) ?: [];
    $searchUrl = url_for('@glam_browse') . '?' . http_build_query($params); @endphp
  <a class="dropdown-item" href="@php echo $searchUrl; @endphp">
    <i class="fa fa-trending-up me-2 text-warning"></i>
    @php echo esc_entities($p->search_query); @endphp
    <small class="text-muted">(@php echo $p->search_count; @endphp)</small>
  </a>
  @php endforeach; @endphp
  @endif
</div>

<style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
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
