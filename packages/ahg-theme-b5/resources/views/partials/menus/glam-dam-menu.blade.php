@php
  $plugins = $themeData['enabledPluginMap'] ?? [];
  $hasDisplay = isset($plugins['ahgDisplayPlugin']);
  $hasDam = isset($plugins['ahgDAMPlugin']);
  $hasCondition = isset($plugins['ahgConditionPlugin']);
  $hasProvenance = isset($plugins['ahgProvenancePlugin']);
  $hasReports = isset($plugins['ahgReportsPlugin']);
@endphp

@if($hasDisplay || $hasDam || $hasReports)
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="glam-dam-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-landmark px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="{{ __('GLAM / DAM') }}" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">{{ __('GLAM / DAM') }}</span>
    <span class="visually-hidden">{{ __('GLAM / DAM') }}</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="glam-dam-menu">
    <li><h6 class="dropdown-header">{{ __('GLAM / DAM') }}</h6></li>
    @if($hasDisplay)
      <li><a class="dropdown-item" href="{{ url('/display/browse') }}"><i class="fas fa-th me-2"></i>{{ __('Browse by Sector') }}</a></li>
    @endif
    @if($hasDam)
      <li><a class="dropdown-item" href="{{ url('/dam/browse') }}"><i class="fas fa-images me-2"></i>{{ __('Digital Assets') }}</a></li>
    @endif
    @if($hasCondition)
      <li><a class="dropdown-item" href="{{ url('/condition/browse') }}"><i class="fas fa-clipboard-check me-2"></i>{{ __('Condition Assessments') }}</a></li>
    @endif
    @if($hasProvenance)
      <li><a class="dropdown-item" href="{{ url('/provenance/browse') }}"><i class="fas fa-route me-2"></i>{{ __('Provenance') }}</a></li>
    @endif
    @if($hasReports)
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="{{ url('/reports/dashboard') }}"><i class="fas fa-chart-bar me-2"></i>{{ __('Reports') }}</a></li>
    @endif
  </ul>
</li>
@endif
