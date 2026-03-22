@extends('theme::layouts.1col')

@section('title', 'Portable Export')
@section('body-class', 'admin portable-export')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-export me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Portable Export</h1>
      <span class="small text-muted">Generate portable archival packages</span>
    </div>
  </div>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  {{-- Export Form --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Export</h5>
    </div>
    <div class="card-body">
      <form action="{{ route('portable-export.export') }}" method="POST">
        @csrf

        {{-- Scope --}}
        <div class="mb-3">
          <label class="form-label fw-bold">Scope <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_all" value="all" checked>
            <label class="form-check-label" for="scope_all">All — export the entire archive <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_repository" value="repository">
            <label class="form-check-label" for="scope_repository">Repository — export a single repository <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="scope" id="scope_fonds" value="fonds">
            <label class="form-check-label" for="scope_fonds">Fonds — export a single fonds <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          @error('scope')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>

        {{-- Repository dropdown (shown conditionally) --}}
        <div class="mb-3" id="repository-select" style="display: none;">
          <label for="repository_id" class="form-label fw-bold">Repository <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="repository_id" id="repository_id" class="form-select">
            <option value="">-- Select a repository --</option>
            @foreach($repositories as $repo)
              <option value="{{ $repo->id }}">{{ $repo->name }}</option>
            @endforeach
          </select>
          @error('repository_id')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>

        {{-- Mode --}}
        <div class="mb-3">
          <label class="form-label fw-bold">Mode <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="mode" id="mode_read_only" value="read_only" checked>
            <label class="form-check-label" for="mode_read_only">Read Only — browsable HTML package for reference use <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="mode" id="mode_archive" value="archive">
            <label class="form-check-label" for="mode_archive">Archive — full importable archive package <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          @error('mode')
            <div class="text-danger small mt-1">{{ $message }}</div>
          @enderror
        </div>

        {{-- Culture/Language --}}
        <div class="mb-3">
          <label for="culture" class="form-label fw-bold">Culture / Language <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="culture" id="culture" class="form-select">
            @foreach($languages as $lang)
              <option value="{{ $lang->code }}" {{ $lang->code === app()->getLocale() ? 'selected' : '' }}>
                {{ $lang->name }} ({{ $lang->code }})
              </option>
            @endforeach
          </select>
        </div>

        {{-- Include options --}}
        <div class="mb-3">
          <label class="form-label fw-bold">Include <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_digital_objects" id="include_digital_objects" value="1">
            <label class="form-check-label" for="include_digital_objects">Include digital objects (master files) <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_thumbnails" id="include_thumbnails" value="1">
            <label class="form-check-label" for="include_thumbnails">Include thumbnails <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="include_references" id="include_references" value="1">
            <label class="form-check-label" for="include_references">Include reference copies <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
        </div>

        {{-- Branding --}}
        <div class="mb-3">
          <label class="form-label fw-bold">Branding <span class="badge bg-secondary ms-1">Optional</span></label>
          <div class="mb-2">
            <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="title" id="title" placeholder="Export title" maxlength="255">
            @error('title')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>
          <div class="mb-2">
            <label for="subtitle" class="form-label">Subtitle <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="subtitle" id="subtitle" placeholder="Export subtitle" maxlength="255">
            @error('subtitle')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>
          <div class="mb-2">
            <label for="footer_text" class="form-label">Footer text <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" class="form-control" name="footer_text" id="footer_text" placeholder="Footer text for exported pages" maxlength="500">
            @error('footer_text')
              <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
          </div>
        </div>

        <button type="submit" class="btn atom-btn-outline-success">
          <i class="fas fa-play me-1"></i> Start Export
        </button>
      </form>
    </div>
  </div>

  {{-- Past Exports --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-history me-2"></i>Past Exports</h5>
    </div>
    <div class="card-body">
      @if(!$hasTable)
        <div class="alert alert-info mb-0">
          <i class="fas fa-info-circle me-1"></i>
          The portable export table has not been created yet. Export history will appear here once the system is fully configured.
        </div>
      @elseif($exports->isEmpty())
        <p class="text-muted mb-0">No exports have been created yet.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Title</th>
                <th>Scope</th>
                <th>Mode</th>
                <th>Status</th>
                <th>Created</th>
                <th>Download</th>
              </tr>
            </thead>
            <tbody>
              @foreach($exports as $export)
                <tr>
                  <td>{{ $export->title ?: '(untitled)' }}</td>
                  <td>
                    <span class="badge bg-secondary">{{ ucfirst($export->scope_type) }}</span>
                  </td>
                  <td>{{ $export->mode === 'read_only' ? 'Read Only' : 'Archive' }}</td>
                  <td>
                    @if($export->status === 'completed')
                      <span class="badge bg-success">Completed</span>
                    @elseif($export->status === 'pending')
                      <span class="badge bg-warning text-dark">Pending</span>
                    @elseif($export->status === 'processing')
                      <span class="badge bg-primary">Processing {{ $export->progress }}%</span>
                    @elseif($export->status === 'failed')
                      <span class="badge bg-danger">Failed</span>
                    @else
                      <span class="badge bg-secondary">{{ ucfirst($export->status) }}</span>
                    @endif
                  </td>
                  <td>{{ $export->created_at ? \Carbon\Carbon::parse($export->created_at)->format('Y-m-d H:i') : '' }}</td>
                  <td>
                    @if($export->status === 'completed' && !empty($export->output_path))
                      <a href="{{ $export->output_path }}" class="btn btn-sm atom-btn-outline-success">
                        <i class="fas fa-download me-1"></i> Download
                      </a>
                    @else
                      &mdash;
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var scopeRadios = document.querySelectorAll('input[name="scope"]');
      var repoSelect = document.getElementById('repository-select');

      function toggleRepoSelect() {
        var checked = document.querySelector('input[name="scope"]:checked');
        if (checked && (checked.value === 'repository' || checked.value === 'fonds')) {
          repoSelect.style.display = 'block';
        } else {
          repoSelect.style.display = 'none';
        }
      }

      scopeRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleRepoSelect);
      });

      toggleRepoSelect();
    });
  </script>
@endsection
