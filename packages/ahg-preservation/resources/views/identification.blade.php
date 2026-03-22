@extends('theme::layouts.1col')
@section('title', 'Format Identification')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-fingerprint me-2"></i>Format Identification</h1>
    <p class="text-muted">Identify file formats using PRONOM registry and DROID/Siegfried.</p>

    {{-- Stats --}}
    <div class="row mb-4">
      <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h6 class="text-white-50">Total Objects</h6><h2 class="mb-0">{{ number_format($stats['total'] ?? 0) }}</h2></div></div></div>
      <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body"><h6 class="text-white-50">Identified</h6><h2 class="mb-0">{{ number_format($stats['identified'] ?? 0) }}</h2></div></div></div>
      <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body"><h6>Unidentified</h6><h2 class="mb-0">{{ number_format($stats['unidentified'] ?? 0) }}</h2></div></div></div>
      <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body"><h6 class="text-white-50">Unique Formats</h6><h2 class="mb-0">{{ number_format($stats['unique_formats'] ?? 0) }}</h2></div></div></div>
    </div>

    {{-- Results --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-list me-2"></i>Recent Identifications</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              <th>File</th><th>PUID</th><th>Format</th><th>MIME</th><th>Tool</th><th>Date</th>
            </tr></thead>
            <tbody>
              @forelse($identifications ?? [] as $id)
              <tr>
                <td>{{ Str::limit($id->filename ?? '', 40) }}</td>
                <td><code>{{ $id->puid ?? '-' }}</code></td>
                <td>{{ $id->format_name ?? '-' }}</td>
                <td><small>{{ $id->mime_type ?? '-' }}</small></td>
                <td><small>{{ $id->tool ?? '-' }}</small></td>
                <td><small class="text-muted">{{ $id->created_at ?? '' }}</small></td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-muted py-3">No identifications performed yet</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection