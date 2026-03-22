@extends('theme::layouts.1col')

@section('title', 'Create Detection Rule')
@section('body-class', 'admin dedupe rule-create')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-plus me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Create Detection Rule</h1>
      <span class="small text-muted">Duplicate Detection</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.rules') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i> Back to Rules
      </a>
    </div>
  </div>

  <form method="post" action="{{ route('dedupe.rule.store') }}">
    @csrf
    <div class="row">
      <div class="col-lg-8">
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0">Rule Details</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="name" class="form-label">Rule Name <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" class="form-control" id="name" name="name" required
                     placeholder="e.g., Title Similarity Check" value="{{ old('name') }}">
            </div>

            <div class="mb-3">
              <label for="rule_type" class="form-label">Rule Type <span class="badge bg-danger ms-1">Required</span></label>
              <select class="form-select" id="rule_type" name="rule_type" required>
                <option value="">-- Select Type --</option>
                @foreach($ruleTypes as $value => $label)
                  <option value="{{ $value }}" {{ old('rule_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="threshold" class="form-label">Threshold (0.0 - 1.0) <span class="badge bg-danger ms-1">Required</span></label>
                  <input type="number" class="form-control" id="threshold" name="threshold"
                         min="0" max="1" step="0.01" value="{{ old('threshold', '0.80') }}" required>
                  <div class="form-text">Minimum similarity score to flag as duplicate</div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="priority" class="form-label">Priority <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" class="form-control" id="priority" name="priority"
                         value="{{ old('priority', '100') }}" min="1" max="1000">
                  <div class="form-text">Higher priority rules run first</div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Apply to Repository <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select" id="repository_id" name="repository_id">
                <option value="">All Repositories (Global)</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}" {{ old('repository_id') == $repo->id ? 'selected' : '' }}>
                    {{ $repo->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="config_json" class="form-label">Configuration (JSON) <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control font-monospace" id="config_json" name="config_json"
                        rows="4" placeholder='{"algorithm": "levenshtein", "normalize": true}'>{{ old('config_json') }}</textarea>
              <div class="form-text">Optional rule-specific configuration in JSON format</div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" value="1" checked>
                  <label class="form-check-label" for="is_enabled">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" id="is_blocking" name="is_blocking" value="1">
                  <label class="form-check-label" for="is_blocking">Blocking <span class="badge bg-secondary ms-1">Optional</span></label>
                  <div class="form-text">Block record save if duplicate found</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-save me-1"></i> Create Rule
          </button>
          <a href="{{ route('dedupe.rules') }}" class="btn atom-btn-white">Cancel</a>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Rule Type Help</h5>
          </div>
          <div class="card-body" id="ruleHelp">
            <p class="text-muted">Select a rule type to see configuration options.</p>
          </div>
        </div>

        <div class="card mt-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-code me-2"></i>Example Configs</h5>
          </div>
          <div class="card-body">
            <p><strong>Title Similarity:</strong></p>
            <pre class="bg-light p-2 rounded small">{
  "algorithm": "levenshtein",
  "normalize": true,
  "ignore_case": true,
  "min_length": 10
}</pre>

            <p class="mt-3"><strong>Combined Analysis:</strong></p>
            <pre class="bg-light p-2 rounded small">{
  "weights": {
    "title": 0.4,
    "identifier": 0.3,
    "date": 0.15,
    "creator": 0.15
  }
}</pre>
          </div>
        </div>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ruleType = document.getElementById('rule_type');
    var helpDiv = document.getElementById('ruleHelp');

    var helpTexts = {
        title_similarity: '<strong>Title Similarity</strong><br>Compares record titles using string distance algorithms (Levenshtein, Jaro-Winkler).<br><br>Recommended threshold: 0.85',
        identifier_exact: '<strong>Identifier Exact Match</strong><br>Flags records with identical identifiers.<br><br>Recommended threshold: 1.00',
        identifier_fuzzy: '<strong>Identifier Fuzzy Match</strong><br>Finds similar identifiers using fuzzy matching.<br><br>Recommended threshold: 0.90',
        date_creator: '<strong>Date + Creator Match</strong><br>Matches records with overlapping date ranges and similar creators.<br><br>Recommended threshold: 0.90',
        checksum: '<strong>File Checksum</strong><br>Detects identical digital objects by comparing file checksums.<br><br>Recommended threshold: 1.00',
        combined: '<strong>Combined Analysis</strong><br>Uses weighted combination of multiple factors for comprehensive duplicate detection.<br><br>Recommended threshold: 0.75',
        custom: '<strong>Custom Rule</strong><br>Define custom matching logic via configuration JSON.'
    };

    ruleType.addEventListener('change', function() {
        if (helpTexts[this.value]) {
            helpDiv.innerHTML = helpTexts[this.value];
        } else {
            helpDiv.innerHTML = '<p class="text-muted">Select a rule type to see configuration options.</p>';
        }
    });
});
</script>
@endpush
