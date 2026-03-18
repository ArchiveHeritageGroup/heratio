@extends('theme::layouts.1col')

@section('title', 'Cron Jobs')
@section('body-class', 'admin settings cron-jobs')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="mb-0"><i class="fas fa-clock me-2"></i>Cron Jobs</h1>
      <div>
        <form action="{{ route('settings.cron-seed') }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm btn-outline-secondary" title="Add missing default entries">
            <i class="fas fa-seedling me-1"></i>Seed Defaults
          </button>
        </form>
        <form action="{{ route('settings.cron-seed') }}" method="POST" class="d-inline ms-1">
          @csrf
          <input type="hidden" name="reset" value="1">
          <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset ALL schedules to defaults? Custom changes will be overwritten.')" title="Overwrite all to defaults">
            <i class="fas fa-undo me-1"></i>Reset All
          </button>
        </form>
      </div>
    </div>

    @if(session('success'))<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if(session('error'))<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif

    {{-- Stats Bar --}}
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-light">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-primary">{{ $stats->total }}</div>
            <small class="text-muted">Total Jobs</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-light">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-success">{{ $stats->enabled }}</div>
            <small class="text-muted">Enabled</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-light">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-secondary">{{ $stats->disabled }}</div>
            <small class="text-muted">Disabled</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card text-center border-0 bg-light">
          <div class="card-body py-2">
            <div class="fs-3 fw-bold text-danger">{{ $stats->failed_24h }}</div>
            <small class="text-muted">Failed (24h)</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Category Filter --}}
    @php $allCategories = $categories->keys()->sort(); @endphp
    <div class="mb-3">
      <div class="btn-group btn-group-sm flex-wrap" role="group">
        <a href="#" class="btn btn-outline-primary active category-filter" data-category="all">All</a>
        @foreach($allCategories as $cat)
          <a href="#" class="btn btn-outline-primary category-filter" data-category="{{ Str::slug($cat) }}">{{ $cat }} <span class="badge bg-primary bg-opacity-25">{{ $categories[$cat]->count() }}</span></a>
        @endforeach
      </div>
    </div>

    {{-- Job Categories --}}
    @foreach($categories as $category => $jobs)
      <div class="card mb-4 category-card" data-category="{{ Str::slug($category) }}">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">{{ $category }} <span class="badge bg-secondary ms-2">{{ $jobs->count() }}</span></h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th style="width:40px">On</th>
                  <th>Job</th>
                  <th>Schedule</th>
                  <th>Status</th>
                  <th>Last Run</th>
                  <th>Duration</th>
                  <th>Next Run</th>
                  <th style="width:120px">Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($jobs as $job)
                <tr class="{{ !$job->is_enabled ? 'table-secondary opacity-50' : '' }}">
                  {{-- Toggle --}}
                  <td>
                    <form action="{{ route('settings.cron-toggle', $job->id) }}" method="POST">
                      @csrf
                      <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" onchange="this.form.submit()" {{ $job->is_enabled ? 'checked' : '' }}>
                      </div>
                    </form>
                  </td>

                  {{-- Name --}}
                  <td>
                    <strong>{{ $job->name }}</strong>
                    <br><small class="text-muted" title="{{ $job->artisan_command }}"><code>{{ $job->artisan_command }}</code></small>
                    @if($job->description)
                      <br><small class="text-muted">{{ Str::limit($job->description, 80) }}</small>
                    @endif
                  </td>

                  {{-- Cron Expression --}}
                  <td>
                    <code class="text-nowrap">{{ $job->cron_expression }}</code>
                    <span class="badge bg-{{ $job->duration_hint === 'short' ? 'success' : ($job->duration_hint === 'medium' ? 'warning' : 'danger') }} ms-1">{{ ucfirst($job->duration_hint) }}</span>
                  </td>

                  {{-- Status --}}
                  <td>
                    @if($job->last_run_status === 'success')
                      <span class="badge bg-success">OK</span>
                    @elseif($job->last_run_status === 'failed')
                      <span class="badge bg-danger">Failed</span>
                    @elseif($job->last_run_status === 'running')
                      <span class="badge bg-info"><i class="fas fa-spinner fa-spin me-1"></i>Running</span>
                    @else
                      <span class="badge bg-secondary">Never</span>
                    @endif
                  </td>

                  {{-- Last Run --}}
                  <td>
                    @if($job->last_run_at)
                      <small class="text-nowrap" title="{{ $job->last_run_at }}">{{ \Carbon\Carbon::parse($job->last_run_at)->diffForHumans() }}</small>
                    @else
                      <small class="text-muted">—</small>
                    @endif
                  </td>

                  {{-- Duration --}}
                  <td>
                    @if($job->last_run_duration_ms !== null)
                      <small>{{ $job->last_run_duration_ms >= 1000 ? round($job->last_run_duration_ms / 1000, 1) . 's' : $job->last_run_duration_ms . 'ms' }}</small>
                    @else
                      <small class="text-muted">—</small>
                    @endif
                  </td>

                  {{-- Next Run --}}
                  <td>
                    @if($job->next_run_at && $job->is_enabled)
                      <small class="text-nowrap" title="{{ $job->next_run_at }}">{{ \Carbon\Carbon::parse($job->next_run_at)->diffForHumans() }}</small>
                    @else
                      <small class="text-muted">—</small>
                    @endif
                  </td>

                  {{-- Actions --}}
                  <td class="text-nowrap">
                    {{-- Run Now --}}
                    <form action="{{ route('settings.cron-run', $job->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-success" title="Run now" onclick="return confirm('Run {{ $job->name }} now?')">
                        <i class="fas fa-play"></i>
                      </button>
                    </form>

                    {{-- Edit Modal Trigger --}}
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-{{ $job->id }}" title="Edit">
                      <i class="fas fa-edit"></i>
                    </button>

                    {{-- Output Modal Trigger --}}
                    @if($job->last_run_output)
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#output-{{ $job->id }}" title="View output">
                      <i class="fas fa-terminal"></i>
                    </button>
                    @endif
                  </td>
                </tr>

                {{-- Edit Modal --}}
                <div class="modal fade" id="edit-{{ $job->id }}" tabindex="-1">
                  <div class="modal-dialog">
                    <form action="{{ route('settings.cron-update', $job->id) }}" method="POST">
                      @csrf
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title">Edit: {{ $job->name }}</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="mb-3">
                            <label class="form-label fw-bold">Command</label>
                            <input type="text" class="form-control" value="{{ $job->artisan_command }}" disabled>
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-bold">Cron Expression</label>
                            <input type="text" name="cron_expression" class="form-control font-monospace" value="{{ $job->cron_expression }}" required>
                            <small class="text-muted">e.g. <code>*/5 * * * *</code> = every 5 min, <code>0 2 * * *</code> = daily 2am</small>
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-bold">Timeout (minutes)</label>
                            <input type="number" name="timeout_minutes" class="form-control" value="{{ $job->timeout_minutes }}" min="1" max="1440" required>
                          </div>
                          <div class="mb-3">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="notify_on_failure" id="notify-{{ $job->id }}" {{ $job->notify_on_failure ? 'checked' : '' }}>
                              <label class="form-check-label" for="notify-{{ $job->id }}">Notify on failure</label>
                            </div>
                          </div>
                          <div class="mb-3">
                            <label class="form-label fw-bold">Notification Email</label>
                            <input type="email" name="notify_email" class="form-control" value="{{ $job->notify_email }}" placeholder="admin@example.com">
                          </div>
                          <div class="row text-muted small">
                            <div class="col">Total runs: {{ $job->total_runs }}</div>
                            <div class="col">Total failures: {{ $job->total_failures }}</div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                          <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>

                {{-- Output Modal --}}
                @if($job->last_run_output)
                <div class="modal fade" id="output-{{ $job->id }}" tabindex="-1">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Output: {{ $job->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <pre class="bg-dark text-light p-3 rounded mb-0" style="max-height:400px;overflow:auto;white-space:pre-wrap;">{{ $job->last_run_output }}</pre>
                      </div>
                    </div>
                  </div>
                </div>
                @endif
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endforeach

    {{-- Cron Syntax Reference (collapsible) --}}
    <div class="card mb-4">
      <div class="card-header">
        <a class="text-decoration-none" data-bs-toggle="collapse" href="#cron-reference">
          <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Cron Syntax Quick Reference</h5>
        </a>
      </div>
      <div id="cron-reference" class="collapse">
        <div class="card-body">
          <pre class="bg-dark text-light p-3 rounded mb-3">┌───────────── minute (0–59)
│ ┌───────────── hour (0–23)
│ │ ┌───────────── day of month (1–31)
│ │ │ ┌───────────── month (1–12)
│ │ │ │ ┌───────────── day of week (0–7, 0 and 7 are Sunday)
│ │ │ │ │
* * * * * command</pre>
          <div class="row">
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tbody>
                  <tr><td><code>* * * * *</code></td><td>Every minute</td></tr>
                  <tr><td><code>*/5 * * * *</code></td><td>Every 5 minutes</td></tr>
                  <tr><td><code>*/15 * * * *</code></td><td>Every 15 minutes</td></tr>
                  <tr><td><code>0 * * * *</code></td><td>Every hour</td></tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <table class="table table-sm mb-0">
                <tbody>
                  <tr><td><code>0 2 * * *</code></td><td>Daily at 2:00 AM</td></tr>
                  <tr><td><code>0 3 * * 0</code></td><td>Sunday at 3:00 AM</td></tr>
                  <tr><td><code>0 7 * * 1</code></td><td>Monday at 7:00 AM</td></tr>
                  <tr><td><code>0 8 1 * *</code></td><td>1st of month at 8:00 AM</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- System crontab info --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-terminal me-2"></i>System Crontab</h5></div>
      <div class="card-body">
        <p class="mb-2">Only one system crontab entry is needed:</p>
        <div class="bg-dark text-light p-3 rounded">
          <code class="text-warning">* * * * * cd /usr/share/nginx/heratio && php artisan schedule:run >> /dev/null 2>&1</code>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
// Category filter
document.querySelectorAll('.category-filter').forEach(function(btn) {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    document.querySelectorAll('.category-filter').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    var cat = btn.dataset.category;
    document.querySelectorAll('.category-card').forEach(function(card) {
      card.style.display = (cat === 'all' || card.dataset.category === cat) ? '' : 'none';
    });
  });
});
</script>
@endsection
