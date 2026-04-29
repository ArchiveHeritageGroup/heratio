{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_vendorCard.php --}}
@php
    $vtBg = [
        'developer' => 'bg-primary',
        'integrator' => 'bg-success',
        'consultant' => 'bg-info text-dark',
        'service_provider' => 'bg-warning text-dark',
        'hosting' => 'bg-secondary',
        'hosting_provider' => 'bg-secondary',
        'digitization' => 'bg-dark',
        'training' => 'bg-danger',
        'reseller' => 'bg-info',
    ];
    $rawVt = $item->vendor_type ?? '[]';
    $vtArr = is_string($rawVt) ? (json_decode($rawVt, true) ?: []) : (is_array($rawVt) ? $rawVt : []);

    $href = \Illuminate\Support\Facades\Route::has('registry.vendorView')
        ? route('registry.vendorView', ['id' => (int) ($item->id ?? 0)])
        : url('/registry/vendor/' . ($item->id ?? 0));

    $desc = $item->short_description ?? ($item->description ?? '');
@endphp
<div class="col">
  <div class="card h-100">
    <div class="card-body">
      <div class="d-flex align-items-start mb-2">
        @if (!empty($item->logo_path))
          <img src="{{ $item->logo_path }}" alt="" class="rounded me-3 flex-shrink-0" style="width: 48px; height: 48px; object-fit: contain;">
        @else
          <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
            <i class="fas fa-handshake text-muted"></i>
          </div>
        @endif
        <div class="min-width-0">
          <h6 class="card-title mb-1">
            <a href="{{ $href }}" class="text-decoration-none stretched-link">
              {{ $item->name ?? '' }}
            </a>
            @if (!empty($item->is_verified))
              <i class="fas fa-check-circle text-primary ms-1" title="{{ __('Verified') }}"></i>
            @endif
          </h6>
          @foreach ($vtArr as $vt)<span class="badge {{ $vtBg[$vt] ?? 'bg-secondary' }} me-1">{{ ucfirst(str_replace('_', ' ', $vt)) }}</span>@endforeach
        </div>
      </div>

      @if (!empty($item->country))
      <div class="small text-muted mb-2">
        <i class="fas fa-map-marker-alt me-1"></i>
        {{ implode(', ', array_filter([$item->city ?? '', $item->country ?? ''])) }}
      </div>
      @endif

      @if (!empty($item->client_count))
      <div class="small text-muted mb-2">
        <i class="fas fa-users me-1"></i>
        {{ (int) $item->client_count }} {{ __('clients') }}
      </div>
      @endif

      @if (!empty($item->average_rating) && ($item->rating_count ?? 0) > 0)
      <div class="mb-2">
        @include('ahg-registry::_rating-stars', ['rating' => (float) $item->average_rating, 'count' => (int) ($item->rating_count ?? 0)])
      </div>
      @endif

      @if (!empty($desc))
      <p class="card-text small text-muted mb-0">
        {{ mb_strimwidth(strip_tags($desc), 0, 120, '...') }}
      </p>
      @endif
    </div>
  </div>
</div>
