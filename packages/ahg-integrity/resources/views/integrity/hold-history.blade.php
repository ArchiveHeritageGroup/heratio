@extends('theme::layouts.1col')
@section('title', 'Integrity - Hold History')
@section('body-class', 'admin integrity holds history')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Hold History</h1><span class="small text-muted">{{ $ioTitle }} (IO #{{ $ioId }})</span></div>
  </div>
@endsection
@section('content')

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">All Legal Holds for <a href="{{ url('/informationobject/show/' . $ioId) }}" class="text-white text-decoration-underline">{{ $ioTitle }}</a></h5>
  </div>
  <div class="card-body p-0">
    @if(count($history) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <th>ID</th><th>Status</th><th>Reason</th><th>Placed By</th><th>Placed At</th><th>Released By</th><th>Released At</th>
      </tr></thead>
      <tbody>
        @foreach($history as $h)
        @php $hold = is_array($h) ? (object) $h : $h; @endphp
        <tr>
          <td>{{ $hold->id }}</td>
          <td><span class="badge bg-{{ $hold->status === 'active' ? 'danger' : 'secondary' }}">{{ ucfirst($hold->status) }}</span></td>
          <td>{{ $hold->reason }}</td>
          <td>{{ $hold->placed_by }}</td>
          <td>{{ $hold->placed_at }}</td>
          <td>{{ $hold->released_by ?? '-' }}</td>
          <td>{{ $hold->released_at ?? '-' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="text-center py-4 text-muted">No hold history for this information object.</div>
    @endif
  </div>
</div>

<div class="mt-3">
  <a href="{{ route('integrity.holds') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Holds</a>
  <a href="{{ url('/informationobject/show/' . $ioId) }}" class="btn atom-btn-white"><i class="fas fa-file-alt me-1"></i>View Object</a>
</div>
@endsection
