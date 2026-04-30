@extends('theme::layouts.1col')

@section('title', 'Start Duplicate Scan')
@section('body-class', 'admin dedupe scan')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-search me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Start Duplicate Scan') }}</h1>
      <span class="small text-muted">{{ __('Duplicate Detection') }}</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.index') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Dashboard') }}
      </a>
    </div>
  </div>

<div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">{{ __('Scan Configuration') }}</h5>
        </div>
        <div class="card-body">
          <form method="post" action="{{ route('dedupe.scan.start') }}">
            @csrf
            <div class="mb-4">
              <label class="form-label fw-bold">Scan Scope <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="scope" id="scopeAll" value="all" checked>
                <label class="form-check-label" for="scopeAll">
                  <strong>{{ __('Entire System') }}</strong>
                  <br><small class="text-muted">{{ __('Scan all records across all repositories') }}</small>
                 <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="scope" id="scopeRepo" value="repository">
                <label class="form-check-label" for="scopeRepo">
                  <strong>{{ __('Specific Repository') }}</strong>
                  <br><small class="text-muted">{{ __('Scan records within a single repository') }}</small>
                 <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
            </div>

            <div class="mb-4" id="repositorySelect" style="display: none;">
              <label for="repository_id" class="form-label">Select Repository <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <select name="repository_id" id="repository_id" class="form-select">
                <option value="">-- Select Repository --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}">{{ $repo->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              <strong>{{ __('Note:') }}</strong> This will create a scan job. To process the scan, run:
              <br><code>php artisan dedupe:scan --all</code> or <code>php artisan dedupe:scan --repository=ID</code>
            </div>

            <button type="submit" class="btn atom-btn-outline-success">
              <i class="fas fa-play me-1"></i> {{ __('Start Scan Job') }}
            </button>
            <a href="{{ route('dedupe.index') }}" class="btn atom-btn-white">Cancel</a>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Scanning</h5>
        </div>
        <div class="card-body">
          <p>The duplicate scan will:</p>
          <ul>
            <li>Compare all records against each other using configured detection rules</li>
            <li>Apply title similarity, identifier matching, and other algorithms</li>
            <li>Record detected duplicates for review</li>
          </ul>
          <p class="mb-0"><strong>{{ __('Tip:') }}</strong> For large collections, start with a single repository to test results before scanning the entire system.</p>
        </div>
      </div>

      <div class="card mt-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>CLI Commands</h5>
        </div>
        <div class="card-body">
          <p class="mb-2"><strong>{{ __('Full system scan:') }}</strong></p>
          <pre class="bg-light p-2 rounded mb-3">php artisan dedupe:scan --all</pre>

          <p class="mb-2"><strong>{{ __('Repository scan:') }}</strong></p>
          <pre class="bg-light p-2 rounded mb-3">php artisan dedupe:scan --repository=1</pre>

          <p class="mb-2"><strong>{{ __('Limited scan:') }}</strong></p>
          <pre class="bg-light p-2 rounded mb-0">php artisan dedupe:scan --limit=1000</pre>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var scopeAll = document.getElementById('scopeAll');
    var scopeRepo = document.getElementById('scopeRepo');
    var repoSelect = document.getElementById('repositorySelect');

    function toggleRepoSelect() {
        repoSelect.style.display = scopeRepo.checked ? 'block' : 'none';
    }

    scopeAll.addEventListener('change', toggleRepoSelect);
    scopeRepo.addEventListener('change', toggleRepoSelect);
});
</script>
@endpush
