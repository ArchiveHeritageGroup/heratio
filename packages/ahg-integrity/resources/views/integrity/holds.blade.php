@extends('theme::layouts.1col')
@section('title', 'Integrity - Holds')
@section('body-class', 'admin integrity holds')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Holds</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Legal Holds</h5></div>
  <div class="card-body p-0">
    @if(isset($holds) && count($holds) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Name</th><th>Object</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
    <tbody>@foreach($holds as $h)<tr><td>{{ $h->name ?? '' }}</td><td>#{{ $h->object_id ?? '' }}</td><td>{{ $h->start_date ?? '-' }}</td><td>{{ $h->end_date ?? '-' }}</td><td><span class="badge bg-{{ ($h->status ?? '') === 'active' ? 'danger' : 'secondary' }}">{{ ucfirst($h->status ?? '') }}</span></td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No legal holds found.</div>@endif
  </div>
</div>
<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
