{{-- Block: Footer Section (migrated from ahgLandingPagePlugin) --}}
@php
$showLogo = $config['show_logo'] ?? false;
$columns = (int)($config['columns'] ?? 3);
$bgColor = $config['background_color'] ?? '#212529';
$textColor = $config['text_color'] ?? '#ffffff';
$sticky = !empty($config['sticky']);

$col1Title = $config['col1_title'] ?? 'About';
$col1Content = $config['col1_content'] ?? '';
$col2Title = $config['col2_title'] ?? 'Quick Links';
$col3Title = $config['col3_title'] ?? 'Contact';
$col3Content = $config['col3_content'] ?? '';
@endphp

<footer class="landing-footer py-3{{ $sticky ? ' sticky-bottom' : '' }}" style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }};{{ $sticky ? ' position: sticky; bottom: 0; z-index: 999;' : '' }}">
  <div class="container">
    <div class="row g-3">
      @if ($columns >= 1)
        <div class="col-md-{{ 12 / $columns }}">
          @if ($showLogo)
            <div class="mb-2">
              @if (file_exists(public_path('uploads/logos/logo.png')))
                <img src="/uploads/logos/logo.png" alt="Logo" style="max-height: 30px; filter: brightness(0) invert(1);">
              @endif
            </div>
          @endif
          @if (!empty($col1Title))
            <h6 class="mb-2" style="color: {{ $textColor }};">{{ e($col1Title) }}</h6>
          @endif
          @if (!empty($col1Content))
            <div class="small opacity-75">{!! $col1Content !!}</div>
          @endif
        </div>
      @endif

      @if ($columns >= 2)
        <div class="col-md-{{ 12 / $columns }}">
          @if (!empty($col2Title))
            <h6 class="mb-2" style="color: {{ $textColor }};">{{ e($col2Title) }}</h6>
          @endif
          <ul class="list-unstyled small mb-0">
            <li class="mb-1"><a href="{{ route('informationobject.browse') }}" class="text-decoration-none opacity-75" style="color: {{ $textColor }};">Browse Collections</a></li>
            <li class="mb-1"><a href="{{ route('repository.browse') }}" class="text-decoration-none opacity-75" style="color: {{ $textColor }};">Repositories</a></li>
            <li class="mb-1"><a href="{{ route('search.advanced') }}" class="text-decoration-none opacity-75" style="color: {{ $textColor }};">Advanced Search</a></li>
            <li><a href="{{ url('/about') }}" class="text-decoration-none opacity-75" style="color: {{ $textColor }};">About Us</a></li>
          </ul>
        </div>
      @endif

      @if ($columns >= 3)
        <div class="col-md-{{ 12 / $columns }}">
          @if (!empty($col3Title))
            <h6 class="mb-2" style="color: {{ $textColor }};">{{ e($col3Title) }}</h6>
          @endif
          @if (!empty($col3Content))
            <div class="small opacity-75">{!! $col3Content !!}</div>
          @else
            <div class="small opacity-75">
              <p class="mb-1">Email: info@example.com</p>
              <p class="mb-0">Phone: +27 12 345 6789</p>
            </div>
          @endif
        </div>
      @endif
    </div>
  </div>
</footer>
