@extends('theme::layouts.1col')

@section('title', 'Authority Deduplication')
@section('body-class', 'authority dedup')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item active">Deduplication</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-clone me-2"></i>Authority Deduplication</h1>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <h3>{{ number_format($stats['total_actors'] ?? 0) }}</h3>
        <small class="text-muted">Total Actors</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <h3>{{ $stats['threshold'] ?? 0.80 }}</h3>
        <small class="text-muted">Threshold</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <h3>{{ number_format($stats['pending'] ?? 0) }}</h3>
        <small class="text-muted">Pending</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <h3>{{ number_format($stats['completed'] ?? 0) }}</h3>
        <small class="text-muted">Completed Merges</small>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-search me-1"></i>Run Dedup Scan
  </div>
  <div class="card-body">
    <form method="post" action="{{ route('actor.dedup.scan') }}">
      @csrf
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Max actors to compare</label>
          <input type="number" name="limit" class="form-control" value="500" min="10" max="5000">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-search me-1"></i>Start Scan
          </button>
        </div>
      </div>
      <div class="form-text">Scans actor names using Jaro-Winkler similarity. May take time for large datasets.</div>
    </form>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-terminal me-1"></i>CLI Scan
  </div>
  <div class="card-body">
    <p class="text-muted">For large datasets, run the dedup scan via CLI:</p>
    <pre class="bg-dark text-light p-3 rounded"><code>php artisan authority:dedup-scan --limit=5000</code></pre>
  </div>
</div>

@endsection
