@extends('ahg-theme-b5::layout')

@section('title', 'Export Rights Data')

@section('content')
<div class="container-fluid mt-3">
  @include('ahg-extended-rights::admin._sidebar')

  <h1><i class="fas fa-file-export"></i> Export Rights Data</h1>

  <div class="row">
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">CSV Export</h5></div>
        <div class="card-body">
          <form method="GET" action="{{ route('ext-rights-admin.export-csv') }}">
            <div class="mb-3">
              <label class="form-label">Export Type</label>
              <select name="type" class="form-select" required>
                <option value="all">All Rights Records</option>
                <option value="rights_statements">Rights Statements</option>
                <option value="cc_licenses">CC Licenses</option>
                <option value="tk_labels">TK Labels</option>
                <option value="embargoes">Active Embargoes</option>
                <option value="orphan_works">Orphan Works</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Repository (optional)</label>
              <select name="repository_id" class="form-select">
                <option value="">All</option>
                @foreach($repositories ?? [] as $repo)
                  <option value="{{ $repo->id }}">{{ e($repo->name ?? '') }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Download CSV</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">JSON-LD Export</h5></div>
        <div class="card-body">
          <form method="GET" action="{{ route('ext-rights-admin.export-jsonld') }}">
            <div class="mb-3">
              <label class="form-label">Export Scope</label>
              <select name="scope" class="form-select">
                <option value="all">All Rights</option>
                <option value="rights_statements">Rights Statements Only</option>
                <option value="cc_licenses">CC Licenses Only</option>
              </select>
            </div>
            <button type="submit" class="btn btn-outline-primary"><i class="fas fa-code"></i> Download JSON-LD</button>
          </form>
        </div>
      </div>

      {{-- Statistics --}}
      <div class="card">
        <div class="card-header"><h5 class="mb-0">Statistics</h5></div>
        <div class="card-body">
          <table class="table table-sm">
            <tr><td>Total Rights Records</td><td><strong>{{ $stats['total_rights'] ?? 0 }}</strong></td></tr>
            <tr><td>Rights Statements</td><td>{{ $stats['rights_statements'] ?? 0 }}</td></tr>
            <tr><td>CC Licenses</td><td>{{ $stats['cc_licenses'] ?? 0 }}</td></tr>
            <tr><td>TK Labels</td><td>{{ $stats['tk_labels'] ?? 0 }}</td></tr>
            <tr><td>Active Embargoes</td><td>{{ $stats['active_embargoes'] ?? 0 }}</td></tr>
            <tr><td>Orphan Works</td><td>{{ $stats['orphan_works'] ?? 0 }}</td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
