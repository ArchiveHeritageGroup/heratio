@extends('theme::layouts.1col')

@section('title', 'Extracted Metadata')

@section('content')

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">
      <i class="bi bi-file-earmark me-2"></i>
      {{ e($digitalObject->name ?: basename($digitalObject->path)) }}
    </h5>
    <div>
      <a href="{{ route('metadata-extraction.index') }}" class="btn atom-btn-white btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to List
      </a>
    </div>
  </div>
  <div class="card-body">

    @if(session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Digital Object Info --}}
    <div class="row mb-4">
      <div class="col-md-6">
        <h6>Digital Object Details</h6>
        <table class="table table-bordered table-sm">
          <tr>
            <th class="w-25">ID</th>
            <td>{{ $digitalObject->id }}</td>
          </tr>
          <tr>
            <th>File Name</th>
            <td>{{ e($digitalObject->name ?: basename($digitalObject->path)) }}</td>
          </tr>
          <tr>
            <th>MIME Type</th>
            <td><code>{{ e($digitalObject->mime_type ?? 'unknown') }}</code></td>
          </tr>
          <tr>
            <th>Size</th>
            <td>{{ $digitalObject->byte_size ? number_format($digitalObject->byte_size / 1024, 1) . ' KB' : '-' }}</td>
          </tr>
          <tr>
            <th>Path</th>
            <td><small class="text-muted">{{ e($digitalObject->path) }}</small></td>
          </tr>
          <tr>
            <th>Parent Record</th>
            <td>
              @if($digitalObject->slug)
                <a href="{{ url('/' . $digitalObject->slug) }}">
                  {{ e($digitalObject->record_title ?? 'Untitled') }}
                </a>
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
          </tr>
        </table>
      </div>
      <div class="col-md-6">
        <h6>Actions</h6>
        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn atom-btn-outline-success btn-sm" id="extractBtn">
            <i class="bi bi-download me-1"></i>Extract Metadata
          </button>
          @if($metadata->count() > 0)
            <button type="button" class="btn atom-btn-outline-danger btn-sm" id="deleteBtn">
              <i class="bi bi-trash me-1"></i>Delete Metadata
            </button>
          @endif
        </div>

        <div class="mt-3">
          <span class="badge bg-info">{{ $metadata->count() }} metadata fields extracted</span>
        </div>
      </div>
    </div>

    <hr>

    {{-- Extracted Metadata --}}
    @if(count($groupedMetadata) > 0)
      <h6>Extracted Metadata</h6>

      <div class="accordion" id="metadataAccordion">
        @php $index = 0; @endphp
        @foreach($groupedMetadata as $group => $fields)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button"
                      data-bs-toggle="collapse" data-bs-target="#collapse{{ $index }}">
                {{ e($group) }}
                <span class="badge bg-secondary ms-2">{{ count($fields) }}</span>
              </button>
            </h2>
            <div id="collapse{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}"
                 data-bs-parent="#metadataAccordion">
              <div class="accordion-body p-0">
                <table class="table table-bordered table-sm table-striped mb-0">
                  <thead>
                    <tr>
                      <th style="width: 30%">Field</th>
                      <th>Value</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($fields as $field)
                      <tr>
                        <td><code class="small">{{ e($field->name) }}</code></td>
                        <td>
                          @php
                            $value = $field->value;
                            $decoded = json_decode($value, true);
                          @endphp
                          @if(is_array($decoded))
                            <ul class="mb-0 small">
                              @foreach($decoded as $item)
                                <li>{{ e((string) $item) }}</li>
                              @endforeach
                            </ul>
                          @elseif(strlen($value) > 200)
                            <span class="small">{{ e(substr($value, 0, 200)) }}...</span>
                          @else
                            {{ e($value) }}
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          @php $index++; @endphp
        @endforeach
      </div>

    @else
      <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        No metadata has been extracted for this digital object yet. Click "Extract Metadata" to begin.
      </div>
    @endif

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var extractBtn = document.getElementById('extractBtn');
  var deleteBtn = document.getElementById('deleteBtn');
  var digitalObjectId = {{ $digitalObject->id }};

  if (extractBtn) {
    extractBtn.addEventListener('click', function() {
      extractBtn.disabled = true;
      extractBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Extracting...';

      fetch('{{ url("/admin/metadata-extraction") }}/' + digitalObjectId + '/extract', {
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
          extractBtn.disabled = false;
          extractBtn.innerHTML = '<i class="bi bi-download me-1"></i>Extract Metadata';
        }
      })
      .catch(err => {
        alert('Error: ' + err.message);
        extractBtn.disabled = false;
        extractBtn.innerHTML = '<i class="bi bi-download me-1"></i>Extract Metadata';
      });
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', function() {
      if (!confirm('Are you sure you want to delete all extracted metadata?')) {
        return;
      }

      deleteBtn.disabled = true;
      deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

      fetch('{{ url("/admin/metadata-extraction") }}/' + digitalObjectId + '/delete', {
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
          deleteBtn.disabled = false;
          deleteBtn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete Metadata';
        }
      })
      .catch(err => {
        alert('Error: ' + err.message);
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete Metadata';
      });
    });
  }
});
</script>

@endsection
