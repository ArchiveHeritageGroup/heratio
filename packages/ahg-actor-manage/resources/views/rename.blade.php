@extends('theme::layouts.1col')

@section('title')
  <h1>{{ $actor->authorized_form_of_name }}</h1>
@endsection

@section('content')

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('actor.processRename', $actor->slug) }}" id="rename-form">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="rename-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rename-collapse" aria-expanded="true" aria-controls="rename-collapse">
            Rename
          </button>
        </h2>
        <div id="rename-collapse" class="accordion-collapse collapse show" aria-labelledby="rename-heading">
          <div class="accordion-body">
            <p>Use this interface to update the authorized form of name, slug (permalink), and/or digital object filename.</p>
            <hr />

            {{-- Update authorized form of name --}}
            <div class="rename-form-field-toggle form-check mb-4">
              <input class="form-check-input" type="checkbox" id="rename_enable_authorizedFormOfName" name="update_name" value="1" checked>
              <label class="form-check-label" for="rename_enable_authorizedFormOfName">
                Update authorized form of name              </label>
            </div>
            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">Authorized form of name</label>
              <input type="text" class="form-control" id="authorized_form_of_name" name="authorized_form_of_name" value="{{ old('authorized_form_of_name', $actor->authorized_form_of_name) }}">
              <div class="form-text">Editing the authorized form of name will automatically update the slug field if the "Update slug" checkbox is selected - you can still edit it after.</div>
            </div>
            <p>Original authorized form of name: <em>{{ $actor->authorized_form_of_name }}</em></p>
            <hr />

            {{-- Slug duplicate warning --}}
            <div id="rename-slug-warning" class="alert alert-danger d-none" role="alert">
              A slug based on this name already exists so a number has been added to pad the slug.
            </div>

            {{-- Update slug --}}
            <div class="rename-form-field-toggle form-check mb-4">
              <input class="form-check-input" type="checkbox" id="rename_enable_slug" name="update_slug" value="1" checked>
              <label class="form-check-label" for="rename_enable_slug">
                Update slug              </label>
            </div>
            <div class="mb-3">
              <label for="slug" class="form-label">Slug</label>
              <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $actor->slug) }}">
              <div class="form-text">Do not use any special characters or spaces in the slug - only lower case alphanumeric characters (a-z, 0-9) and dashes (-) will be saved. Other characters will be stripped out or replaced. Editing the slug will not automatically update the other fields.</div>
            </div>
            <p>Original slug: <em>{{ $actor->slug }}</em></p>

            {{-- Update filename (only if digital objects exist) --}}
            @if ($digitalObject)
              <hr />
              <div class="rename-form-field-toggle form-check mb-4">
                <input class="form-check-input" type="checkbox" id="rename_enable_filename" name="update_filename" value="1" checked>
                <label class="form-check-label" for="rename_enable_filename">
                  Update filename                </label>
              </div>
              <div class="mb-3">
                <label for="filename" class="form-label">Filename <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control" id="filename" name="filename" value="{{ old('filename', $digitalObject->name) }}">
                <div class="form-text">Do not use any special characters or spaces in the filename - only lower case alphanumeric characters (a-z, 0-9) and dashes (-) will be saved. Other characters will be stripped out or replaced. Editing the filename will not automatically update the other fields.</div>
              </div>
              <p>Original filename: <em>{{ $digitalObject->name }}</em></p>
            @endif
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('actor.show', $actor->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" id="rename-form-submit" type="submit" value="Update"></li>
    </ul>

  </form>

@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const nameInput = document.getElementById('authorized_form_of_name');
  const slugInput = document.getElementById('slug');
  const nameCheckbox = document.getElementById('rename_enable_authorizedFormOfName');
  const slugCheckbox = document.getElementById('rename_enable_slug');
  const filenameCheckbox = document.getElementById('rename_enable_filename');

  // Toggle field disabled state based on checkbox
  function toggleField(checkbox, fieldId) {
    const field = document.getElementById(fieldId);
    if (field) {
      field.disabled = !checkbox.checked;
    }
  }

  nameCheckbox.addEventListener('change', function () {
    toggleField(this, 'authorized_form_of_name');
  });

  slugCheckbox.addEventListener('change', function () {
    toggleField(this, 'slug');
  });

  if (filenameCheckbox) {
    filenameCheckbox.addEventListener('change', function () {
      toggleField(this, 'filename');
    });
  }

  // Auto-update slug when name changes (if slug checkbox is checked)
  if (nameInput && slugInput) {
    nameInput.addEventListener('input', function () {
      if (slugCheckbox.checked) {
        slugInput.value = nameInput.value
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
