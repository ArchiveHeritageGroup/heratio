@extends('theme::layouts.1col')

@section('title', 'DOI Reports')
@section('body-class', 'admin doi report')

@section('content')
  @if(!($tablesExist ?? false))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The DOI management tables have not been created yet. Please run the database migration to set up DOI management.
    </div>
  @else
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column">
        <h1 class="mb-0">DOI Reports</h1>
        <span class="small text-muted">Minting Statistics</span>
      </div>
      <div class="ms-auto">
        <a href="{{ route('doi.index') }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
        </a>
      </div>
    </div>

    {{-- Monthly Stats --}}
    <h3 class="mb-3">Monthly Minting Statistics</h3>
    @if(count($monthlyStats))
      <div class="table-responsive mb-4">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th>Month</th>
              <th>Minted</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            @foreach($monthlyStats as $row)
              <tr>
                <td>{{ $row['month'] }}</td>
                <td>{{ number_format($row['minted_count']) }}</td>
                <td>{{ number_format($row['updated_count']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="alert alert-info mb-4">No monthly statistics available yet.</div>
    @endif

    {{-- By Repository --}}
    <h3 class="mb-3">DOIs by Repository</h3>
    @if(count($byRepository))
      <div class="table-responsive mb-4">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th>Repository</th>
              <th>DOI Count</th>
            </tr>
          </thead>
          <tbody>
            @foreach($byRepository as $row)
              <tr>
                <td>{{ $row['repository_name'] }}</td>
                <td>{{ number_format($row['doi_count']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="alert alert-info mb-4">No repository breakdown available yet.</div>
    @endif
  @endif
@endsection
