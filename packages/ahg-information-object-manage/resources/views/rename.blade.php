@extends('ahg-theme-b5::layout')

@section('title', 'Rename - ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container-fluid py-3">
  <div class="row">

    {{-- Sidebar --}}
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-info-circle me-1"></i> Context
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.show', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-arrow-left me-1"></i> Back to description
          </a>
        </div>
      </div>
    </div>

    {{-- Main content --}}
    <div class="col-md-9">

      <h1 class="mb-3">{{ $io->title ?? 'Untitled' }}</h1>

      <form action="{{ route('informationobject.renameUpdate', $io->slug) }}" method="POST" id="rename-form">
        @csrf
        @method('PUT')

        <div class="accordion mb-3">
          <div class="accordion-item">
            <h2 class="accordion-header" id="rename-heading">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rename-collapse" aria-expanded="true" aria-controls="rename-collapse">
                Rename
              </button>
            </h2>
            <div id="rename-collapse" class="accordion-collapse collapse show" aria-labelledby="rename-heading">
              <div class="accordion-body">
                <p>Use this interface to update the description title, slug (permalink), and/or digital object filename.</p>
                <hr>

                <div class="rename-form-field-toggle form-check mb-4">
                  <input class="form-check-input" type="checkbox" id="rename_enable_title" checked>
                  <label class="form-check-label" for="rename_enable_title">
                    Update title
                  </label>
                </div>
                <div class="mb-3">
                  <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" id="title" name="title" value="{{ $io->title }}">
                  <div class="form-text">Editing the description title will automatically update the slug field if the "Update slug" checkbox is selected - you can still edit it after.</div>
                </div>
                <p>Original title: <em>{{ $io->title }}</em></p>
                <hr>

                <div id="rename-slug-warning" class="alert alert-danger d-none" role="alert">
                  A slug based on this title already exists so a number has been added to pad the slug.
                </div>
                <div class="rename-form-field-toggle form-check mb-4">
                  <input class="form-check-input" type="checkbox" id="rename_enable_slug" checked>
                  <label class="form-check-label" for="rename_enable_slug">
                    Update slug
                  </label>
                </div>
                <div class="mb-3">
                  <label for="slug" class="form-label">Slug <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" class="form-control" id="slug" name="slug" value="{{ $io->slug }}">
                  <div class="form-text">Do not use any special characters or spaces in the slug - only lower case alphanumeric characters (a-z, 0-9) and dashes (-) will be saved. Other characters will be stripped out or replaced. Editing the slug will not automatically update the other fields.</div>
                </div>
                <p>Original slug: <em>{{ $io->slug }}</em></p>

                @if(isset($digitalObject) && $digitalObject)
                  <hr>
                  <div class="rename-form-field-toggle form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="rename_enable_filename" checked>
                    <label class="form-check-label" for="rename_enable_filename">
                      Update filename
                    </label>
                  </div>
                  <div class="mb-3">
                    <label for="filename" class="form-label">Filename <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="text" class="form-control" id="filename" name="filename" value="{{ $digitalObject->name }}">
                    <div class="form-text">Do not use any special characters or spaces in the filename - only lower case alphanumeric characters (a-z, 0-9) and dashes (-) will be saved. Other characters will be stripped out or replaced. Editing the filename will not automatically update the other fields.</div>
                  </div>
                  <p>Original filename: <em>{{ $digitalObject->name }}</em></p>
                @endif
              </div>
            </div>
          </div>
        </div>

        <ul class="actions mb-3 nav gap-2">
          <li>
            <a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a>
          </li>
          <li>
            <input class="btn atom-btn-outline-success" id="rename-form-submit" type="submit" value="Update">
          </li>
        </ul>

      </form>

    </div>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    var enableTitle = document.getElementById('rename_enable_title');
    var enableSlug = document.getElementById('rename_enable_slug');
    var enableFilename = document.getElementById('rename_enable_filename');

    // Toggle field enabled/disabled based on checkboxes
    if (enableTitle) {
        enableTitle.addEventListener('change', function() {
            titleInput.disabled = !this.checked;
        });
    }
    if (enableSlug) {
        enableSlug.addEventListener('change', function() {
            slugInput.disabled = !this.checked;
        });
    }
    if (enableFilename) {
        var filenameInput = document.getElementById('filename');
        enableFilename.addEventListener('change', function() {
            filenameInput.disabled = !this.checked;
        });
    }

    // Auto-update slug when title changes (if slug checkbox is checked)
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            if (enableSlug && enableSlug.checked) {
                slugInput.value = this.value
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            }
        });
    }
});
</script>
@endpush
@endsection
