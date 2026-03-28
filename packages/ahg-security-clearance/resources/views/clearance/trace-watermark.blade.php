@extends('ahg-theme-b5::layout')

@section('title', 'Trace Watermark')

@section('content')
<div class="container-fluid mt-3">
  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.dashboard') }}">Security Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('security-clearance.watermark-settings') }}">Watermark Settings</a></li>
    <li class="breadcrumb-item active">Trace Watermark</li>
  </ol></nav>

  <h1><i class="fas fa-search"></i> Trace Watermark</h1>
  <p class="text-muted">Enter a watermark code to identify who downloaded the document.</p>

  <div class="card mb-4">
    <div class="card-body">
      <form method="POST" action="{{ route('security-clearance.trace-watermark-result') }}">
        @csrf
        <div class="row">
          <div class="col-md-6">
            <div class="mb-3">
              <label class="form-label">Watermark Code</label>
              <input type="text" name="code" class="form-control" value="{{ old('code', request('code', '')) }}" placeholder="e.g. WM-2024-ABC123" required>
            </div>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <button type="submit" class="btn btn-primary mb-3"><i class="fas fa-search"></i> Trace</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  @if(isset($traceResult))
  <div class="card">
    <div class="card-header"><h5 class="mb-0">Trace Result</h5></div>
    <div class="card-body">
      @if($traceResult)
        <table class="table">
          <tr><th>User</th><td>{{ e($traceResult->username ?? '') }}</td></tr>
          <tr><th>Email</th><td>{{ e($traceResult->email ?? '') }}</td></tr>
          <tr><th>Object</th><td>{{ e($traceResult->object_title ?? '') }}</td></tr>
          <tr><th>Downloaded At</th><td>{{ $traceResult->downloaded_at ?? '' }}</td></tr>
          <tr><th>IP Address</th><td>{{ e($traceResult->ip_address ?? '') }}</td></tr>
          <tr><th>Watermark Type</th><td>{{ e($traceResult->watermark_type ?? '') }}</td></tr>
        </table>
      @else
        <div class="alert alert-warning">No matching watermark code found.</div>
      @endif
    </div>
  </div>
  @endif
</div>
@endsection
