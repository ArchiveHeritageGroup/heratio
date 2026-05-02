{{--
  Marketplace — My Favourites (listings the current user has hearted)

  Cloned shell from my-following.blade.php; listing-card grid mirrors browse.blade.php.
--}}
@extends('theme::layouts.1col')
@section('title', __('My Favourites') . ' - ' . __('Marketplace'))
@section('body-class', 'marketplace my-favourites')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgmarketplace.browse') }}">{{ __('Marketplace') }}</a></li>
    <li class="breadcrumb-item active">{{ __('My Favourites') }}</li>
  </ol>
</nav>

@if(session('success') || session('notice'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') ?? session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">
    <i class="fas fa-heart text-danger me-2"></i>{{ __('My Favourites') }}
    @if(($total ?? 0) > 0)
      <span class="badge bg-secondary ms-2">{{ (int) $total }}</span>
    @endif
  </h1>
  <div class="btn-group">
    <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Browse') }}
    </a>
  </div>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3" id="listings-grid">
  @forelse($listings ?? [] as $listing)
    <div class="col">
      <div class="card h-100 position-relative">
        @php $favOn = in_array((int) $listing->id, $favouritedIds ?? [], true); @endphp
        <button type="button"
                class="btn btn-light position-absolute top-0 end-0 m-2 rounded-circle shadow-sm fav-toggle"
                style="z-index:2;width:36px;height:36px;padding:0;"
                data-listing-id="{{ (int) $listing->id }}"
                title="{{ $favOn ? __('Remove from favourites') : __('Add to favourites') }}">
          <i class="{{ $favOn ? 'fas' : 'far' }} fa-heart text-danger"></i>
        </button>
        <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">
          @if(!empty($listing->featured_image_path))
            <img src="{{ $listing->featured_image_path }}" class="card-img-top" alt="{{ $listing->title ?? '' }}" style="height:200px;object-fit:cover;">
          @else
            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:200px;">
              <i class="fas fa-image fa-3x text-muted"></i>
            </div>
          @endif
        </a>
        <div class="card-body">
          <h6 class="card-title">
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($listing->title ?? 'Untitled', 50) }}</a>
          </h6>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold">
              @if(!empty($listing->price_on_request))
                <span class="text-muted">{{ __('POR') }}</span>
              @else
                {{ $listing->currency ?? '' }} {{ number_format((float) ($listing->price ?? 0), 2) }}
              @endif
            </span>
            <span class="badge bg-info">{{ ucfirst($listing->sector ?? '') }}</span>
            @if(!empty($listing->has_3d))
              <span class="badge bg-dark ms-1" title="{{ __('3D model available') }}"><i class="fas fa-cube me-1"></i>3D</span>
            @endif
          </div>
          @if(($listing->listing_type ?? '') === 'fixed_price' && empty($listing->price_on_request))
            <form action="{{ route('cart.listing-add', ['listingId' => (int) $listing->id]) }}" method="POST" class="d-grid">
              @csrf
              <button type="submit" class="btn btn-sm btn-success">
                <i class="fas fa-cart-plus me-1"></i>{{ __('Add to cart') }}
              </button>
            </form>
          @elseif(($listing->listing_type ?? '') === 'auction')
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-sm btn-outline-warning d-block">
              <i class="fas fa-gavel me-1"></i>{{ __('Place bid') }}
            </a>
          @elseif(($listing->listing_type ?? '') === 'offer_only')
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $listing->slug ?? '']) }}" class="btn btn-sm btn-outline-primary d-block">
              <i class="fas fa-handshake me-1"></i>{{ __('Make offer') }}
            </a>
          @endif
        </div>
      </div>
    </div>
  @empty
    <div class="col-12">
      <div class="text-center py-5">
        <i class="far fa-heart fa-3x text-muted mb-3"></i>
        <h5>{{ __('No favourites yet') }}</h5>
        <p class="text-muted">{{ __('Tap the heart on any listing to save it here.') }}</p>
        <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-primary">{{ __('Browse Marketplace') }}</a>
      </div>
    </div>
  @endforelse
</div>

@php $totalPages = (int) ceil(($total ?? 0) / ($limit ?? 24)); @endphp
@if($totalPages > 1)
  <nav aria-label="{{ __('Page navigation') }}" class="mt-4">
    <ul class="pagination justify-content-center">
      <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
        <a class="page-link" href="?page={{ ($page ?? 1) - 1 }}">&laquo;</a>
      </li>
      @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
        <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
          <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
        </li>
      @endfor
      <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
        <a class="page-link" href="?page={{ ($page ?? 1) + 1 }}">&raquo;</a>
      </li>
    </ul>
  </nav>
@endif

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Heart toggle — same as browse.blade.php; on this page un-favouriting also
  // hides the card so the list stays consistent with what's saved.
  var csrf = document.querySelector('meta[name="csrf-token"]');
  document.querySelectorAll('.fav-toggle').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var id = btn.getAttribute('data-listing-id');
      if (!id) return;
      btn.disabled = true;
      fetch('/marketplace/api/' + id + '/favourite', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
          'Accept': 'application/json',
        },
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.favourited === false) {
          var col = btn.closest('.col');
          if (col) col.remove();
        }
      })
      .catch(function () {})
      .finally(function () { btn.disabled = false; });
    });
  });
});
</script>
@endpush
@endsection
