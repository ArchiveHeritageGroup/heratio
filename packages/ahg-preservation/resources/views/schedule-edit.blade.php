@extends('theme::layouts.1col')
@section('title', ($schedule ?? null) ? 'Edit Schedule' : 'New Schedule')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h1><i class="fas fa-{{ ($schedule ?? null) ? 'edit' : 'plus' }} me-2"></i>{{ ($schedule ?? null) ? 'Edit Schedule' : 'New Schedule' }}</h1>
        <a href="{{ route('preservation.scheduler') }}" class="btn btn-sm atom-btn-white">
            <i class="fas fa-arrow-left me-1"></i>Back to Scheduler
        </a>
    </div>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <form method="post" action="{{ $formAction ?? '#' }}">
              @csrf
              @if(isset($schedule)) @method('PUT') @endif

              <div class="card mb-3">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <i class="fas fa-cog me-1"></i> Schedule Configuration
                </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="name" class="form-label">Schedule Name <span class="badge bg-danger ms-1">Required</span></label>
                        <input type="text" class="form-control" id="name" name="name" required
                               value="{{ old('name', $schedule->name ?? '') }}"
                               placeholder="e.g., Daily Format Identification">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="workflow_type" class="form-label">Workflow Type <span class="badge bg-danger ms-1">Required</span></label>
                        <select class="form-select" id="workflow_type" name="workflow_type" required>
                            @php
                                $workflowTypes = [
                                    'fixity' => 'Fixity Check',
                                    'identification' => 'Format Identification',
                                    'virus_scan' => 'Virus Scan',
                                    'backup' => 'Backup Verification',
                                    'conversion' => 'Format Conversion',
                                ];
                            @endphp
                            @foreach($workflowTypes as $value => $label)
                                <option value="{{ $value }}"
                                        {{ old('workflow_type', $schedule->workflow_type ?? '') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label for="description" class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                    <textarea class="form-control" id="description" name="description" rows="2"
                              placeholder="Optional description of this schedule">{{ old('description', $schedule->description ?? '') }}</textarea>
                  </div>

                  <hr>
                  <h6 class="text-muted mb-3"><i class="fas fa-clock"></i> Schedule Timing</h6>

                  <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="cron_expression" class="form-label">Cron Expression <span class="badge bg-danger ms-1">Required</span></label>
                        <input type="text" class="form-control font-monospace" id="cron_expression" name="cron_expression"
                               value="{{ old('cron_expression', $schedule->cron_expression ?? '0 2 * * *') }}"
                               placeholder="0 2 * * *">
                        <div class="form-text" id="cron_description">Daily at 02:00</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quick Presets</label>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm atom-btn-white cron-preset" data-cron="0 1 * * *">1:00 AM</button>
                            <button type="button" class="btn btn-sm atom-btn-white cron-preset" data-cron="0 2 * * *">2:00 AM</button>
                            <button type="button" class="btn btn-sm atom-btn-white cron-preset" data-cron="0 3 * * *">3:00 AM</button>
                            <button type="button" class="btn btn-sm atom-btn-white cron-preset" data-cron="0 * * * *">Hourly</button>
                            <button type="button" class="btn btn-sm atom-btn-white cron-preset" data-cron="0 4 * * 0">Sunday 4AM</button>
                            <button type="button" class="btn btn-sm atom-btn-white cron-preset" data-cron="0 6 * * 6">Saturday 6AM</button>
                        </div>
                    </div>
                  </div>

                  <hr>
                  <h6 class="text-muted mb-3"><i class="fas fa-sliders-h"></i> Execution Settings</h6>

                  <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="batch_limit" class="form-label">Batch Limit <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="number" class="form-control" id="batch_limit" name="batch_limit"
                               value="{{ old('batch_limit', $schedule->batch_limit ?? 100) }}" min="1" max="10000">
                        <div class="form-text">Max objects per run</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="timeout_minutes" class="form-label">Timeout (minutes) <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="number" class="form-control" id="timeout_minutes" name="timeout_minutes"
                               value="{{ old('timeout_minutes', $schedule->timeout_minutes ?? 60) }}" min="1" max="480">
                        <div class="form-text">Max runtime before abort</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1"
                                   {{ old('is_enabled', $schedule->is_enabled ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_enabled">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
                        </div>
                    </div>
                  </div>

                  <hr>
                  <h6 class="text-muted mb-3"><i class="fas fa-bell"></i> Notifications</h6>

                  <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="notify_on_failure" name="notify_on_failure" value="1"
                                   {{ old('notify_on_failure', $schedule->notify_on_failure ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="notify_on_failure">Notify on Failure <span class="badge bg-secondary ms-1">Optional</span></label>
                        </div>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="notify_email" class="form-label">Notification Email <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="email" class="form-control" id="notify_email" name="notify_email"
                               value="{{ old('notify_email', $schedule->notify_email ?? '') }}"
                               placeholder="admin@example.com">
                    </div>
                  </div>

                  <input type="hidden" name="schedule_type" value="cron">
                </div>
              </div>

              <div class="d-flex justify-content-between">
                <a href="{{ route('preservation.scheduler') }}" class="btn atom-btn-white">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn atom-btn-white">
                    <i class="fas fa-save me-1"></i>{{ ($schedule ?? null) ? 'Update Schedule' : 'Create Schedule' }}
                </button>
              </div>
            </form>
        </div>

        <div class="col-lg-4">
            {{-- Cron Help --}}
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-question-circle me-1"></i> Cron Format</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-2 rounded small"> ┌───────────── minute (0 - 59)
 │ ┌───────────── hour (0 - 23)
 │ │ ┌───────────── day of month (1 - 31)
 │ │ │ ┌───────────── month (1 - 12)
 │ │ │ │ ┌───────────── day of week (0 - 6)
 │ │ │ │ │              (Sunday = 0)
 │ │ │ │ │
 * * * * *</pre>
                    <p class="small mb-2"><strong>Examples:</strong></p>
                    <ul class="small mb-0">
                        <li><code>0 2 * * *</code> - Daily at 2:00 AM</li>
                        <li><code>0 */4 * * *</code> - Every 4 hours</li>
                        <li><code>0 3 * * 0</code> - Sundays at 3:00 AM</li>
                        <li><code>0 6 * * 1-5</code> - Weekdays at 6:00 AM</li>
                        <li><code>0 0 1 * *</code> - 1st of each month</li>
                    </ul>
                </div>
            </div>

            @if(($schedule ?? null) && !empty($runs ?? []))
            {{-- Recent Runs --}}
            <div class="card mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h6 class="mb-0"><i class="fas fa-history me-1"></i> Recent Runs</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($runs as $run)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <small>
                                    {{ $run->started_at }}
                                    <br>
                                    <span class="text-success">{{ $run->objects_succeeded ?? 0 }}</span> /
                                    <span class="text-danger">{{ $run->objects_failed ?? 0 }}</span> /
                                    {{ $run->objects_processed ?? 0 }}
                                </small>
                                @php
                                    $rBadge = ['completed' => 'bg-success', 'running' => 'bg-info', 'partial' => 'bg-warning', 'failed' => 'bg-danger'];
                                @endphp
                                <span class="badge {{ $rBadge[$run->status ?? ''] ?? 'bg-secondary' }}">{{ $run->status ?? '' }}</span>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            @if($schedule ?? null)
            {{-- Schedule Statistics --}}
            <div class="card">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-1"></i> Statistics</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Total Runs</dt>
                        <dd class="col-6">{{ number_format($schedule->total_runs ?? 0) }}</dd>
                        <dt class="col-6">Total Processed</dt>
                        <dd class="col-6">{{ number_format($schedule->total_processed ?? 0) }}</dd>
                        <dt class="col-6">Created</dt>
                        <dd class="col-6"><small>{{ $schedule->created_at ?? '-' }}</small></dd>
                        <dt class="col-6">Created By</dt>
                        <dd class="col-6"><small>{{ $schedule->created_by ?? 'system' }}</small></dd>
                    </dl>
                </div>
            </div>
            @endif
        </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cronInput = document.getElementById('cron_expression');
    const cronDesc = document.getElementById('cron_description');

    function updateCronDescription() {
        const cron = cronInput.value.trim();
        const parts = cron.split(/\s+/);
        if (parts.length !== 5) { cronDesc.textContent = 'Invalid format'; return; }
        const [minute, hour, day, month, weekday] = parts;
        let desc = '';
        if (minute === '0' && hour !== '*' && day === '*' && month === '*' && weekday === '*') {
            desc = 'Daily at ' + hour.padStart(2, '0') + ':00';
        } else if (minute === '0' && hour !== '*' && day === '*' && month === '*' && weekday === '0') {
            desc = 'Sundays at ' + hour.padStart(2, '0') + ':00';
        } else if (minute === '0' && hour !== '*' && day === '*' && month === '*' && weekday === '6') {
            desc = 'Saturdays at ' + hour.padStart(2, '0') + ':00';
        } else if (minute === '0' && hour.startsWith('*/')) {
            desc = 'Every ' + hour.substring(2) + ' hours';
        } else if (minute === '0' && hour === '*') {
            desc = 'Every hour at :00';
        } else {
            desc = 'Custom schedule';
        }
        cronDesc.textContent = desc;
    }

    cronInput.addEventListener('input', updateCronDescription);
    updateCronDescription();

    document.querySelectorAll('.cron-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            cronInput.value = this.dataset.cron;
            updateCronDescription();
        });
    });
});
</script>
@endsection
