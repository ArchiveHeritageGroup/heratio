{{-- Block: Copyright Bar (migrated from ahgLandingPagePlugin) --}}
@php
$copyright = $config['copyright_text'] ?? '© ' . date('Y') . ' All Rights Reserved';
$poweredBy = $config['powered_by'] ?? 'Heratio';
$poweredByUrl = $config['powered_by_url'] ?? 'https://github.com/ArchiveHeritageGroup/heratio';
$enhancedBy = $config['enhanced_by'] ?? '';
$enhancedByUrl = $config['enhanced_by_url'] ?? '';
$bgColor = $config['background_color'] ?? '#1a1a1a';
$textColor = $config['text_color'] ?? '#6c757d';
$sticky = !empty($config['sticky']);
$showVersion = !isset($config['show_version']) || $config['show_version'];

$appVersion = '';
if ($showVersion) {
    $versionFile = base_path('version.json');
    if (file_exists($versionFile)) {
        $versionData = json_decode(file_get_contents($versionFile), true);
        $appVersion = ' ' . ($versionData['version'] ?? '');
    }
}
@endphp
<div class="copyright-bar py-2{{ $sticky ? ' sticky-bottom' : '' }}" style="background-color: {{ e($bgColor) }}; color: {{ e($textColor) }};{{ $sticky ? ' position: sticky; bottom: 0; z-index: 1000;' : '' }}">
  <div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center small">
      <span>{{ e($copyright) }}</span>
      <span>
        @if (!empty($poweredBy))
          Powered by <a href="{{ e($poweredByUrl) }}" class="text-decoration-none" style="color: {{ e($textColor) }};" target="_blank">{{ e($poweredBy . $appVersion) }}</a>
        @endif
        @if (!empty($poweredBy) && !empty($enhancedBy))
          <span class="mx-1">|</span>
        @endif
        @if (!empty($enhancedBy))
          Enhanced by <a href="{{ e($enhancedByUrl) }}" class="text-decoration-none" style="color: {{ e($textColor) }};" target="_blank">{{ e($enhancedBy) }}</a>
        @endif
      </span>
    </div>
  </div>
</div>
