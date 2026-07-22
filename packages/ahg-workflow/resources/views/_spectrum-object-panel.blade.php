{{-- Spectrum Phase C3 — per-object compliance panel (additive partial).

  Usage from a Blade view that already has an information object loaded:

    @include('ahg-workflow::_spectrum-object-panel', ['informationObjectId' => $io->id])

  Renders nothing if the Spectrum compliance tables aren't present yet (graceful no-op).
--}}
@php
  $svc = null;
  $summary = [];
  try {
    if (\Illuminate\Support\Facades\Schema::hasTable('ahg_workflow') &&
        \Illuminate\Support\Facades\Schema::hasTable('ahg_spectrum_object_compliance')) {
      $svc = new \AhgWorkflow\Services\SpectrumComplianceService();
      $summary = $svc->objectSummary((int) ($informationObjectId ?? 0));
    }
  } catch (\Throwable $e) {
    $summary = [];
  }
@endphp

@if(!empty($summary))
  @php $cspNonce = function_exists('csp_nonce') ? csp_nonce() : ''; @endphp
  <style nonce="{{ $cspNonce }}">
    .spectrum-obj-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 6px; }
    .spectrum-obj-cell { padding: 6px 10px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; }
    .spectrum-obj-cell.s-not_started { background: #f8f9fa; color: #6c757d; }
    .spectrum-obj-cell.s-in_progress { background: #cfe2ff; color: #084298; }
    .spectrum-obj-cell.s-completed   { background: #d1e7dd; color: #0f5132; }
    .spectrum-obj-cell.s-overdue     { background: #f8d7da; color: #842029; font-weight: 600; }
    .spectrum-obj-cell.s-rejected    { background: #fff3cd; color: #664d03; }
    .spectrum-obj-cell .icon { opacity: 0.7; }
  </style>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-university me-2"></i><strong>{{ __('Museum procedure compliance') }}</strong></span>
      <a href="{{ route('workflow.spectrum.dashboard') }}" class="text-decoration-none small">{{ __('Collection dashboard') }} <i class="fas fa-external-link-alt"></i></a>
    </div>
    <div class="card-body">
      <div class="spectrum-obj-grid">
        @foreach($summary as $code => $entry)
          <div class="spectrum-obj-cell s-{{ $entry['status'] }}" title="{{ $entry['label'] }} — {{ \AhgWorkflow\Services\SpectrumComplianceService::STATUSES[$entry['status']] ?? $entry['status'] }}">
            <span>{{ $entry['label'] }}</span>
            <span class="icon">
              @if($entry['status'] === 'completed')
                <i class="fas fa-check-circle"></i>
              @elseif($entry['status'] === 'in_progress')
                <i class="fas fa-spinner"></i>
              @elseif($entry['status'] === 'overdue')
                <i class="fas fa-exclamation-triangle"></i>
              @elseif($entry['status'] === 'rejected')
                <i class="fas fa-times-circle"></i>
              @else
                <i class="fas fa-circle text-muted" style="opacity:0.3"></i>
              @endif
            </span>
          </div>
        @endforeach
      </div>
    </div>
  </div>
@endif
