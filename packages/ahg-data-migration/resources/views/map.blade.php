@extends('theme::layouts.1col')

@section('title', 'Field Mapping - Data Migration')
@section('body-class', 'admin data-migration map')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-project-diagram me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Field Mapping</h1>
      <span class="small text-muted">Map source columns to target fields</span>
    </div>
  </div>

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item"><a href="{{ route('data-migration.upload') }}">Upload</a></li>
      <li class="breadcrumb-item active">Map Fields</li>
    </ol>
  </nav>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    File: <strong>{{ $fileName }}</strong> |
    Target: <strong>{{ $targetType }}</strong> |
    Rows: <strong>{{ number_format($totalRows) }}</strong> |
    Columns: <strong>{{ count($sourceColumns) }}</strong>
  </div>

  {{-- Load saved mapping --}}
  @if(count($savedMappings) > 0)
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h6 class="mb-0"><i class="fas fa-folder-open"></i> Load Saved Mapping</h6>
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          @foreach($savedMappings as $saved)
            <button type="button" class="btn btn-sm atom-btn-white load-mapping-btn"
                    data-mapping-id="{{ $saved['id'] }}"
                    data-mappings="{{ $saved['field_mappings'] }}">
              <i class="fas fa-map"></i> {{ $saved['name'] }}
            </button>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Mapping table --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-columns"></i> Column Mapping</h5>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm atom-btn-outline-success" id="autoMapBtn" title="Auto-map columns by name matching">
          <i class="fas fa-magic"></i> Auto Map
        </button>
        <button type="button" class="btn btn-sm atom-btn-white" id="clearMapBtn">
          <i class="fas fa-eraser"></i> Clear All
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered mb-0" id="mappingTable">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th style="width: 40%;">Source Column (CSV)</th>
              <th style="width: 10%; text-align: center;"><i class="fas fa-arrow-right"></i></th>
              <th style="width: 50%;">Target Field</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sourceColumns as $col)
              <tr>
                <td>
                  <code>{{ $col }}</code>
                </td>
                <td class="text-center text-muted"><i class="fas fa-long-arrow-alt-right"></i></td>
                <td>
                  <select class="form-select form-select-sm target-field-select" data-source="{{ $col }}">
                    <option value="">-- Skip this column --</option>
                    @foreach($targetFields as $fieldKey => $fieldLabel)
                      <option value="{{ $fieldKey }}">{{ $fieldLabel }}</option>
                    @endforeach
                  </select>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Source data preview --}}
  @if(count($previewRows) > 0)
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h6 class="mb-0"><i class="fas fa-table"></i> Source Data Preview (first {{ count($previewRows) }} rows)</h6>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                @foreach($sourceColumns as $col)
                  <th class="text-nowrap">{{ $col }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($previewRows as $row)
                <tr>
                  @foreach($sourceColumns as $col)
                    <td>{{ Str::limit($row[$col] ?? '', 80) }}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- Save mapping & action buttons --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h6 class="mb-0"><i class="fas fa-save"></i> Save Mapping &amp; Actions</h6>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4">
          <label for="mappingName" class="form-label">Mapping Name <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" class="form-control" id="mappingName" placeholder="e.g. ISAD CSV Import">
        </div>
        <div class="col-md-3">
          <label for="mappingCategory" class="form-label">Category <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" class="form-control" id="mappingCategory" value="Custom" placeholder="Category">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="button" class="btn atom-btn-white" id="saveMappingBtn">
            <i class="fas fa-save"></i> Save Mapping
          </button>
        </div>
      </div>

      <hr>

      <div class="d-flex flex-wrap gap-2">
        <form method="GET" action="{{ route('data-migration.preview') }}" id="previewForm" class="d-inline">
          <input type="hidden" name="mapping" id="previewMappingInput" value="{}">
          <button type="submit" class="btn atom-btn-white">
            <i class="fas fa-eye"></i> Preview Transformed Data
          </button>
        </form>

        <form method="POST" action="{{ route('data-migration.execute') }}" id="executeForm" class="d-inline" onsubmit="return confirm('Are you sure you want to execute this import? This will modify the database.')">
          @csrf
          <input type="hidden" name="mapping" id="executeMappingInput" value="{}">
          <input type="hidden" name="name" id="executeJobName" value="">
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-play"></i> Execute Import
          </button>
        </form>

        <a href="{{ route('data-migration.upload') }}" class="btn atom-btn-white">
          <i class="fas fa-arrow-left"></i> Back to Upload
        </a>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function getMapping() {
        var mapping = {};
        document.querySelectorAll('.target-field-select').forEach(function (sel) {
            if (sel.value) {
                mapping[sel.dataset.source] = sel.value;
            }
        });
        return mapping;
    }

    function updateHiddenInputs() {
        var json = JSON.stringify(getMapping());
        document.getElementById('previewMappingInput').value = json;
        document.getElementById('executeMappingInput').value = json;
    }

    document.querySelectorAll('.target-field-select').forEach(function (sel) {
        sel.addEventListener('change', updateHiddenInputs);
    });

    // Auto-map by matching column names to target field keys
    document.getElementById('autoMapBtn').addEventListener('click', function () {
        var targetOptions = {};
        var firstSelect = document.querySelector('.target-field-select');
        if (firstSelect) {
            firstSelect.querySelectorAll('option').forEach(function (opt) {
                if (opt.value) {
                    targetOptions[opt.value.toLowerCase()] = opt.value;
                    targetOptions[opt.textContent.trim().toLowerCase()] = opt.value;
                }
            });
        }
        document.querySelectorAll('.target-field-select').forEach(function (sel) {
            var source = sel.dataset.source.toLowerCase().replace(/[\s_-]+/g, '_');
            if (targetOptions[source]) {
                sel.value = targetOptions[source];
            }
        });
        updateHiddenInputs();
    });

    // Clear all mappings
    document.getElementById('clearMapBtn').addEventListener('click', function () {
        document.querySelectorAll('.target-field-select').forEach(function (sel) {
            sel.value = '';
        });
        updateHiddenInputs();
    });

    // Load saved mapping
    document.querySelectorAll('.load-mapping-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var mappings = {};
            try {
                mappings = JSON.parse(this.dataset.mappings);
            } catch (e) {
                alert('Could not parse mapping data.');
                return;
            }
            document.querySelectorAll('.target-field-select').forEach(function (sel) {
                var source = sel.dataset.source;
                if (mappings[source]) {
                    sel.value = mappings[source];
                } else {
                    sel.value = '';
                }
            });
            updateHiddenInputs();
        });
    });

    // Save mapping via AJAX
    document.getElementById('saveMappingBtn').addEventListener('click', function () {
        var name = document.getElementById('mappingName').value.trim();
        if (!name) {
            alert('Please enter a mapping name.');
            return;
        }
        var mapping = getMapping();
        if (Object.keys(mapping).length === 0) {
            alert('Please map at least one column.');
            return;
        }
        fetch('{{ route("data-migration.save-mapping") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: name,
                target_type: '{{ $targetType }}',
                category: document.getElementById('mappingCategory').value.trim() || 'Custom',
                field_mappings: mapping
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                alert('Mapping saved successfully (ID: ' + data.id + ').');
            } else {
                alert('Failed to save mapping.');
            }
        })
        .catch(function () {
            alert('Error saving mapping.');
        });
    });

    // Set job name from mapping name
    document.getElementById('executeForm').addEventListener('submit', function () {
        var name = document.getElementById('mappingName').value.trim();
        document.getElementById('executeJobName').value = name || 'CSV Import';
        updateHiddenInputs();
    });

    updateHiddenInputs();
});
</script>
@endpush
