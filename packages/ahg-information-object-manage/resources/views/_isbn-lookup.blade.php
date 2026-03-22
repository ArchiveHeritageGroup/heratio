@php /**
 * ISBN Lookup Component
 *
 * Include in information object edit forms to enable ISBN-based
 * metadata auto-population from WorldCat/Open Library/Google Books.
 *
 * Usage: <?php include_partial('informationobject/isbnLookup', ['resource' => $resource]) @endphp
 */

$resourceId = isset($resource) && $resource->id ? $resource->id : '';
?>

<div class="card mb-3 isbn-lookup-card">
    <div class="card-header bg-light">
        <h5 class="card-title mb-0">
            <i class="fas fa-barcode me-2"></i>{{ __('ISBN Lookup') }}
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            {{ __('Enter an ISBN to automatically populate metadata from WorldCat, Open Library, or Google Books.') }}
        </p>
        
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label for="isbnLookupInput" class="form-label">
                    {{ __('ISBN-10 or ISBN-13') }}
                 <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" 
                       id="isbnLookupInput" 
                       class="form-control" 
                       placeholder="{{ __('e.g., 978-0-13-468599-1') }}"
                       data-isbn-lookup
                       autocomplete="off">
                <div class="form-text">
                    {{ __('Hyphens and spaces are optional') }}
                </div>
            </div>
            <div class="col-md-4">
                <button type="button" 
                        id="isbnLookupBtn" 
                        class="btn atom-btn-white w-100"
                        onclick="IsbnLookup.lookup(document.getElementById('isbnLookupInput').value)">
                    <i class="fas fa-search me-1"></i>{{ __('Lookup ISBN') }}
                </button>
            </div>
        </div>
        
        @if($resourceId)
        <script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
            document.addEventListener('DOMContentLoaded', function() {
                IsbnLookup.setObjectId(@php echo (int) $resourceId; @endphp);
            });
        </script>
        @endif
    </div>
</div>

<style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
.isbn-lookup-card .form-control.is-valid {
    border-color: var(--ahg-primary, #005837);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
}
.isbn-lookup-card .form-control.is-invalid {
    border-color: #dc3545;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
}
</style>
