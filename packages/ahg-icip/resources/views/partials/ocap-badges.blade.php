{{--
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL v3+

  Renders OCAP traffic-light badges for an IO. Skipped silently when:
    - OCAP overlay is disabled in icip_config
    - icip_object_summary has no entry for this IO (no ICIP signal)

  Usage:
    @include('icip::partials.ocap-badges', ['ioId' => $io->id])
--}}
@php
  $__ocapAssessment = null;
  try {
      $__ocapSvc = new \AhgIcip\Services\OcapService();
      if ($__ocapSvc->isEnabled() && \Illuminate\Support\Facades\Schema::hasTable('icip_object_summary')) {
          $__hasSignal = \Illuminate\Support\Facades\DB::table('icip_object_summary')
              ->where('information_object_id', $ioId)
              ->where('has_icip_content', 1)
              ->exists();
          if ($__hasSignal) {
              $__ocapAssessment = $__ocapSvc->assessForIO((int)$ioId);
          }
      }
  } catch (\Throwable $e) { /* package not installed; render nothing */ }

  $__ocapBadge = function (string $label, string $status): string {
      $cls = match ($status) {
          'green' => 'bg-success',
          'amber' => 'bg-warning text-dark',
          'red'   => 'bg-danger',
          default => 'bg-secondary',
      };
      return '<span class="badge ' . $cls . ' me-1" title="{{ __("OCAP: ' . $label . '") }}">'
           . '<i class="fas fa-shield-alt me-1"></i>' . $label . '</span>';
  };
@endphp

@if($__ocapAssessment)
  <div class="ocap-badges mb-2" aria-label="{{ __('OCAP compliance') }}">
    <small class="text-muted me-2"><strong>{{ __('OCAP®') }}</strong></small>
    {!! $__ocapBadge('Ownership',  $__ocapAssessment['ownership']) !!}
    {!! $__ocapBadge('Control',    $__ocapAssessment['control']) !!}
    {!! $__ocapBadge('Access',     $__ocapAssessment['access']) !!}
    {!! $__ocapBadge('Possession', $__ocapAssessment['possession']) !!}
    @if(!empty($__ocapAssessment['reasons']))
      <i class="fas fa-info-circle text-muted ms-1"
         data-bs-toggle="tooltip"
         title="{{ implode(' ', $__ocapAssessment['reasons']) }}"></i>
    @endif
  </div>
@endif
