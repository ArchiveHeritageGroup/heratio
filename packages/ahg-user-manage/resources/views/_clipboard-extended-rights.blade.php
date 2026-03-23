{{-- Clipboard Extended Rights Actions --}}
@php
  $clipboard = session('clipboard', []);
  $clipboardCount = count($clipboard);
@endphp

@if($clipboardCount > 0)
  @php
    $objectIds = implode(',', $clipboard);
  @endphp

  <div class="card mb-3">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-copyright me-1"></i>{{ __('Rights Operations') }}</h5>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-3">
        {!! __('Apply to %1% selected items', ['%1%' => '<strong>' . $clipboardCount . '</strong>']) !!}
      </p>
      <div class="d-grid gap-2">
        <a href="{{ route('extendedRights.batch') }}?object_ids={{ $objectIds }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-layer-group me-1"></i>{{ __('Batch Assign Rights') }}
        </a>
        <a href="{{ route('extendedRights.batch') }}?object_ids={{ $objectIds }}&batch_action=embargo" class="btn btn-outline-warning btn-sm">
          <i class="fas fa-lock me-1"></i>{{ __('Batch Apply Embargo') }}
        </a>
        <a href="{{ route('extendedRights.export') }}?ids={{ $objectIds }}" class="btn btn-outline-success btn-sm">
          <i class="fas fa-download me-1"></i>{{ __('Export Rights (JSON-LD)') }}
        </a>
      </div>
    </div>
  </div>
@endif
