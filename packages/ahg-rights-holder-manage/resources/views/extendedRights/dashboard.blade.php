@extends('theme::layouts.1col')

@section('title', 'Extended Rights Dashboard')
@section('body-class', 'extended-rights dashboard')

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-copyright me-2"></i>Extended Rights Dashboard</h1>
@endsection

@section('content')
  {{-- Statistics Cards --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-white bg-primary">
        <div class="card-body">
          <h5 class="card-title">Objects with Rights</h5>
          <h2>{{ number_format($stats->with_rights_statement ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-warning">
        <div class="card-body">
          <h5 class="card-title">Active Embargoes</h5>
          <h2>{{ number_format($stats->active_embargoes ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <h5 class="card-title">Expiring Soon</h5>
          <h2>{{ number_format($stats->expiring_soon ?? 0) }}</h2>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white bg-info">
        <div class="card-body">
          <h5 class="card-title">With TK Labels</h5>
          <h2>{{ number_format($stats->with_tk_labels ?? 0) }}</h2>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Rights Statements --}}
    <div class="col-md-6 mb-4">
      <div class="card h-100">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">By Rights Statement</h5>
        </div>
        <div class="card-body">
          @if(!empty($stats->by_rights_statement ?? []))
          <table class="table table-sm">
            <thead><tr><th>Statement</th><th class="text-end">Count</th></tr></thead>
            <tbody>
              @foreach($stats->by_rights_statement ?? [] as $row)
              <tr>
                <td><span class="badge bg-secondary me-1">{{ $row->code ?? '' }}</span>{{ $row->name ?? '' }}</td>
                <td class="text-end">{{ number_format($row->count ?? 0) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @else
          <p class="text-muted">No rights statements assigned yet.</p>
          @endif
        </div>
      </div>
    </div>

    {{-- CC Licenses --}}
    <div class="col-md-6 mb-4">
      <div class="card h-100">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">By CC License</h5>
        </div>
        <div class="card-body">
          @if(!empty($stats->by_cc_license ?? []))
          <table class="table table-sm">
            <thead><tr><th>License</th><th class="text-end">Count</th></tr></thead>
            <tbody>
              @foreach($stats->by_cc_license ?? [] as $row)
              <tr>
                <td><span class="badge bg-success me-1">{{ $row->code ?? '' }}</span>{{ $row->name ?? '' }}</td>
                <td class="text-end">{{ number_format($row->count ?? 0) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
          @else
          <p class="text-muted">No CC licenses assigned yet.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Actions --}}
  <div class="card">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">Quick Actions</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-3">
          <a href="{{ route('extended-rights.batch') }}" class="btn atom-btn-white w-100 mb-2"><i class="fas fa-layer-group me-1"></i>Batch Assign Rights</a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('extended-rights.embargoes') }}" class="btn atom-btn-white w-100 mb-2"><i class="fas fa-lock me-1"></i>Manage Embargoes</a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('extended-rights.export') }}" class="btn atom-btn-white w-100 mb-2"><i class="fas fa-download me-1"></i>Export Rights</a>
        </div>
        <div class="col-md-3">
          <a href="{{ route('settings.index') }}" class="btn atom-btn-white w-100 mb-2"><i class="fas fa-arrow-left me-1"></i>Back to Settings</a>
        </div>
      </div>
    </div>
  </div>
@endsection
