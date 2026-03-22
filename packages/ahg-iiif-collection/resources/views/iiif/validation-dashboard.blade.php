@extends('theme::layouts.1col')
@section('title', 'IIIF Compliance Dashboard')
@section('body-class', 'admin iiif validation')
@section('title-block')<h1 class="mb-0"><i class="fas fa-check-double me-2"></i>IIIF Compliance Dashboard</h1>@endsection
@section('content')
<div class="row mb-4">
  <div class="col-md-3"><div class="card text-center border-primary"><div class="card-body py-3"><h3 class="text-primary mb-0">{{ $stats['total'] ?? 0 }}</h3><small class="text-muted">Objects Validated</small></div></div></div>
  <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-3"><h3 class="text-success mb-0">{{ $stats['passed'] ?? 0 }}</h3><small class="text-muted">Fully Compliant</small></div></div></div>
  <div class="col-md-3"><div class="card text-center border-danger"><div class="card-body py-3"><h3 class="text-danger mb-0">{{ $stats['failed'] ?? 0 }}</h3><small class="text-muted">Issues Found</small></div></div></div>
  <div class="col-md-3"><div class="card text-center border-warning"><div class="card-body py-3"><h3 class="text-warning mb-0">{{ $stats['warning'] ?? 0 }}</h3><small class="text-muted">Warnings</small></div></div></div>
</div>
@if(isset($recentFailures) && count($recentFailures) > 0)
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Recent Failures</h5></div>
<div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Object</th><th>Issue</th><th>Date</th></tr></thead>
<tbody>@foreach($recentFailures as $f)<tr><td>{{ $f->title ?? '#' . ($f->object_id ?? '') }}</td><td>{{ $f->issue ?? '' }}</td><td>{{ $f->checked_at ?? '' }}</td></tr>@endforeach</tbody></table></div></div>
@endif
@endsection
