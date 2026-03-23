@extends('theme::layouts.1col')
@section('title', 'Format Conversion')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h1 class="mb-0"><i class="fas fa-sync-alt me-2"></i>Format Conversion</h1>
        <div class="text-end">
            <span class="text-muted">Pending conversions:</span>
            <span class="badge bg-{{ ($pendingConversions ?? 0) > 0 ? 'warning' : 'success' }} ms-2">
                {{ number_format($pendingConversions ?? 0) }}
            </span>
        </div>
    </div>
    <p class="text-muted">Convert digital object formats for long-term preservation.</p>

    {{-- Tool Status --}}
    <div class="row mb-4">
      @foreach($tools ?? [] as $name => $info)
      <div class="col-md-3 mb-3">
        <div class="card {{ ($info['available'] ?? false) ? 'border-success' : 'border-secondary' }}">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="mb-0">{{ $name }}</h6>
              @if($info['available'] ?? false) <span class="badge bg-success">Available</span>
              @else <span class="badge bg-secondary">Not Installed</span> @endif
            </div>
            <small class="text-muted">{{ implode(', ', array_slice((array)($info['formats'] ?? []), 0, 5)) }}</small>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Stats --}}
    <div class="row mb-4">
      @foreach(['completed' => 'success', 'processing' => 'info', 'pending' => 'warning', 'failed' => 'danger'] as $status => $color)
      <div class="col-md-3">
        <div class="card bg-{{ $color }} text-white">
          <div class="card-body">
            <h6 class="mb-0">{{ ucfirst($status) }}</h6>
            <h2 class="mb-0">{{ number_format($conversionStats[$status] ?? 0) }}</h2>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Supported Conversions --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-diagram-project me-2"></i>Supported Conversions</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <h6><i class="fas fa-image me-2"></i>Images (ImageMagick)</h6>
            <ul class="small mb-3"><li>JPEG, PNG, BMP, GIF &rarr; TIFF (uncompressed)</li></ul>
            <h6><i class="fas fa-music me-2"></i>Audio (FFmpeg)</h6>
            <ul class="small mb-3"><li>MP3, AAC, OGG &rarr; WAV (PCM)</li></ul>
          </div>
          <div class="col-md-6">
            <h6><i class="fas fa-file-pdf me-2"></i>Documents</h6>
            <ul class="small mb-3"><li>PDF &rarr; PDF/A (Ghostscript)</li><li>DOC, DOCX, XLS, PPT &rarr; PDF/A (LibreOffice)</li></ul>
            <h6><i class="fas fa-film me-2"></i>Video (FFmpeg)</h6>
            <ul class="small mb-0"><li>Various &rarr; MKV/FFV1 (lossless)</li></ul>
          </div>
        </div>
      </div>
    </div>

    {{-- CLI Commands --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-terminal me-2"></i>CLI Commands</div>
      <div class="card-body">
        <p class="mb-2">Run format conversions from the command line:</p>
        <pre class="bg-dark text-light p-3 rounded mb-0"><code># Show available tools and statistics
php artisan preservation:convert --status

# Preview conversions (dry run)
php artisan preservation:convert --dry-run

# Convert specific object to TIFF
php artisan preservation:convert --object-id=123 --format=tiff

# Batch convert JPEG images to TIFF
php artisan preservation:convert --mime-type=image/jpeg --format=tiff --limit=50</code></pre>
      </div>
    </div>

    {{-- Recent Conversions --}}
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-list me-2"></i>Recent Conversions</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              <th>File</th><th>Source</th><th>Target</th><th>Status</th><th>Tool</th><th>Created</th>
            </tr></thead>
            <tbody>
              @forelse($recentConversions ?? [] as $conv)
              <tr>
                <td><a href="{{ route('preservation.object', $conv->digital_object_id ?? 0) }}">{{ Str::limit($conv->filename ?? 'Unknown', 30) }}</a></td>
                <td><small>{{ $conv->source_format ?? '-' }}</small></td>
                <td><small>{{ $conv->target_format ?? '-' }}</small></td>
                <td>
                  @if($conv->status === 'completed') <span class="badge bg-success">Completed</span>
                  @elseif($conv->status === 'processing') <span class="badge bg-info">Processing</span>
                  @elseif($conv->status === 'failed') <span class="badge bg-danger">Failed</span>
                  @else <span class="badge bg-warning text-dark">{{ ucfirst($conv->status ?? 'pending') }}</span> @endif
                </td>
                <td><small>{{ $conv->conversion_tool ?? '-' }}</small></td>
                <td><small class="text-muted">{{ $conv->created_at ?? '' }}</small></td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-muted py-3">No conversions performed yet</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection