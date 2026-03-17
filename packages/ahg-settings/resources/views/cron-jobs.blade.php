@extends('theme::layouts.1col')

@section('title', 'Cron Jobs')
@section('body-class', 'admin settings cron-jobs')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-clock me-2"></i>Cron Jobs</h1>
    <p class="text-muted mb-4">Reference guide for all available scheduled tasks. Add entries to your system crontab using <code>sudo crontab -e</code>.</p>

    {{-- Cron Syntax Quick Reference --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Cron Syntax Quick Reference</h5></div>
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

    {{-- View Current Crontab --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-terminal me-2"></i>View / Edit System Crontab</h5></div>
      <div class="card-body">
        <div class="bg-dark text-light p-3 rounded">
          <code class="text-info">sudo crontab -l</code> <span class="text-muted ms-3"># View current cron jobs</span><br>
          <code class="text-info">sudo crontab -e</code> <span class="text-muted ms-3"># Edit cron jobs</span>
        </div>
      </div>
    </div>

    {{-- Job Categories --}}
    @foreach($categories as $category => $jobs)
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">{{ $category }} <span class="badge bg-secondary ms-2">{{ count($jobs) }}</span></h5>
        </div>
        <div class="card-body p-0">
          <div class="accordion accordion-flush" id="cat-{{ Str::slug($category) }}">
            @foreach($jobs as $idx => $job)
              @php $jobId = Str::slug($category) . '-' . $idx; @endphp
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#job-{{ $jobId }}">
                    <span class="fw-semibold">{{ $job['name'] }}</span>
                    <span class="badge bg-{{ $job['duration'] === 'short' ? 'success' : ($job['duration'] === 'medium' ? 'warning' : 'danger') }} ms-2">{{ ucfirst($job['duration']) }}</span>
                    <span class="text-muted small ms-3">{{ $job['schedule'] }}</span>
                  </button>
                </h2>
                <div id="job-{{ $jobId }}" class="accordion-collapse collapse">
                  <div class="accordion-body">
                    <p>{{ $job['description'] }}</p>

                    <h6>Command</h6>
                    <div class="bg-dark text-light p-2 rounded mb-3 d-flex align-items-center">
                      <code class="flex-grow-1 text-info">cd {{ $atomRoot }} && {{ $job['command'] }}</code>
                      <button class="btn btn-sm btn-outline-light ms-2 copy-btn" data-text="cd {{ $atomRoot }} && {{ $job['command'] }}" title="Copy">
                        <i class="fas fa-copy"></i>
                      </button>
                    </div>

                    <h6>Example Cron Entry</h6>
                    @php
                      $schedMap = [
                        'Continuous (run via supervisor or systemd)' => '# Use supervisor instead of cron',
                        'Every 5 minutes' => '*/5 * * * *',
                        'Every 5 minutes (if enabled)' => '*/5 * * * *',
                        'Every 15 minutes' => '*/15 * * * *',
                        'Every 30 minutes' => '*/30 * * * *',
                        'Hourly' => '0 * * * *',
                        'Daily at 1am' => '0 1 * * *',
                        'Daily at 2am' => '0 2 * * *',
                        'Daily at 3am' => '0 3 * * *',
                        'Daily at 4am' => '0 4 * * *',
                        'Daily at 5am' => '0 5 * * *',
                        'Daily at 6am' => '0 6 * * *',
                        'Daily at 8am' => '0 8 * * *',
                        'Monday at 7am' => '0 7 * * 1',
                        'Monday at 8am' => '0 8 * * 1',
                        'First of month at 8am' => '0 8 1 * *',
                        'Weekly (Sunday 1am)' => '0 1 * * 0',
                        'Weekly (Sunday 2am)' => '0 2 * * 0',
                        'Weekly (Sunday 3am)' => '0 3 * * 0',
                        'Weekly (Sunday 4am)' => '0 4 * * 0',
                        'Weekly (Sunday 6am)' => '0 6 * * 0',
                      ];
                      $cronExpr = $schedMap[$job['schedule']] ?? '0 * * * *';
                      $cronLine = "{$cronExpr} cd {$atomRoot} && {$job['command']} >> /var/log/heratio/cron.log 2>&1";
                    @endphp
                    <div class="bg-dark text-light p-2 rounded d-flex align-items-center">
                      <code class="flex-grow-1 text-warning">{{ $cronLine }}</code>
                      <button class="btn btn-sm btn-outline-light ms-2 copy-btn" data-text="{{ $cronLine }}" title="Copy">
                        <i class="fas fa-copy"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach

  </div>
</div>

<script>
document.querySelectorAll('.copy-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    navigator.clipboard.writeText(btn.dataset.text).then(function() {
      var icon = btn.querySelector('i');
      icon.className = 'fas fa-check text-success';
      setTimeout(function() { icon.className = 'fas fa-copy'; }, 1500);
    });
  });
});
</script>
@endsection
