@php
  use AhgCore\Services\AhgSettingsService;
  $ftDisclaimer = AhgSettingsService::get('ahg_footer_disclaimer', '');
  $ftSystemName = AhgSettingsService::get('ahg_footer_system_name', '');
  $ftOrgName = AhgSettingsService::get('ahg_footer_org_name', config('app.name', 'Heratio'));
  $ftOrgUrl = AhgSettingsService::get('ahg_footer_org_url', '');
  $ftStandards = AhgSettingsService::get('ahg_footer_standards', '');
  $ftLinks = AhgSettingsService::get('ahg_footer_links', '');
  $ftUtilityLinks = AhgSettingsService::get('ahg_footer_utility_links', '');
  $ftCopyrightStart = AhgSettingsService::get('ahg_footer_copyright', date('Y'));
  $ftCustomText = $themeData['footerText'] ?? '';
  $version = '';
  $versionFile = base_path('version.json');
  if (file_exists($versionFile)) {
    $v = json_decode(file_get_contents($versionFile), true);
    $version = $v['version'] ?? '';
  }
@endphp
<footer class="ahg-site-footer py-3" role="contentinfo">
  <div class="container small">

    {{-- Disclaimer --}}
    @if($ftDisclaimer)
      <div class="text-center mb-2">
        <small class="text-white-50">{{ $ftDisclaimer }}</small>
      </div>
      <hr class="border-light my-2 opacity-25">
    @endif

    <div class="row">
      {{-- Left: System & Org --}}
      <div class="col-md-4 mb-2 mb-md-0">
        @if($ftSystemName)
          <strong>{{ $ftSystemName }}</strong><br>
        @endif
        @if($ftOrgUrl)
          <a href="{{ $ftOrgUrl }}" class="footer-link">{{ $ftOrgName }}</a>
          <span class="text-white-50">&middot;</span>
          <a href="{{ $ftOrgUrl }}" class="footer-link">{{ str_replace(['https://', 'http://'], '', $ftOrgUrl) }}</a>
        @else
          {{ $ftOrgName }}
        @endif

        @if($ftStandards)
          <div class="mt-1">
            @foreach(explode(',', $ftStandards) as $std)
              <span class="badge bg-dark border border-light border-opacity-25 me-1 mb-1">{{ trim($std) }}</span>
            @endforeach
          </div>
        @endif
      </div>

      {{-- Centre: Policy links --}}
      <div class="col-md-4 mb-2 mb-md-0">
        @if($ftLinks)
          <div class="d-flex flex-wrap justify-content-center gap-1">
            @foreach(explode("\n", $ftLinks) as $line)
              @php $parts = explode('|', trim($line)); @endphp
              @if(count($parts) === 2)
                <a href="{{ trim($parts[1]) }}" class="footer-link small">{{ trim($parts[0]) }}</a>
              @endif
            @endforeach
          </div>
        @endif
      </div>

      {{-- Right: Copyright & utility --}}
      <div class="col-md-4 text-md-end">
        <div class="small">
          &copy; {{ $ftCopyrightStart }}{!! $ftCopyrightStart != date('Y') ? '&ndash;' . date('Y') : '' !!} {{ $ftOrgName }}. All rights reserved.
        </div>

        @if($ftUtilityLinks)
          <div class="mt-1">
            @foreach(explode("\n", $ftUtilityLinks) as $line)
              @php $parts = explode('|', trim($line)); @endphp
              @if(count($parts) === 2)
                <a href="{{ trim($parts[1]) }}" class="footer-link small">{{ trim($parts[0]) }}</a>
                @if(!$loop->last) <span class="text-white-50">&middot;</span> @endif
              @endif
            @endforeach
          </div>
        @endif

        <div class="mt-1 text-white-50">
          @if($ftCustomText){{ $ftCustomText }} &middot; @endif
          Powered by <strong>Heratio</strong>{{ $version ? ' v' . $version : '' }}
        </div>
      </div>
    </div>

  </div>
</footer>
