@php
  $footerText = $themeData['footerText'] ?? '';
  $showBranding = $themeData['showBranding'] ?? true;
@endphp

@if($showBranding && !empty($footerText))
<footer class="ahg-site-footer text-center py-3 mt-auto" style="background-color: var(--ahg-primary, #005837); color: #fff;">
  <small>{{ $footerText }}</small>
</footer>
@endif
