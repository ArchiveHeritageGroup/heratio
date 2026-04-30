{{-- Block: Repository Spotlight (migrated from ahgLandingPagePlugin) --}}
@php
$repo = $data ?? null;
$showLogo = $config['show_logo'] ?? true;
$showDescription = $config['show_description'] ?? true;
$showContact = $config['show_contact'] ?? false;
$showHoldingsCount = $config['show_holdings_count'] ?? true;
@endphp

@if (empty($repo))
  <p class="text-muted">No repository selected.</p>
@else
  <div class="repository-spotlight">
    <div class="row align-items-center">
      @if ($showLogo)
        <div class="col-md-3 text-center mb-3 mb-md-0">
          <div class="bg-light rounded p-3">
            <i class="bi bi-building display-3 text-primary"></i>
          </div>
        </div>
      @endif

      <div class="{{ $showLogo ? 'col-md-9' : 'col-12' }}">
        <h3 class="mb-2">
          <a href="{{ route('repository.show', ['slug' => $repo['slug']]) }}"
             class="text-decoration-none">
            {{ e($repo['name'] ?? '') }}
          </a>
        </h3>

        @if ($showContact && (!empty($repo['city']) || !empty($repo['region'])))
          <p class="text-muted mb-2">
            <i class="bi bi-geo-alt"></i>
            {{ e(implode(', ', array_filter([$repo['city'] ?? '', $repo['region'] ?? '']))) }}
          </p>
        @endif

        @if ($showDescription && !empty($repo['description']))
          <p class="mb-3">{{ \Illuminate\Support\Str::limit(strip_tags($repo['description']), 200) }}</p>
        @endif

        <div class="d-flex flex-wrap gap-3">
          @if ($showHoldingsCount && isset($repo['holdings_count']))
            <span class="badge bg-primary fs-6">
              <i class="bi bi-archive"></i> {{ number_format($repo['holdings_count']) }} Holdings
            </span>
          @endif

          <a href="{{ route('repository.show', ['slug' => $repo['slug']]) }}"
             class="btn btn-outline-primary btn-sm">
            View Repository <i class="bi bi-arrow-right"></i>
          </a>
        </div>

        @if (!empty($repo['sample_holdings']))
          <div class="mt-3">
            <h6 class="small text-muted">{{ __('Sample Holdings:') }}</h6>
            <ul class="list-unstyled small">
              @foreach ($repo['sample_holdings'] as $holding)
                <li>
                  <a href="{{ route('informationobject.show', ['slug' => $holding->slug]) }}"
                     class="text-decoration-none">
                    {{ e($holding->title ?? $holding->slug) }}
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        @endif
      </div>
    </div>
  </div>
@endif
