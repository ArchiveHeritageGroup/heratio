@extends('theme::layouts.1col')
@section('title', 'Facility Reports Report')
@section('body-class', 'gallery-reports facility-reports')
@section('title-block')<h1 class="mb-0">Facility Reports Report</h1>@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Facility Reports</h5>
  </div>
  <div class="card-body p-0">
    @if(isset($items) && count($items) > 0)
      <table class="table table-striped table-hover mb-0">
        <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>Name</th><th>Type</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          @foreach($items as $item)
            <tr><td>{{ $item->name ?? $item->title ?? '' }}</td><td>{{ $item->type ?? '-' }}</td><td>{{ $item->date ?? '-' }}</td><td>{{ ucfirst($item->status ?? '') }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="text-center py-4 text-muted">No records found.</div>
    @endif
  </div>
</div>
<div class="mt-3"><a href="{{ route('gallery-reports.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Reports Dashboard</a></div>
@endsection
