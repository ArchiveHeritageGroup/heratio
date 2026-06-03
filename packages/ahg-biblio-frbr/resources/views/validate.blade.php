{{-- ahg-biblio-frbr/validate.blade.php — FRBR validation UI --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">{{ __('FRBR Validator') }}</h1>
    <span class="badge bg-warning text-dark">Validate</span>
  </div>
  <p class="text-muted small mb-4">
    Paste or upload an FRBR document to check for structural correctness.
    Errors block import; warnings are advisory.
  </p>

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header">
          <i class="bi bi-check-circle me-1"></i> Validation
        </div>
        <div class="card-body">
          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
          @endif

          <form method="POST" action="{{ route('frbr.validate-run') }}" enctype="multipart/form-data">
            @csrf

            {{-- Tabs: paste vs upload --}}
            <ul class="nav nav-tabs mb-3" id="inputTabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="paste-tab" data-bs-toggle="tab"
                  data-bs-target="#paste-pane" type="button" role="tab">
                  Paste XML
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="upload-tab" data-bs-toggle="tab"
                  data-bs-target="#upload-pane" type="button" role="tab">
                  Upload File
                </button>
              </li>
            </ul>

            <div class="tab-content mb-3" id="inputTabContent">
              <div class="tab-pane fade show active" id="paste-pane" role="tabpanel">
                <textarea name="frbr_content" id="frbr_content" class="form-control font-monospace"
                  rows="12"
                  style="font-size:0.8rem;"
                  placeholder="{{ __('Paste FRBR XML here...') }}"
                >{{ old('frbr_content', $frbr_content ?? '') }}</textarea>
                @error('frbr_content')
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>
              <div class="tab-pane fade" id="upload-pane" role="tabpanel">
                <input type="file" name="frbr_file" class="form-control" accept=".xml">
                @error('frbr_file')
                  <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning">
                <i class="bi bi-check-circle me-1"></i> Validate
              </button>
              <a href="{{ route('frbr.index') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
          </form>
        </div>
      </div>

      {{-- Validation results --}}
      @if(($validation_result = session('validation_result')))
        <div class="card mt-4">
          <div class="card-header">
            <i class="bi bi-list-check me-1"></i> Validation Report
          </div>
          <div class="card-body">
            @if(empty($validation_result['errors']) && empty($validation_result['fatal']))
              <div class="alert alert-success mb-0">
                <i class="bi bi-check-circle me-1"></i>
                Document is structurally valid FRBR.
              </div>
            @else
              @if(! empty($validation_result['fatal']))
                <h6 class="text-danger">
                  <i class="bi bi-x-circle me-1"></i> Fatal Errors
                </h6>
                <ul class="list-unstyled text-danger small mb-3">
                  @foreach($validation_result['fatal'] as $msg)
                    <li class="mb-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $msg }}</li>
                  @endforeach
                </ul>
              @endif

              @if(! empty($validation_result['errors']))
                <h6 class="text-danger">
                  <i class="bi bi-x-circle me-1"></i> Errors
                </h6>
                <ul class="list-unstyled text-danger small mb-3">
                  @foreach($validation_result['errors'] as $msg)
                    <li class="mb-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $msg }}</li>
                  @endforeach
                </ul>
              @endif
            @endif

            @if(! empty($validation_result['warnings']))
              <h6 class="text-warning">
                <i class="bi bi-exclamation-triangle me-1"></i> Warnings
              </h6>
              <ul class="list-unstyled text-warning small mb-0">
                @foreach($validation_result['warnings'] as $msg)
                  <li class="mb-1"><i class="bi bi-exclamation-triangle me-1"></i>{{ $msg }}</li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      @endif
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">Validation Checks</div>
        <div class="card-body small">
          <p class="mb-2">The validator runs the following checks:</p>
          <ul class="text-muted mb-0">
            <li>Well-formed XML</li>
            <li>RDF root element is present</li>
            <li>At least one <code>frbr:Work</code> or <code>frbr:Expression</code></li>
            <li>Items have a parent Expression</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
