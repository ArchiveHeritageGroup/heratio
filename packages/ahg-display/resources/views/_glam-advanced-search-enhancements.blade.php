{{-- GLAM Advanced Search Enhancements - with dropdown for saved searches --}}
@php
$isAuthenticated = auth()->check();
$isAdmin = $isAuthenticated && auth()->user()->is_admin;

$savedSearches = [];
try {
    if ($isAuthenticated) {
        $userId = auth()->id();
        $savedSearches = \Illuminate\Support\Facades\DB::table('saved_search')
            ->where('user_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }
} catch (\Exception $e) {
    // Silently fail
}
@endphp

<div class="advanced-search-enhancements mt-3 pt-2 border-top">
  @if($isAuthenticated)
  <div class="d-flex align-items-center flex-wrap gap-2">
    @if(!empty($savedSearches))
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-success dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bookmark me-1"></i>{{ __('Saved Searches') }} ({{ count($savedSearches) }})
      </button>
      <ul class="dropdown-menu">
        @foreach($savedSearches as $saved)
        @php $searchParams = json_decode($saved->search_params, true) ?: []; @endphp
        <li><a class="dropdown-item" href="{{ route('informationobject.browse') }}?{{ http_build_query($searchParams) }}">
          <i class="fas fa-search me-2 text-muted"></i>{{ e($saved->name) }}
        </a></li>
        @endforeach
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ route('searchEnhancement.savedSearches') }}">
          <i class="fas fa-cog me-2 text-muted"></i>{{ __('Manage Saved Searches') }}
        </a></li>
      </ul>
    </div>
    @endif

    @if(!empty(request()->all()))
    <button type="button" class="btn btn-sm btn-success py-0 px-2" data-bs-toggle="modal" data-bs-target="#saveGlamSearchModal">
      <i class="fas fa-bookmark me-1"></i>{{ __('Save Search') }}
    </button>
    @endif
  </div>
  @endif
</div>

@if($isAuthenticated)
<div class="modal fade" id="saveGlamSearchModal" tabindex="-1" aria-labelledby="saveGlamSearchModalLabel" aria-modal="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="saveGlamSearchModalLabel">{{ __('Save This Search') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Name') }} *</label>
          <input type="text" id="glam-save-search-name" class="form-control" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="glam-save-search-public">
          <label class="form-check-label" for="glam-save-search-public">
            <i class="fas fa-link me-1"></i>{{ __('Make public (shareable link)') }}
          </label>
        </div>
        @if($isAdmin)
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="glam-save-search-global">
          <label class="form-check-label" for="glam-save-search-global">
            <i class="fas fa-globe me-1"></i>{{ __('Global (visible to all users)') }}
          </label>
        </div>
        @endif
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="glam-save-search-notify">
          <label class="form-check-label" for="glam-save-search-notify">{{ __('Notify me of new results') }}</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        <button type="button" class="btn btn-primary" onclick="saveGlamSearch()">{{ __('Save') }}</button>
      </div>
    </div>
  </div>
</div>
<script{{ Vite::useCspNonce() ? ' nonce="' . Vite::cspNonce() . '"' : '' }}>
function saveGlamSearch() {
  var name = document.getElementById('glam-save-search-name').value;
  if (!name) { alert('Please enter a name'); return; }
  var notify = document.getElementById('glam-save-search-notify').checked ? 1 : 0;
  var isPublic = document.getElementById('glam-save-search-public').checked ? 1 : 0;
  var isGlobal = document.getElementById('glam-save-search-global')?.checked ? 1 : 0;
  var params = window.location.search.substring(1);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', '{{ route("searchEnhancement.saveSearch") }}', true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
  xhr.onload = function() {
    if (xhr.status === 200) {
      try {
        var result = JSON.parse(xhr.responseText);
        if (result.success) {
          var modal = bootstrap.Modal.getInstance(document.getElementById('saveGlamSearchModal'));
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
