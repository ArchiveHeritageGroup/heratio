{{-- Partial: Favorite toggle button --}}
@props(['slug' => '', 'isFavorite' => false])
<button type="button" class="btn btn-sm {{ $isFavorite ? 'btn-warning' : 'atom-btn-white' }} favorite-toggle" data-slug="{{ $slug }}" data-url="{{ route('favorites.ajax.toggle') }}" title="{{ $isFavorite ? 'Remove from favorites' : 'Add to favorites' }}">
  <i class="fas fa-star"></i>
</button>
