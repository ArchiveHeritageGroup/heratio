@php /**
 * Semantic Search Expansion Info Partial
 *
 * Displays information about how the search query was expanded using synonyms.
 * Include this partial on search results pages when semantic search is enabled.
 *
 * Variables:
 *   $expansionInfo - Array from SemanticSearchService::getExpansionInfo()
 *   $query - The original search query
 *
 * @package ahgThemeB5Plugin
 * @author Johan Pieterse - The Archive and Heritage Group
 */

// Check if semantic search expansion data is available
if (!isset($expansionInfo) || !$expansionInfo['expanded']) {
    return;
} @endphp

<div class="semantic-expansion-info alert alert-info alert-dismissible fade show mb-3" role="alert">
  <div class="d-flex align-items-start">
    <i class="fas fa-brain fa-lg me-3 mt-1 text-info" aria-hidden="true"></i>
    <div class="flex-grow-1">
      <strong>{{ __('Semantic Search Active') }}</strong>
      <p class="mb-1 small">
        {{ __('Your search has been expanded with related terms:') }}
      </p>

      @if(!empty($expansionInfo['terms']))
        <div class="expansion-terms">
          @php foreach ($expansionInfo['terms'] as $originalTerm => $synonyms): @endphp
            <div class="term-expansion mb-1">
              <code class="bg-light px-1 rounded">{{ $originalTerm }}</code>
              <i class="fas fa-arrow-right mx-2 text-muted small" aria-hidden="true"></i>
              <span class="synonyms">
                @php $synonymList = array_map(function ($syn) {
                    return '<span class="badge bg-secondary">' . htmlspecialchars($syn) . '</span>';
                }, $synonyms);
                echo implode(' ', $synonymList); @endphp
              </span>
            </div>
          @php endforeach; @endphp
        </div>
      @endif

      <p class="mb-0 mt-2 small text-muted">
        <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
        {{ __('Disable semantic search in the search options to search for exact terms only.') }}
      </p>
    </div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
</div>

<style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
.semantic-expansion-info {
  background-color: #e8f4f8;
  border-color: #bee5eb;
}
.semantic-expansion-info .expansion-terms {
  font-size: 0.9rem;
}
.semantic-expansion-info .synonyms .badge {
  font-weight: normal;
  font-size: 0.8rem;
}
</style>
