{{-- Spectrum Phase C1 — collection-wide compliance dashboard --}}
@extends('theme::layouts.1col')

@section('title', __('Spectrum compliance dashboard'))
@section('body-class', 'spectrum compliance-dashboard')

@section('content')
  @php $cspNonce = function_exists('csp_nonce') ? csp_nonce() : ''; @endphp
  <style nonce="{{ $cspNonce }}">
    .spectrum-heatmap-row { transition: background 0.1s; }
    .spectrum-heatmap-row:hover { background: #f8f9fa; }
    .status-cell { text-align: center; min-width: 80px; font-variant-numeric: tabular-nums; }
    .status-cell .badge { width: 100%; }
    .swatch-not_started { background: #e9ecef; color: #495057; }
    .swatch-in_progress { background: #cfe2ff; color: #084298; }
    .swatch-completed   { background: #d1e7dd; color: #0f5132; }
    .swatch-overdue     { background: #f8d7da; color: #842029; font-weight: 600; }
    .swatch-rejected    { background: #fff3cd; color: #664d03; }
    .progress { height: 8px; }
  </style>

  <div class="d-flex flex-wrap align-items-baseline mb-3 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-university me-2"></i>{{ __('Spectrum compliance dashboard') }}
    </h1>
    <a href="{{ route('workflow.spectrum.export', ['overdue_days' => $overdueDays]) }}" class="btn btn-outline-success">
      <i class="fas fa-file-csv me-1"></i>{{ __('Export CSV') }}
    </a>
    <a href="{{ route('workflow.spectrum.chain') }}" class="btn btn-outline-primary">
      <i class="fas fa-link me-1"></i>{{ __('Chain rules') }}
    </a>
    <a href="{{ route('workflow.admin') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Workflows') }}
    </a>
  </div>

  <form method="GET" action="{{ route('workflow.spectrum.dashboard') }}" class="d-flex gap-2 align-items-end mb-3">
    <div style="max-width: 14rem;">
      <label for="overdue_days" class="form-label small mb-1">{{ __('Overdue threshold (days)') }}</label>
      <input type="number" name="overdue_days" id="overdue_days" class="form-control form-control-sm" min="1" max="3650" value="{{ $overdueDays }}">
    </div>
    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Apply') }}</button>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Museum procedure') }}</th>
              <th class="status-cell">{{ __('Not started') }}</th>
              <th class="status-cell">{{ __('In progress') }}</th>
              <th class="status-cell">{{ __('Completed') }}</th>
              <th class="status-cell">{{ __('Overdue') }}</th>
              <th class="status-cell">{{ __('Rejected') }}</th>
              <th style="min-width: 150px;">{{ __('Completion') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($heatmap as $code => $row)
              <tr class="spectrum-heatmap-row">
                <td>
                  <strong>{{ $row['label'] }}</strong>
                  <br><small class="text-muted">{{ $code }}</small>
                </td>
                <td class="status-cell"><span class="badge swatch-not_started">{{ $row['totals']['not_started'] }}</span></td>
                <td class="status-cell"><span class="badge swatch-in_progress">{{ $row['totals']['in_progress'] }}</span></td>
                <td class="status-cell"><span class="badge swatch-completed">{{ $row['totals']['completed'] }}</span></td>
                <td class="status-cell"><span class="badge swatch-overdue">{{ $row['totals']['overdue'] }}</span></td>
                <td class="status-cell"><span class="badge swatch-rejected">{{ $row['totals']['rejected'] }}</span></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1">
                      <div class="progress-bar bg-success" role="progressbar" style="width: {{ $row['percent_completed'] }}%" aria-valuenow="{{ $row['percent_completed'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted" style="min-width: 3rem; text-align: right;">{{ $row['percent_completed'] }}%</small>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="7" class="small text-muted">
                {{ __('Status derived from :n information objects and their workflow task history. "Not started" = no task on that procedure. "Overdue" = pending task older than :d days.', ['n' => $heatmap[array_key_first($heatmap)]['total_objects'] ?? 0, 'd' => $overdueDays]) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
@endsection
