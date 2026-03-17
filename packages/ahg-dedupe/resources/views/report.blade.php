@extends('theme::layouts.1col')

@section('title', 'Duplicate Detection Report')
@section('body-class', 'admin dedupe report')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Duplicate Detection Report</h1>
      <span class="small text-muted">Statistics and breakdown</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>

  {{-- Monthly Stats --}}
  <div class="card mb-4">
    <div class="card-header"><strong>Monthly Statistics</strong></div>
    <div class="card-body p-0">
      @if($monthlyStats->isEmpty())
        <div class="p-3 text-muted">No data available.</div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Month</th>
                <th class="text-end">Total</th>
                <th class="text-end">Pending</th>
                <th class="text-end">Confirmed</th>
                <th class="text-end">Merged</th>
                <th class="text-end">Dismissed</th>
              </tr>
            </thead>
            <tbody>
              @foreach($monthlyStats as $row)
                <tr>
                  <td>{{ $row->month }}</td>
                  <td class="text-end">{{ number_format($row->total) }}</td>
                  <td class="text-end">{{ number_format($row->pending) }}</td>
                  <td class="text-end">{{ number_format($row->confirmed) }}</td>
                  <td class="text-end">{{ number_format($row->merged) }}</td>
                  <td class="text-end">{{ number_format($row->dismissed) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  {{-- By Detection Method --}}
  <div class="card mb-4">
    <div class="card-header"><strong>By Detection Method</strong></div>
    <div class="card-body p-0">
      @if($methodBreakdown->isEmpty())
        <div class="p-3 text-muted">No data available.</div>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Method</th>
                <th class="text-end">Total</th>
                <th class="text-end">Avg Score</th>
                <th class="text-end">Confirmed</th>
                <th class="text-end">Dismissed</th>
              </tr>
            </thead>
            <tbody>
              @foreach($methodBreakdown as $row)
                <tr>
                  <td><span class="badge bg-light text-dark">{{ $row->detection_method }}</span></td>
                  <td class="text-end">{{ number_format($row->total) }}</td>
                  <td class="text-end">{{ number_format($row->avg_score, 1) }}%</td>
                  <td class="text-end">{{ number_format($row->confirmed) }}</td>
                  <td class="text-end">{{ number_format($row->dismissed) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
