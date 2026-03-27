@extends('theme::layouts.1col')

@section('title', 'Accession dashboard')
@section('body-class', 'browse accession dashboard')

@section('title-block')
  <h1>Accession dashboard</h1>
@endsection

@section('before-content')
  <div class="d-flex flex-wrap gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2 ms-auto">
      <a href="{{ route('accession.browse') }}" class="btn btn-sm atom-btn-white">Browse {{ mb_strtolower(config('app.ui_label_accession', 'Accession')) }}s</a>
      <a href="{{ route('accession.create') }}" class="btn btn-sm atom-btn-white">Add new</a>
    </div>
  </div>
@endsection

@section('content')
  <div class="row mb-4">
    <div class="col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body text-center">
          <h2 class="display-4">{{ number_format($total) }}</h2>
          <p class="text-muted mb-0">Total accessions</p>
        </div>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body text-center">
          <h2 class="display-4">{{ number_format($recentCount) }}</h2>
          <p class="text-muted mb-0">Added in last 30 days</p>
        </div>
      </div>
    </div>
    <div class="col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body text-center">
          <a href="{{ route('accession.intake-queue') }}" class="btn atom-btn-white">
            View intake queue
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">By processing status</h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-bordered mb-0">
            <thead>
              <tr><th>Status</th><th class="text-end">Count</th></tr>
            </thead>
            <tbody>
              @foreach($byStatus as $row)
                <tr>
                  <td>{{ e($row->status_name) }}</td>
                  <td class="text-end">{{ number_format($row->cnt) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0">By processing priority</h5>
        </div>
        <div class="card-body p-0">
          <table class="table table-bordered mb-0">
            <thead>
              <tr><th>Priority</th><th class="text-end">Count</th></tr>
            </thead>
            <tbody>
              @foreach($byPriority as $row)
                <tr>
                  <td>{{ e($row->priority_name) }}</td>
                  <td class="text-end">{{ number_format($row->cnt) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
