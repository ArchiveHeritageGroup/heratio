{{-- Block: Hero Banner (migrated from ahgLandingPagePlugin) --}}
@php
$bgImage = $config['background_image'] ?? '';
$overlay = $config['overlay_opacity'] ?? 0.5;
$height = $config['height'] ?? '400px';
$textAlign = $config['text_align'] ?? 'center';
$titleSize = $config['title_size'] ?? '';
$subtitleSize = $config['subtitle_size'] ?? '';
$showButton = $config['show_button'] ?? true;
if (is_string($showButton)) {
    $showButton = !in_array(strtolower($showButton), ['0', 'false', 'no', '']);
}
@endphp
<div class="hero-banner position-relative d-flex align-items-center justify-content-{{ $textAlign }}" style="min-height: {{ e($height) }}; background: url('{{ e($bgImage) }}') center/cover no-repeat;">
  <div class="position-absolute top-0 start-0 w-100 h-100" style="background: rgba(0,0,0,{{ $overlay }});"></div>
  <div class="container position-relative text-white text-{{ $textAlign }}">
    @if (!empty($config['title']))
      <h1 class="fw-bold mb-3" style="{{ $titleSize ? 'font-size: ' . e($titleSize) . ';' : '' }}">{{ e($config['title']) }}</h1>
    @endif
    @if (!empty($config['subtitle']))
      <p class="mb-4" style="{{ $subtitleSize ? 'font-size: ' . e($subtitleSize) . ';' : '' }}">{{ e($config['subtitle']) }}</p>
    @endif
    @if ($showButton && !empty($config['cta_text']) && !empty($config['cta_url']))
      <a href="{{ e($config['cta_url']) }}" class="btn btn-primary btn-lg">{{ e($config['cta_text']) }}</a>
    @endif
  </div>
</div>
