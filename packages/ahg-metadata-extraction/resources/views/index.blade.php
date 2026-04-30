@extends('theme::layouts.1col')

@section('title', 'Metadata Extraction')

@section('content')

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">{{ __('Digital Objects') }}</h5>
    <div>
      <a href="{{ route('metadata-extraction.status') }}" class="btn atom-btn-white btn-sm me-2">
        <i class="bi bi-info-circle me-1"></i>{{ __('Status') }}
      </a>
      @if($exifToolAvailable)
        <form action="{{ route('metadata-extraction.batchExtract') }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-outline-light btn-sm" onclick="return confirm('Run batch extraction on up to 50 objects?')">
            <i class="bi bi-lightning me-1"></i>{{ __('Batch Extract') }}
          </button>
        </form>
      @endif
    </div>
  </div>
  <div class="card-body">

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Tool Status --}}
    <div class="row mb-3">
      <div class="col-auto">
        <span class="badge {{ $exifToolAvailable ? 'bg-success' : 'bg-danger' }}">
          <i class="bi bi-{{ $exifToolAvailable ? 'check-circle' : 'x-circle' }} me-1"></i>ExifTool{{ $exifToolVersion ? ' '.$exifToolVersion : '' }}
        </span>
      </div>
      <div class="col-auto">
        <span class="badge {{ $ffprobeAvailable ? 'bg-success' : 'bg-danger' }}">
          <i class="bi bi-{{ $ffprobeAvailable ? 'check-circle' : 'x-circle' }} me-1"></i>ffprobe{{ $ffprobeVersion ? ' '.$ffprobeVersion : '' }}
        </span>
      </div>
      <div class="col-auto">
        <span class="badge {{ $pdfinfoAvailable ? 'bg-success' : 'bg-danger' }}">
          <i class="bi bi-{{ $pdfinfoAvailable ? 'check-circle' : 'x-circle' }} me-1"></i>pdfinfo
        </span>
      </div>
    </div>

    @if(!$exifToolAvailable)
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>{{ __('ExifTool Not Available') }}</strong>
        <p class="mb-0 mt-2">ExifTool is not installed on this system. Install it with: <code>sudo apt install exiftool</code></p>
      </div>
    @endif

    {{-- Filters --}}
    <form method="get" action="{{ route('metadata-extraction.index') }}" class="mb-4">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">MIME Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="mime_type" class="form-select form-select-sm">
            <option value="">{{ __('All types') }}</option>
            @foreach($mimeTypes as $mime)
              <option value="{{ $mime }}" {{ $filterMimeType === $mime ? 'selected' : '' }}>{{ $mime }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Has Metadata <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="extracted" class="form-select form-select-sm">
            <option value="">{{ __('All') }}</option>
            <option value="yes" {{ $filterExtracted === 'yes' ? 'selected' : '' }}>{{ __('Yes - has metadata') }}</option>
            <option value="no" {{ $filterExtracted === 'no' ? 'selected' : '' }}>{{ __('No - not extracted') }}</option>
          </select>
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn atom-btn-outline-light btn-sm me-2">{{ __('Filter') }}</button>
          <a href="{{ route('metadata-extraction.index') }}" class="btn atom-btn-white btn-sm">Clear</a>
        </div>
      </div>
    </form>

    {{-- Results --}}
    <p class="text-muted small">Showing {{ $digitalObjects->count() }} of {{ number_format($totalCount) }} digital objects</p>

    @if($digitalObjects->count() > 0)
      <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
          <thead>
            <tr>
              <th>{{ __('ID') }}</th>
              <th>{{ __('File Name') }}</th>
              <th>{{ __('MIME Type') }}</th>
              <th>{{ __('Size') }}</th>
              <th>{{ __('Parent Record') }}</th>
              <th>{{ __('Metadata') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($digitalObjects as $obj)
              <tr>
                <td>{{ $obj->id }}</td>
                <td>
                  <a href="{{ route('metadata-extraction.view', $obj->id) }}">
                    {{ e($obj->name ?: basename($obj->path)) }}
                  </a>
                </td>
                <td><code class="small">{{ e($obj->mime_type ?? 'unknown') }}</code></td>
                <td>{{ $obj->byte_size ? number_format($obj->byte_size / 1024, 1) . ' KB' : '-' }}</td>
                <td>
                  @if($obj->information_object_id)
                    {{ e(mb_substr($obj->record_title ?? 'Untitled', 0, 40)) }}
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  @if($obj->metadata_count > 0)
                    <span class="badge bg-success">{{ $obj->metadata_count }} fields</span>
                  @else
                    <span class="badge bg-secondary">{{ __('None') }}</span>
                  @endif
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('metadata-extraction.view', $obj->id) }}" class="btn atom-btn-white" title="{{ __('View metadata') }}">
                      <i class="bi bi-eye"></i>
                    </a>
                    @if($exifToolAvailable)
                      <button type="button" class="btn atom-btn-outline-success extract-btn" data-id="{{ $obj->id }}" title="{{ __('Extract metadata') }}">
                        <i class="bi bi-download"></i>
                      </button>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Pagination --}}
      @if($totalPages > 1)
        <nav aria-label="{{ __('Page navigation') }}">
          <ul class="pagination pagination-sm justify-content-center">
            @if($page > 1)
              <li class="page-item">
                <a class="page-link" href="{{ route('metadata-extraction.index', ['page' => $page - 1, 'mime_type' => $filterMimeType, 'extracted' => $filterExtracted]) }}">Previous</a>
              </li>
            @endif

            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item {{ $i === $page ? 'active' : '' }}">
                <a class="page-link" href="{{ route('metadata-extraction.index', ['page' => $i, 'mime_type' => $filterMimeType, 'extracted' => $filterExtracted]) }}">{{ $i }}</a>
              </li>
            @endfor

            @if($page < $totalPages)
              <li class="page-item">
                <a class="page-link" href="{{ route('metadata-extraction.index', ['page' => $page + 1, 'mime_type' => $filterMimeType, 'extracted' => $filterExtracted]) }}">Next</a>
              </li>
            @endif
          </ul>
        </nav>
      @endif

    @else
      <div class="alert alert-info">
        No digital objects found matching your criteria.
      </div>
    @endif

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.extract-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var id = this.dataset.id;
      var button = this;

      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

      fetch('{{ url("/admin/metadata-extraction") }}/' + id + '/extract', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          button.disabled = false;
          button.innerHTML = '<i class="bi bi-download"></i>';
        }
      })
      .catch(err => {
        alert('Error: ' + err.message);
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-download"></i>';
      });
    });
  });
});
</script>

@endsection
