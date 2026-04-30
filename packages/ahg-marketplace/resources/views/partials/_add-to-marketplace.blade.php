{{--
  Shared sidebar card: "Add to marketplace" link for any GLAM/DAM entity show page.
  Usage: @include('ahg-marketplace::partials._add-to-marketplace', ['ioId' => $record->id])

  Renders only if:
    - the user is authenticated
    - the marketplace_enabled setting is on (default: true)
    - the ahgmarketplace.seller-listing-create route exists (marketplace package booted)
--}}
@auth
@if(\AhgCore\Services\AhgSettingsService::getBool('marketplace_enabled', true)
    && \Illuminate\Support\Facades\Route::has('ahgmarketplace.seller-listing-create')
    && !empty($ioId))
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-store me-1"></i> {{ __('Marketplace') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('ahgmarketplace.seller-listing-create', ['io' => $ioId]) }}"
         class="list-group-item list-group-item-action small">
        <i class="fas fa-tag me-1"></i> {{ __('Add to marketplace') }}
      </a>
    </div>
  </div>
@endif
@endauth
