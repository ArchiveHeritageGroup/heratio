@extends('theme::layouts.1col')
@section('title', 'Facility Report')
@section('body-class', 'gallery facility-report')
@section('title-block')<h1 class="mb-0"><i class="fas fa-building me-2"></i>Facility Report</h1>@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Facility Report Details</h5></div>
  <div class="card-body">
    @if(isset($report))
    <div class="row"><div class="col-md-6"><dl>
      @if($report->institution ?? null)<dt>Institution</dt><dd>{{ $report->institution }}</dd>@endif
      @if($report->report_type ?? null)<dt>Type</dt><dd>{{ ucfirst($report->report_type) }}</dd>@endif
      @if($report->report_date ?? null)<dt>Date</dt><dd>{{ $report->report_date }}</dd>@endif
    </dl></div><div class="col-md-6"><dl>
      <dt>Fire Detection</dt><dd>{{ ($report->fire_detection ?? false) ? 'Yes' : 'No' }}</dd>
      <dt>Climate Control</dt><dd>{{ ($report->climate_control ?? false) ? 'Yes' : 'No' }}</dd>
      <dt>24hr Security</dt><dd>{{ ($report->security_24hr ?? false) ? 'Yes' : 'No' }}</dd>
      <dt>Trained Handlers</dt><dd>{{ ($report->trained_handlers ?? false) ? 'Yes' : 'No' }}</dd>
    </dl></div></div>
    @if($report->notes ?? null)<h6>Notes</h6><p>{{ $report->notes }}</p>@endif
    @else<div class="alert alert-info">No facility report data available.</div>@endif
  </div>
</div>
@endsection
