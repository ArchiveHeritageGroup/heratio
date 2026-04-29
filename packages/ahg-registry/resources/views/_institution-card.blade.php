{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_institutionCard.php --}}
@php
    $typeBg = [
        'archive' => 'bg-primary text-white',
        'library' => 'bg-success text-white',
        'museum' => 'bg-purple text-white',
        'gallery' => 'bg-orange text-white',
        'dam' => 'bg-teal text-white',
        'heritage_site' => 'bg-warning text-dark',
        'research_centre' => 'bg-info text-dark',
        'government' => 'bg-secondary text-white',
        'university' => 'bg-dark text-white',
        'academic' => 'bg-danger text-white',
        'community' => 'bg-success text-white',
        'private' => 'bg-secondary text-white',
    ];
    $type = $item->institution_type ?? '';
    $typeClass = $typeBg[$type] ?? 'bg-secondary';
    $typeStyle = '';
    if ('museum' === $type) $typeStyle = 'background-color:#6f42c1!important;';
    elseif ('gallery' === $type) $typeStyle = 'background-color:#fd7e14!important;';
    elseif ('dam' === $type) $typeStyle = 'background-color:#20c997!important;';

    $href = \Illuminate\Support\Facades\Route::has('registry.institutionView')
        ? route('registry.institutionView', ['id' => (int) ($item->id ?? 0)])
        : url('/registry/institution/' . ($item->id ?? 0));

    $sectors = [];
    if (!empty($item->glam_sectors)) {
        $sectors = is_string($item->glam_sectors) ? (json_decode($item->glam_sectors, true) ?: []) : (array) $item->glam_sectors;
    }
    $desc = $item->short_description ?? ($item->description ?? '');
    $isFav = !empty($showFavoriteToggle) && isset($userFavoriteIds) && is_array($userFavoriteIds) && in_array($item->id ?? 0, $userFavoriteIds);
    $favHref = \Illuminate\Support\Facades\Route::has('registry.favoriteToggle')
        ? route('registry.favoriteToggle')
        : url('/registry/favorite/toggle');
@endphp
<div class="col">
  <div class="card h-100">
    <div class="card-body">
      <div class="d-flex align-items-start mb-2">
        @if (!empty($item->logo_path))
          <img src="{{ $item->logo_path }}" alt="" class="rounded me-3 flex-shrink-0" style="width: 48px; height: 48px; object-fit: contain;">
        @else
          <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
            <i class="fas fa-university text-muted"></i>
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
          <span class="badge {{ $typeClass }}" style="{{ $typeStyle }}">{{ ucfirst(str_replace('_', ' ', $type)) }}</span>
          @if (!empty($myInstitutionIds) && is_array($myInstitutionIds) && in_array($item->id, $myInstitutionIds))
            <span class="badge bg-info ms-1"><i class="fas fa-user me-1"></i>{{ __('My Institution') }}</span>
          @endif
          @if (!empty($item->is_featured))
            <span class="badge bg-warning text-dark ms-1"><i class="fas fa-star me-1"></i>{{ __('Featured') }}</span>
          @endif
        </div>
      </div>

      @if (!empty($item->city) || !empty($item->country))
      <div class="small text-muted mb-2">
        <i class="fas fa-map-marker-alt me-1"></i>
        {{ implode(', ', array_filter([$item->city ?? '', $item->country ?? ''])) }}
      </div>
      @endif

      @if (is_array($sectors) && count($sectors) > 0)
      <div class="mb-2">
        @foreach ($sectors as $sector)
          <span class="badge bg-light text-dark border me-1 mb-1">{{ $sector }}</span>
        @endforeach
      </div>
      @endif

      @if (!empty($item->instance_count))
      <div class="small text-muted mb-2">
        <i class="fas fa-server me-1"></i>
        {{ (int) $item->instance_count }} {{ __('instance(s)') }}
      </div>
      @endif

      @if (!empty($desc))
      <p class="card-text small text-muted mb-0">
        {{ mb_strimwidth(strip_tags($desc), 0, 120, '...') }}
      </p>
      @endif
    </div>
    @if (!empty($showFavoriteToggle))
      <div class="card-footer bg-transparent border-0 pt-0 text-end" style="position:relative; z-index:2;">
        <form method="post" action="{{ $favHref }}" class="d-inline">
          @csrf
          <input type="hidden" name="entity_type" value="institution">
          <input type="hidden" name="entity_id" value="{{ (int) $item->id }}">
          <input type="hidden" name="return" value="{{ request()->getRequestUri() }}">
          <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none" title="{{ $isFav ? __('Remove from favorites') : __('Add to favorites') }}">
            <i class="fa{{ $isFav ? 's' : 'r' }} fa-star {{ $isFav ? 'text-warning' : 'text-muted' }}"></i>
          </button>
        </form>
      </div>
    @endif
  </div>
</div>
