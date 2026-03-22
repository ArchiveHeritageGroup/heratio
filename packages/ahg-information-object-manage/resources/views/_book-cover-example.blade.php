@php /**
 * Example: Display book cover in information object view.
 *
 * Usage in template:
 * <?php include_partial('informationobject/bookCoverExample', ['isbn' => '978-0-13-468599-1']) @endphp
 */

use AtomFramework\Services\BookCoverService;

// Get ISBN from resource properties or parameter
$isbn = $isbn ?? $resource->getPropertyByName('isbn13')?->getValue() 
             ?? $resource->getPropertyByName('isbn10')?->getValue();

if (!$isbn): ?>
    <!-- No ISBN available -->
@php else: 
    $covers = BookCoverService::getAllSizes($isbn); @endphp

<div class="book-cover-container text-center mb-3">
    <!-- Primary: Open Library (direct URL, no API call) -->
    <a href="@php echo $covers['large']; @endphp" 
       target="_blank" 
       title="{{ __('View larger cover') }}">
        <img src="@php echo $covers['medium']; @endphp" 
             alt="{{ __('Book cover') }}"
             class="img-fluid rounded shadow-sm"
             loading="lazy"
             style="max-height: 300px;"
             onerror="this.onerror=null; this.src='/plugins/ahgThemeB5Plugin/images/no-cover.png';">
    </a>
    
    <div class="mt-2">
        <small class="text-muted">
            {{ __('ISBN: %1%', ['%1%' => $isbn]) }}
        </small>
    </div>
    
    <!-- Optional: Link to view all sizes -->
    <div class="btn-group btn-group-sm mt-2">
        <a href="@php echo $covers['small']; @endphp" class="btn btn-outline-secondary" target="_blank">S</a>
        <a href="@php echo $covers['medium']; @endphp" class="btn btn-outline-secondary" target="_blank">M</a>
        <a href="@php echo $covers['large']; @endphp" class="btn btn-outline-secondary" target="_blank">L</a>
    </div>
</div>

@endif
