@extends('theme::layouts.1col')

@section('title', 'PDF Tools')

@section('content')

<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">PDF Tools</h5>
  </div>
  <div class="card-body">

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Tool Status --}}
    <h6>Tool Availability</h6>
    <div class="row mb-4">
      <div class="col-md-4">
        <div class="card {{ $pdftotextAvailable ? 'border-success' : 'border-danger' }}">
          <div class="card-body text-center">
            <span class="badge {{ $pdftotextAvailable ? 'bg-success' : 'bg-danger' }} mb-2">
              <i class="bi bi-{{ $pdftotextAvailable ? 'check-circle' : 'x-circle' }} me-1"></i>
              {{ $pdftotextAvailable ? 'Installed' : 'Not Installed' }}
            </span>
            <h6 class="card-title">pdftotext</h6>
            <p class="card-text small text-muted">PDF text extraction</p>
            @if($pdftotextVersion)
              <code class="small">{{ $pdftotextVersion }}</code>
            @elseif(!$pdftotextAvailable)
              <code class="small text-muted">sudo apt install poppler-utils</code>
            @endif
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card {{ $imageMagickAvailable ? 'border-success' : 'border-danger' }}">
          <div class="card-body text-center">
            <span class="badge {{ $imageMagickAvailable ? 'bg-success' : 'bg-danger' }} mb-2">
              <i class="bi bi-{{ $imageMagickAvailable ? 'check-circle' : 'x-circle' }} me-1"></i>
              {{ $imageMagickAvailable ? 'Installed' : 'Not Installed' }}
            </span>
            <h6 class="card-title">ImageMagick</h6>
            <p class="card-text small text-muted">Image/TIFF to PDF conversion</p>
            @if($imageMagickVersion)
              <code class="small">{{ $imageMagickVersion }}</code>
            @elseif(!$imageMagickAvailable)
              <code class="small text-muted">sudo apt install imagemagick</code>
            @endif
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card {{ $ghostscriptAvailable ? 'border-success' : 'border-danger' }}">
          <div class="card-body text-center">
            <span class="badge {{ $ghostscriptAvailable ? 'bg-success' : 'bg-danger' }} mb-2">
              <i class="bi bi-{{ $ghostscriptAvailable ? 'check-circle' : 'x-circle' }} me-1"></i>
              {{ $ghostscriptAvailable ? 'Installed' : 'Not Installed' }}
            </span>
            <h6 class="card-title">Ghostscript</h6>
            <p class="card-text small text-muted">PDF/A generation</p>
            @if($ghostscriptVersion)
              <code class="small">{{ $ghostscriptVersion }}</code>
            @elseif(!$ghostscriptAvailable)
              <code class="small text-muted">sudo apt install ghostscript</code>
            @endif
          </div>
        </div>
      </div>
    </div>

    <hr>

    {{-- Statistics --}}
    <h6>PDF Text Extraction Statistics</h6>
    <div class="row mb-4">
      <div class="col-md-4">
        <div class="card bg-light">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($pdfStats['total_pdfs']) }}</h3>
            <small class="text-muted">Total PDFs</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-light">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($pdfStats['extracted_count']) }}</h3>
            <small class="text-muted">Text Extracted</small>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-light">
          <div class="card-body text-center">
            <h3 class="mb-0">{{ number_format($pdfStats['remaining_count']) }}</h3>
            <small class="text-muted">Remaining</small>
          </div>
        </div>
      </div>
    </div>

    <hr>

    {{-- Quick Actions --}}
    <h6>Quick Actions</h6>
    <div class="d-flex gap-3 flex-wrap">
      @if($imageMagickAvailable)
        <a href="{{ route('pdf-tools.merge') }}" class="btn atom-btn-white">
          <i class="bi bi-files me-1"></i>Merge Files to PDF
        </a>
      @endif

      @if($pdftotextAvailable)
        <form action="{{ route('pdf-tools.batchExtractText') }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-outline-success" onclick="return confirm('Run batch text extraction on up to 50 PDFs?')">
            <i class="bi bi-lightning me-1"></i>Batch Extract Text
          </button>
        </form>
      @endif

      @if($pdftotextAvailable)
        <button type="button" class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#extractTextModal">
          <i class="bi bi-file-earmark-text me-1"></i>Extract Text from PDF
        </button>
      @endif
    </div>

    {{-- Supported Formats --}}
    <div class="mt-4">
      <h6>Supported Input Formats for Merge</h6>
      <div class="d-flex gap-2 flex-wrap">
        @foreach($supportedFormats as $fmt)
          <span class="badge bg-secondary">.{{ $fmt }}</span>
        @endforeach
        <span class="badge bg-secondary">.pdf</span>
      </div>
    </div>

  </div>
</div>

{{-- Extract Text Modal --}}
@if($pdftotextAvailable)
<div class="modal fade" id="extractTextModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('pdf-tools.extractText') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Extract Text from PDF</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Upload PDF File <span class="badge bg-danger ms-1">Required</span></label>
            <input type="file" class="form-control" name="pdf_file" accept=".pdf" required>
            <div class="form-text">Maximum 100 MB</div>
          </div>
          <div class="text-muted small">OR</div>
          <div class="mb-3 mt-2">
            <label class="form-label">Digital Object ID <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" class="form-control" name="digital_object_id" placeholder="Enter digital object ID">
            <div class="form-text">Extract text from an existing PDF in the repository</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="bi bi-file-earmark-text me-1"></i>Extract Text
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@endsection
