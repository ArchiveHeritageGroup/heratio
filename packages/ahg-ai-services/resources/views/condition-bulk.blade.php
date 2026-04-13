{{--
 | Heratio - Bulk Condition Scan
 |
 | @author    Johan Pieterse <johan@theahg.co.za>
 | @copyright (c) Plain Sailing (Pty) Ltd t/a The Archive and Heritage Group
 | @license   GNU Affero General Public License v3.0 or later
 --}}
@extends('theme::layouts.2col')

@section('body-class', 'ai condition bulk')

@section('sidebar')
  <div class="sidebar-content">
    <div class="card mb-3">
      <div class="card-header py-2"><h6 class="mb-0">{{ __('Bulk Scan') }}</h6></div>
      <div class="card-body py-2 small">
        <p class="text-muted">{{ __('Scan all digital objects in a repository or collection through AI assessment.') }}</p>
        <a href="{{ route('admin.ai.condition.dashboard') }}" class="btn btn-sm btn-outline-secondary w-100">
          <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Assessments') }}
        </a>
      </div>
    </div>
  </div>
@endsection

@section('title-block')
  <h1 class="h3 mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Bulk Condition Scan') }}</h1>
  <p class="text-muted small mb-3">{{ __('Scan multiple objects for condition assessment') }}</p>
@endsection

@section('content')
  <div class="card mb-3">
    <div class="card-header py-2"><h6 class="mb-0">{{ __('Configure Scan') }}</h6></div>
    <div class="card-body">
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Repository') }}</label>
        <div class="col-sm-9">
          <select class="form-select form-select-sm" id="bulkRepository">
            <option value="">{{ __('-- All repositories --') }}</option>
            @foreach(($repositories ?? []) as $r)
              <option value="{{ $r->id }}">{{ $r->name }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Max Objects') }}</label>
        <div class="col-sm-9">
          <input type="number" class="form-control form-control-sm" id="bulkLimit" value="50" min="1" max="1000">
        </div>
      </div>
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Min Confidence') }}</label>
        <div class="col-sm-9">
          <input type="number" class="form-control form-control-sm" id="bulkConfidence" value="0.25" min="0.1" max="0.9" step="0.05">
        </div>
      </div>
      <div class="alert alert-warning small py-1">
        <i class="fas fa-info-circle me-1"></i>
        {{ __('Bulk scans run as a background CLI task. Use the command below to start:') }}
      </div>
      <div class="bg-dark text-light p-2 rounded small font-monospace" id="bulkCommand">
        php artisan ahg:ai-condition-bulk-scan --limit=50 --confidence=0.25
      </div>
    </div>
  </div>

  <script>
    (function () {
      function updateCommand() {
        var repo = document.getElementById('bulkRepository').value;
        var limit = document.getElementById('bulkLimit').value;
        var conf = document.getElementById('bulkConfidence').value;
        var cmd = 'php artisan ahg:ai-condition-bulk-scan --limit=' + limit + ' --confidence=' + conf;
        if (repo) cmd += ' --repository=' + repo;
        document.getElementById('bulkCommand').textContent = cmd;
      }
      document.getElementById('bulkRepository').addEventListener('change', updateCommand);
      document.getElementById('bulkLimit').addEventListener('input', updateCommand);
      document.getElementById('bulkConfidence').addEventListener('input', updateCommand);
    })();
  </script>
@endsection
