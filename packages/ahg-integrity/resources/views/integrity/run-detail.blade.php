@extends('theme::layouts.1col')
@section('title', 'Integrity - Run Detail')
@section('body-class', 'admin integrity run-detail')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Run Detail</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')
<div class="card mb-4"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Run Details</h5></div>
<div class="card-body"><div class="row"><div class="col-md-6"><dl>
  @if($run->id ?? null)<dt>Run ID</dt><dd>{{ $run->id }}</dd>@endif
  @if($run->started_at ?? null)<dt>Started</dt><dd>{{ $run->started_at }}</dd>@endif
  @if($run->completed_at ?? null)<dt>Completed</dt><dd>{{ $run->completed_at }}</dd>@endif
  @if($run->duration ?? null)<dt>Duration</dt><dd>{{ $run->duration }}</dd>@endif
</dl></div><div class="col-md-6"><dl>
  @if($run->status ?? null)<dt>Status</dt><dd><span class="badge bg-{{ ($run->status ?? '') === 'passed' ? 'success' : 'danger' }}">{{ ucfirst($run->status ?? '') }}</span></dd>@endif
  @if(isset($run->total_checked))<dt>Total Checked</dt><dd>{{ number_format($run->total_checked) }}</dd>@endif
  @if(isset($run->total_passed))<dt>Passed</dt><dd>{{ number_format($run->total_passed) }}</dd>@endif
  @if(isset($run->total_failed))<dt>Failed</dt><dd>{{ number_format($run->total_failed) }}</dd>@endif
</dl></div></div></div></div>
@if(isset($failures) && count($failures) > 0)
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Failures</h5></div>
<div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Object</th><th>Failure</th><th>Message</th></tr></thead>
<tbody>@foreach($failures as $f)<tr><td>#{{ $f->digital_object_id ?? '' }}</td><td>{{ $f->failure_type ?? '' }}</td><td>{{ $f->message ?? '' }}</td></tr>@endforeach</tbody></table></div></div>
@endif
<div class="mt-3"><a href="{{ route('integrity.runs') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Runs</a></div>
@endsection
