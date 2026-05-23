{{-- heratio#143 Phase 1 — workflow diagram view --}}
@extends('theme::layouts.1col')

@section('title', __('Diagram: :name', ['name' => $workflow->name]))
@section('body-class', 'workflow diagram')

@section('content')
  @php $cspNonce = function_exists('csp_nonce') ? csp_nonce() : ''; @endphp
  <style nonce="{{ $cspNonce }}">
    .workflow-diagram-stage { overflow-x: auto; background: #fafbfc; }
    .workflow-diagram { width: 100%; height: auto; color: #6c757d; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .wfdiag-edge { stroke: #adb5bd; stroke-width: 2; fill: none; }
    .wfdiag-node { fill: #ffffff; stroke: #0d6efd; stroke-width: 2; }
    .wfdiag-node.wfdiag-inactive { stroke: #adb5bd; fill: #f1f3f5; stroke-dasharray: 4 4; }
    .wfdiag-node.wfdiag-optional { stroke: #6f42c1; }
    .wfdiag-node.wfdiag-status-completed { fill: #d1e7dd; stroke: #198754; }
    .wfdiag-node.wfdiag-status-current   { fill: #fff3cd; stroke: #ffc107; stroke-width: 3; }
    .wfdiag-node.wfdiag-status-pending   { fill: #e9ecef; stroke: #adb5bd; }
    .wfdiag-node.wfdiag-status-rejected  { fill: #f8d7da; stroke: #dc3545; }
    .wfdiag-badge { fill: #0d6efd; stroke: #ffffff; stroke-width: 2; }
    .wfdiag-badge-text { fill: #ffffff; font-size: 11px; font-weight: 600; }
    .wfdiag-node-name { fill: #212529; font-size: 13px; font-weight: 600; }
    .wfdiag-node-type { fill: #6c757d; font-size: 11px; }
    .workflow-diagram-legend .legend-swatch { display: inline-block; width: 22px; height: 14px; border-radius: 4px; border: 2px solid #0d6efd; background: #fff; }
    .workflow-diagram-legend .swatch-optional { border-color: #6f42c1; transform: rotate(45deg); border-radius: 0; }
    .workflow-diagram-legend .swatch-inactive { border-style: dashed; border-color: #adb5bd; background: #f1f3f5; }
    @media print { .workflow-diagram-stage { background: #fff; } .btn { display: none; } }
  </style>

  <div class="d-flex flex-wrap align-items-baseline mb-3 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-project-diagram me-2"></i>{{ __('Diagram:') }} {{ $workflow->name }}
    </h1>
    <a href="{{ route('workflow.admin.edit', $workflow->id) }}" class="btn btn-outline-secondary">
      <i class="fas fa-edit me-1"></i>{{ __('Edit workflow') }}
    </a>
    <a href="{{ route('workflow.admin') }}" class="btn btn-outline-secondary">
      <i class="fas fa-list me-1"></i>{{ __('All workflows') }}
    </a>
  </div>

  @if(!empty($workflow->description))
    <p class="text-muted">{{ $workflow->description }}</p>
  @endif

  <div class="row">
    <div class="col-lg-9">
      <div class="card">
        <div class="card-body p-3 workflow-diagram-stage">
          {!! $svg !!}
        </div>
      </div>
    </div>

    <div class="col-lg-3 mt-3 mt-lg-0">
      <div class="card">
        <div class="card-header"><strong>{{ __('Steps') }}</strong></div>
        <ol class="list-group list-group-flush list-group-numbered mb-0 small">
          @forelse($fallback as $line)
            <li class="list-group-item">{{ \Illuminate\Support\Str::after($line, '. ') }}</li>
          @empty
            <li class="list-group-item text-muted">{{ __('No steps yet.') }}</li>
          @endforelse
        </ol>
      </div>

      <div class="card mt-3">
        <div class="card-header"><strong>{{ __('Legend') }}</strong></div>
        <ul class="list-group list-group-flush small mb-0 workflow-diagram-legend">
          <li class="list-group-item d-flex align-items-center gap-2"><span class="legend-swatch swatch-default"></span> {{ __('Standard step') }}</li>
          <li class="list-group-item d-flex align-items-center gap-2"><span class="legend-swatch swatch-optional"></span> {{ __('Optional step (diamond)') }}</li>
          <li class="list-group-item d-flex align-items-center gap-2"><span class="legend-swatch swatch-inactive"></span> {{ __('Inactive step') }}</li>
        </ul>
      </div>
    </div>
  </div>
@endsection
