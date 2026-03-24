{{-- Block: Divider (migrated from ahgLandingPagePlugin) --}}
@php
$style = $config['style'] ?? 'line';
$width = $config['width'] ?? '100%';
$color = $config['color'] ?? '#dee2e6';
$marginY = $config['margin_y'] ?? '3';

$borderStyle = 'solid';
if ($style === 'dashed') {
    $borderStyle = 'dashed';
} elseif ($style === 'dotted') {
    $borderStyle = 'dotted';
} elseif ($style === 'none') {
    $borderStyle = 'none';
}
@endphp

@if ($style === 'gradient')
  <div class="my-{{ $marginY }}" style="height: 3px; width: {{ $width }}; margin-left: auto; margin-right: auto; background: linear-gradient(90deg, transparent, {{ $color }}, transparent);"></div>
@elseif ($style !== 'none')
  <hr class="my-{{ $marginY }}" style="width: {{ $width }}; margin-left: auto; margin-right: auto; border: 0; height: 3px; background-color: {{ $color }}; opacity: 1;">
@endif
