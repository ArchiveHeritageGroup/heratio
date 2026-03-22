@php /**
 * Enhanced Search Toggle - Include near search box in browse templates
 */
require_once sfConfig::get('sf_root_dir') . '/atom-framework/bootstrap.php';

use AtomFramework\Services\Search\SearchIntegrationService;

$searchService = new SearchIntegrationService();
$isEnabled = $searchService->isEnhancedSearchEnabled();
$userPref = $sf_user->getAttribute('enhanced_search', $isEnabled ? '1' : '0'); @endphp

@if($isEnabled)
<div class="enhanced-search-toggle d-inline-flex align-items-center ms-2" 
     title="{{ __('Smart search uses synonyms and related terms for better results') }}">
    <div class="form-check form-switch mb-0">
        <input class="form-check-input" type="checkbox" role="switch" 
               id="enhancedSearchToggle" 
               @php echo $userPref === '1' ? 'checked' : ''; @endphp>
        <label class="form-check-label small text-muted" for="enhancedSearchToggle">
            <i class="bi bi-stars me-1"></i>{{ __('Smart') }}
        </label>
    </div>
</div>

<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
document.getElementById('enhancedSearchToggle')?.addEventListener('change', function() {
    sessionStorage.setItem('enhancedSearch', this.checked ? '1' : '0');
});
</script>
@endif
