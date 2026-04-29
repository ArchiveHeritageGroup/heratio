{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_standardCard.php --}}
@php
    $catBg = [
        'descriptive' => 'bg-primary',
        'preservation' => 'bg-success',
        'rights' => 'bg-warning text-dark',
        'accounting' => 'bg-info text-dark',
        'compliance' => 'bg-danger',
        'metadata' => 'bg-secondary',
        'interchange' => 'bg-dark',
        'sector' => 'bg-primary',
    ];
    $cat = $item->category ?? '';
    $catClass = $catBg[strtolower($cat)] ?? 'bg-secondary';

    $acronym = $item->acronym ?? '';
    $name = $item->name ?? '';
    $shortDesc = $item->short_description ?? '';
    $issuingBody = $item->issuing_body ?? '';
    $version = $item->current_version ?? '';
    $pubYear = $item->publication_year ?? '';
    $isFeatured = !empty($item->is_featured);
    $extensionCount = (int) ($item->extension_count ?? 0);

    $rawSectors = $item->sector_applicability ?? '';
    $sectors = is_string($rawSectors) ? (json_decode($rawSectors, true) ?: []) : (is_array($rawSectors) ? $rawSectors : []);
    if (!is_array($sectors)) $sectors = [];

    $href = \Illuminate\Support\Facades\Route::has('registry.standardView')
        ? route('registry.standardView', ['id' => (int) ($item->id ?? 0)])
        : url('/registry/standard/' . ($item->id ?? 0));
@endphp
<div class="col">
  <div class="card h-100">
    <div class="card-body">
      <div class="mb-2">
        @if (!empty($acronym))
          <h5 class="card-title mb-0">
            <a href="{{ $href }}" class="text-decoration-none stretched-link">
              {{ $acronym }}
            </a>
          </h5>
          <small class="text-muted">{{ $name }}</small>
        @else
          <h6 class="card-title mb-0">
            <a href="{{ $href }}" class="text-decoration-none stretched-link">
              {{ $name }}
            </a>
          </h6>
        @endif
      </div>

      <div class="mb-2">
        <span class="badge {{ $catClass }}">{{ ucfirst($cat) }}</span>
        @if ($extensionCount > 0)
          <span class="badge bg-success"><i class="fas fa-puzzle-piece me-1"></i>Heratio +{{ $extensionCount }}</span>
        @endif
        @if ($isFeatured)
          <span class="badge bg-warning text-dark"><i class="fas fa-award me-1"></i>{{ __('Featured') }}</span>
        @endif
      </div>

      @if (!empty($issuingBody))
      <div class="small text-muted mb-2">
        <i class="fas fa-building me-1"></i>{{ $issuingBody }}
      </div>
      @endif

      @if (!empty($shortDesc))
      <p class="card-text small text-muted mb-2">
        {{ mb_strimwidth(strip_tags($shortDesc), 0, 140, '...') }}
      </p>
      @endif

      @if (!empty($sectors))
      <div class="mb-2">
        @foreach ($sectors as $s)
          <span class="badge bg-light text-dark border me-1" style="font-size: 0.7em;">{{ ucfirst($s) }}</span>
        @endforeach
      </div>
      @endif

      @if (!empty($version) || !empty($pubYear))
      <div class="small text-muted">
        @if (!empty($version))
          <span class="badge bg-secondary">{{ $version }}</span>
        @endif
        @if (!empty($pubYear))
          <span class="ms-1">{{ (int) $pubYear }}</span>
        @endif
      </div>
      @endif
    </div>
  </div>
</div>
