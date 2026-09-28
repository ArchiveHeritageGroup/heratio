{{-- ahg-biblio-frbr/export.blade.php - FRBR export UI --}}
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
          <i class="bi bi-box-arrow-up-right me-1"></i> {{ __('Export Configuration') }}
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
                    {{ $work->id }} - {{ $work->title }}
                    @if($work->author) - {{ $work->author }} @endif
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
                <i class="bi bi-download me-1"></i> {{ __('Download FRBR') }}
              </button>
              <a href="{{ route('frbr.index') }}" class="btn btn-outline-secondary">{{ __('Back to Dashboard') }}</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">{{ __('FRBR Entity Model') }}</div>
        <div class="card-body small">
          <dl class="row mb-1">
            <dt class="col-4">{{ __('Work') }}</dt>
            <dd class="col-8">{{ __('Distinct intellectual creation') }}</dd>
          </dl>
          <dl class="row mb-1">
            <dt class="col-4">{{ __('Expression') }}</dt>
            <dd class="col-8">{{ __('Text, translation, or edition') }}</dd>
          </dl>
          <dl class="row mb-1">
            <dt class="col-4">{{ __('Manifestation') }}</dt>
            <dd class="col-8">{{ __('Carrier and format') }}</dd>
          </dl>
          <dl class="row mb-0">
            <dt class="col-4">{{ __('Item') }}</dt>
            <dd class="col-8">{{ __('Concrete copy') }}</dd>
          </dl>
          <hr>
          <p class="mb-0 text-muted">
            Heratio maps Expression/Manifestation to <code>library_item</code> and Item to <code>library_copy</code>.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
