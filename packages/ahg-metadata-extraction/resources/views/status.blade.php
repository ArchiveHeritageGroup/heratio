@extends('theme::layouts.1col')

@section('title', 'Metadata Extraction Status')

@section('content')

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">System Status</h5>
    <a href="{{ route('metadata-extraction.index') }}" class="btn atom-btn-white btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
  <div class="card-body">

    {{-- Tool Status --}}
    <div class="row mb-4">
      <div class="col-md-6">
        <h6>Tool Availability</h6>
        <table class="table table-bordered table-sm">
          <tr>
            <th class="w-50">ExifTool</th>
            <td>
              @if($exifToolAvailable)
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Installed</span>
                <code class="ms-2">{{ $exifToolVersion }}</code>
              @else
                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Not installed</span>
              @endif
            </td>
          </tr>
          <tr>
            <th>ffprobe</th>
            <td>
              @if($ffprobeAvailable)
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Installed</span>
                <code class="ms-2">{{ $ffprobeVersion }}</code>
              @else
                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Not installed</span>
              @endif
            </td>
          </tr>
          <tr>
            <th>pdfinfo</th>
            <td>
              @if($pdfinfoAvailable)
                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Installed</span>
              @else
                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Not installed</span>
              @endif
            </td>
          </tr>
        </table>

        @if(!$exifToolAvailable)
          <div class="alert alert-warning">
            <h6>Installation Instructions</h6>
            <p class="mb-2">ExifTool is required for metadata extraction. Install with:</p>
            <code>sudo apt install exiftool</code>
            <p class="mt-2 mb-0 small text-muted">For other systems: https://exiftool.org/install.html</p>
          </div>
        @endif

        @if(!$ffprobeAvailable)
          <div class="alert alert-warning">
            <h6>ffprobe Installation</h6>
            <p class="mb-2">ffprobe is required for video/audio metadata. Install with:</p>
            <code>sudo apt install ffmpeg</code>
          </div>
        @endif

        @if(!$pdfinfoAvailable)
          <div class="alert alert-warning">
            <h6>pdfinfo Installation</h6>
            <p class="mb-2">pdfinfo is required for PDF metadata. Install with:</p>
            <code>sudo apt install poppler-utils</code>
          </div>
        @endif
      </div>

      <div class="col-md-6">
        <h6>Extraction Statistics</h6>
        <table class="table table-bordered table-sm">
          <tr>
            <th class="w-50">Total Digital Objects</th>
            <td><strong>{{ number_format($stats['total_digital_objects']) }}</strong></td>
          </tr>
          <tr>
            <th>Objects with Metadata</th>
            <td>
              <strong>{{ number_format($stats['objects_with_metadata']) }}</strong>
              @if($stats['total_digital_objects'] > 0)
                <small class="text-muted">({{ round($stats['objects_with_metadata'] / $stats['total_digital_objects'] * 100, 1) }}%)</small>
              @endif
            </td>
          </tr>
          <tr>
            <th>Total Metadata Fields</th>
            <td><strong>{{ number_format($stats['total_metadata_fields']) }}</strong></td>
          </tr>
          <tr>
            <th>Average Fields per Object</th>
            <td>
              <strong>{{ $stats['objects_with_metadata'] > 0 ? round($stats['total_metadata_fields'] / $stats['objects_with_metadata'], 1) : 0 }}</strong>
            </td>
          </tr>
        </table>
      </div>
    </div>

    <hr>

    {{-- MIME Type Breakdown --}}
    <h6>MIME Type Breakdown</h6>
    <p class="text-muted small">Top 10 file types in your repository</p>

    @if($stats['mime_type_breakdown']->count() > 0)
      <div class="table-responsive">
        <table class="table table-bordered table-sm table-striped">
          <thead>
            <tr>
              <th>MIME Type</th>
              <th>Count</th>
              <th>Supported</th>
            </tr>
          </thead>
          <tbody>
            @foreach($stats['mime_type_breakdown'] as $item)
              <tr>
                <td><code>{{ e($item->mime_type) }}</code></td>
                <td>{{ number_format($item->count) }}</td>
                <td>
                  @if(in_array($item->mime_type, $supportedTypes))
                    <span class="badge bg-success">Yes</span>
                  @else
                    <span class="badge bg-secondary">Limited</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="alert alert-info">
        No digital objects found in the repository.
      </div>
    @endif

    <hr>

    {{-- Supported Formats --}}
    <h6>Supported Formats</h6>
    <p class="text-muted small">ExifTool can extract metadata from the following file types:</p>

    <div class="row">
      <div class="col-md-3">
        <h6 class="text-muted small">Images</h6>
        <ul class="small">
          <li>JPEG/JPG</li>
          <li>PNG</li>
          <li>TIFF</li>
          <li>GIF</li>
          <li>BMP</li>
          <li>WebP</li>
          <li>RAW formats</li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-muted small">Documents</h6>
        <ul class="small">
          <li>PDF</li>
          <li>Office documents</li>
          <li>OpenDocument</li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-muted small">Video</h6>
        <ul class="small">
          <li>MP4</li>
          <li>AVI</li>
          <li>MOV</li>
          <li>MKV</li>
        </ul>
      </div>
      <div class="col-md-3">
        <h6 class="text-muted small">Audio</h6>
        <ul class="small">
          <li>MP3</li>
          <li>WAV</li>
          <li>FLAC</li>
          <li>OGG</li>
        </ul>
      </div>
    </div>

  </div>
</div>

@endsection
