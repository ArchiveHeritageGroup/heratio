@extends('theme::layouts.1col')

@section('title', 'Duplicate Detection Rules')
@section('body-class', 'admin dedupe rules')

@section('content')
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('dedupe.index') }}">Duplicate Detection</a></li>
      <li class="breadcrumb-item active">Detection Rules</li>
    </ol>
  </nav>

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Detection Rules</h1>
      <span class="small text-muted">Duplicate Detection</span>
    </div>
    <div class="ms-auto d-flex gap-2">
      <a href="{{ route('dedupe.rule.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Add Rule
      </a>
      <a href="{{ route('dedupe.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>


  <div class="card">
    <div class="card-body p-0">
      @if($rules->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-cog fa-3x mb-3"></i>
          <p>No detection rules configured.</p>
          <a href="{{ route('dedupe.rule.create') }}" class="btn btn-primary">
            Create Your First Rule
          </a>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th style="width: 80px;">Priority</th>
                <th>Name</th>
                <th>Type</th>
                <th style="width: 100px;">Threshold</th>
                <th>Repository</th>
                <th style="width: 100px;">Enabled</th>
                <th style="width: 100px;">Blocking</th>
                <th style="width: 120px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rules as $rule)
                <tr class="{{ !$rule->is_enabled ? 'table-secondary' : '' }}">
                  <td class="text-center"><span class="badge bg-secondary">{{ $rule->priority }}</span></td>
                  <td><strong>{{ $rule->name }}</strong></td>
                  <td><span class="badge bg-light text-dark">{{ ucwords(str_replace('_', ' ', $rule->rule_type)) }}</span></td>
                  <td class="text-center">{{ number_format($rule->threshold, 0) }}%</td>
                  <td>
                    @if(!empty($rule->repository_name))
                      <small>{{ $rule->repository_name }}</small>
                    @else
                      <span class="badge bg-info">Global</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($rule->is_enabled)
                      <span class="badge bg-success">Enabled</span>
                    @else
                      <span class="badge bg-secondary">Disabled</span>
                    @endif
                  </td>
                  <td class="text-center">
                    @if($rule->is_blocking)
                      <span class="badge bg-danger">Blocking</span>
                    @else
                      <span class="badge bg-light text-dark">No</span>
                    @endif
                  </td>
                  <td class="text-center">
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('dedupe.rule.edit', $rule->id) }}" class="btn btn-outline-secondary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                      <a href="{{ route('dedupe.rule.delete', $rule->id) }}" class="btn atom-btn-outline-danger"
                         title="Delete" onclick="return confirm('Delete this rule?');">
                        <i class="fas fa-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  <div class="card mt-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>About Detection Rules</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <h6>Rule Types</h6>
          <ul class="list-unstyled">
            <li><strong>Title Similarity:</strong> Compares titles using Levenshtein distance</li>
            <li><strong>Identifier Exact:</strong> Matches identical identifiers</li>
            <li><strong>Identifier Fuzzy:</strong> Matches similar identifiers (Jaro-Winkler)</li>
            <li><strong>Date + Creator:</strong> Matches records with same date range and creator</li>
            <li><strong>Checksum:</strong> Matches identical files by hash</li>
            <li><strong>Combined:</strong> Weighted combination of multiple factors</li>
          </ul>
        </div>
        <div class="col-md-6">
          <h6>Settings</h6>
          <ul class="list-unstyled">
            <li><strong>Priority:</strong> Higher priority rules run first</li>
            <li><strong>Threshold:</strong> Minimum similarity score to flag as duplicate</li>
            <li><strong>Blocking:</strong> If enabled, prevents saving when duplicate found</li>
            <li><strong>Repository:</strong> Apply rule only to specific repository, or globally</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
@endsection
