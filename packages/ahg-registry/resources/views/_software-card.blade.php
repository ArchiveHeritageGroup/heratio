{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_softwareCard.php --}}
@php
    $catBg = [
        'ams' => 'bg-primary', 'ims' => 'bg-primary', 'dam' => 'bg-success', 'dams' => 'bg-success',
        'cms' => 'bg-info text-dark', 'glam' => 'bg-info text-dark', 'ils' => 'bg-warning text-dark',
        'preservation' => 'bg-dark', 'digitization' => 'bg-secondary', 'discovery' => 'bg-danger',
        'utility' => 'bg-secondary', 'plugin' => 'bg-secondary', 'theme' => 'bg-secondary',
        'integration' => 'bg-secondary', 'other' => 'bg-info text-dark',
    ];
    $catLabels = [
        'ams' => 'AMS', 'ims' => 'IMS', 'dam' => 'DAM', 'dams' => 'DAMS',
        'cms' => 'CMS', 'glam' => 'GLAM', 'preservation' => 'Preservation',
        'digitization' => 'Digitization', 'discovery' => 'Discovery',
        'utility' => 'Utility', 'plugin' => 'Plugin', 'theme' => 'Theme',
        'integration' => 'Integration', 'other' => 'Other',
    ];
    $rawCat = $item->category ?? '';
    $catList = [];
    if ('' !== (string) $rawCat) {
        $decoded = json_decode((string) $rawCat, true);
        $catList = is_array($decoded) ? $decoded : [(string) $rawCat];
    }

    $licenseBg = [
        'open_source' => 'bg-success', 'proprietary' => 'bg-danger',
        'freemium' => 'bg-warning text-dark', 'saas' => 'bg-primary',
    ];
    $lic = $item->license ?? '';
    $licClass = $licenseBg[strtolower(str_replace(' ', '_', $lic))] ?? 'bg-secondary';

    $pricingBg = [
        'free' => 'bg-success', 'subscription' => 'bg-primary',
        'one_time' => 'bg-info text-dark', 'per_user' => 'bg-warning text-dark',
        'custom' => 'bg-secondary',
    ];
    $pm = $item->pricing_model ?? '';
    $pmClass = $pricingBg[strtolower(str_replace(' ', '_', $pm))] ?? 'bg-secondary';

    $gitIcons = [
        'github' => 'fab fa-github', 'gitlab' => 'fab fa-gitlab', 'bitbucket' => 'fab fa-bitbucket',
    ];

    $href = \Illuminate\Support\Facades\Route::has('registry.softwareView')
        ? route('registry.softwareView', ['id' => (int) ($item->id ?? 0)])
        : url('/registry/software/' . ($item->id ?? 0));

    $gitProvider = '';
    if (!empty($item->git_url)) {
        if (stripos($item->git_url, 'github.com') !== false) $gitProvider = 'github';
        elseif (stripos($item->git_url, 'gitlab.com') !== false) $gitProvider = 'gitlab';
        elseif (stripos($item->git_url, 'bitbucket.org') !== false) $gitProvider = 'bitbucket';
    }
    $gitIcon = $gitIcons[$gitProvider] ?? 'fas fa-code-branch';

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
            <i class="fas fa-code text-muted"></i>
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
          <div>
            @foreach ($catList as $catVal)
              @php
                $catKey = strtolower((string) $catVal);
                $catClass = $catBg[$catKey] ?? 'bg-info text-dark';
                $catLabel = $catLabels[$catKey] ?? ucfirst((string) $catVal);
              @endphp
              <span class="badge {{ $catClass }}">{{ $catLabel }}</span>
            @endforeach
            @if (!empty($lic))
              <span class="badge {{ $licClass }}">{{ ucfirst(str_replace('_', ' ', $lic)) }}</span>
            @endif
          </div>
        </div>
      </div>

      @if (!empty($item->git_url))
        <div class="small mb-2">
          <a href="{{ $item->git_url }}" target="_blank" rel="noopener" class="text-decoration-none">
            <i class="{{ $gitIcon }} me-1"></i>{{ __('Source') }}
            <i class="fas fa-external-link-alt ms-1" style="font-size: 0.7em;"></i>
          </a>
        </div>
      @endif

      <div class="mb-2">
        @if (!empty($item->latest_version))
          <span class="badge bg-secondary">v{{ $item->latest_version }}</span>
        @endif
        @if (!empty($pm))
          <span class="badge {{ $pmClass }}">{{ ucfirst(str_replace('_', ' ', $pm)) }}</span>
        @endif
      </div>

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
