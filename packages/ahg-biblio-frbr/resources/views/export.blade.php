{{-- ahg-biblio-frbr/export.blade.php — FRBR export UI --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ __('FRBR Export') }}</h1>
    <span class="badge bg-primary">IFLA FRBR</span>
  </div>
  <p class="text-muted small mb-4">
    Export a bibliographic work as FRBR XML. Select a work, choose a format, and download the file.
  </p>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-box-arrow-up-right me-1"></i> Export Configuration
        </div>
        <div class="card-body">
          @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
          @endif

          <form method="POST" action="{{ route('frbr.export-run') }}">
            @csrf

            <div class="mb-3">
              <label for="work_id" class="form-label">{{ __('Bibliographic Work') }}</label>
              <select name="work_id" id="work_id" class="form-select" required>
                <option value="">-- Select a work --</option>
                @foreach($works as $work)
                  <option value="{{ $work->id }}"
                    {{ old('work_id') == $work->id ? 'selected' : '' }}>
                    {{ $work->id }} &mdash; {{ $work->title }}
                    @if($work->author) &mdash; {{ $work->author }} @endif
                  </option>
                @endforeach
              </select>
              @error('work_id')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="format" class="form-label">{{ __('Output Format') }}</label>
              <select name="format" id="format" class="form-select" required>
                <option value="xml" {{ old('format', 'xml') == 'xml' ? 'selected' : '' }}>{{ __('XML (default)') }}</option>
                <option value="json" {{ old('format') == 'json' ? 'selected' : '' }}>JSON</option>
                <option value="rdf" {{ old('format') == 'rdf' ? 'selected' : '' }}>RDF</option>
              </select>
              @error('format')
                <div class="text-danger small mt-1">{{ $message }}</div>
              @enderror
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-download me-1"></i> Download FRBR
              </button>
              <a href="{{ route('frbr.index') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">FRBR Entity Model</div>
        <div class="card-body small">
          <dl class="row mb-1">
            <dt class="col-4">Work</dt>
            <dd class="col-8">Distinct intellectual creation</dd>
          </dl>
          <dl class="row mb-1">
            <dt class="col-4">Expression</dt>
            <dd class="col-8">Text, translation, or edition</dd>
          </dl>
          <dl class="row mb-1">
            <dt class="col-4">Manifestation</dt>
            <dd class="col-8">Carrier and format</dd>
          </dl>
          <dl class="row mb-0">
            <dt class="col-4">Item</dt>
            <dd class="col-8">Concrete copy</dd>
          </dl>
          <hr>
          <p class="mb-0 text-muted">
            Heratio maps Expression/Manifestation to <code>library_biblio_instance</code> and Item to <code>library_biblio_item</code>.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
