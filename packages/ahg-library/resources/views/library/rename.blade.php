@extends('theme::layouts.1col')

@section('title', 'Rename — ' . ($item->title ?? ''))
@section('body-class', 'edit library')

@section('content')

  <h1>{{ $item->title ?? '[Untitled]' }}</h1>

  <form method="POST" action="{{ route('library.rename-store', $item->slug ?? '') }}" id="rename-form">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="rename-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#rename-collapse" aria-expanded="true">
            Rename
          </button>
        </h2>
        <div id="rename-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <p>Use this interface to update the description title, slug (permalink), and/or digital object filename.</p>
            <hr>

            {{-- Title --}}
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="enable_title" name="enable_title" value="1" checked>
              <label class="form-check-label fw-bold" for="enable_title">Update title</label>
            </div>
            <div class="mb-3" id="title-field">
              <label for="title" class="form-label">Title</label>
              <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $item->title ?? '') }}">
              <div class="form-text">Editing the title will automatically update the slug field if "Update slug" is selected — you can still edit it after.</div>
              <p class="mt-1 mb-0"><small>Original title: <em>{{ $item->title ?? '' }}</em></small></p>
            </div>
            <hr>

            {{-- Slug --}}
            <div id="rename-slug-warning" class="alert alert-danger d-none" role="alert">
              A slug based on this title already exists so a number has been added to pad the slug.
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="enable_slug" name="enable_slug" value="1" checked>
              <label class="form-check-label fw-bold" for="enable_slug">Update slug</label>
            </div>
            <div class="mb-3" id="slug-field">
              <label for="slug" class="form-label">Slug</label>
              <input type="text" name="slug" id="slug" class="form-control" value="{{ old('slug', $item->slug ?? '') }}">
              <div class="form-text">Do not use special characters or spaces — only lowercase alphanumeric characters (a-z, 0-9) and dashes (-). Other characters will be stripped.</div>
              <p class="mt-1 mb-0"><small>Original slug: <em>{{ $item->slug ?? '' }}</em></small></p>
            </div>

            {{-- Filename (only if digital object exists) --}}
            @if(isset($digitalObject) && $digitalObject)
              <hr>
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="enable_filename" name="enable_filename" value="1" checked>
                <label class="form-check-label fw-bold" for="enable_filename">Update filename</label>
              </div>
              <div class="mb-3" id="filename-field">
                <label for="filename" class="form-label">Filename</label>
                <input type="text" name="filename" id="filename" class="form-control" value="{{ old('filename', $digitalObject->name ?? '') }}">
                <div class="form-text">Do not use special characters or spaces — only lowercase alphanumeric characters (a-z, 0-9) and dashes (-). Other characters will be stripped.</div>
                <p class="mt-1 mb-0"><small>Original filename: <em>{{ $digitalObject->name ?? '' }}</em></small></p>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3 nav gap-2">
      <li>
        <a href="{{ route('library.show', $item->slug ?? '') }}" class="btn atom-btn-outline-light">Cancel</a>
      </li>
      <li>
        <button type="submit" class="btn atom-btn-outline-success" id="rename-form-submit">Update</button>
      </li>
    </section>
  </form>

  <script>
  (function() {
    var titleInput = document.getElementById('title');
    var slugInput = document.getElementById('slug');
    var enableTitle = document.getElementById('enable_title');
    var enableSlug = document.getElementById('enable_slug');
    var enableFilename = document.getElementById('enable_filename');
    var slugWarning = document.getElementById('rename-slug-warning');

    // Toggle field visibility based on checkbox
    function toggleField(checkbox, fieldId) {
      if (!checkbox) return;
      var field = document.getElementById(fieldId);
      if (!field) return;
      checkbox.addEventListener('change', function() {
        field.style.opacity = this.checked ? '1' : '0.4';
        field.querySelectorAll('input').forEach(function(i) { i.disabled = !checkbox.checked; });
      });
    }
    toggleField(enableTitle, 'title-field');
    toggleField(enableSlug, 'slug-field');
    toggleField(enableFilename, 'filename-field');

    // Auto-generate slug from title
    if (titleInput && slugInput && enableSlug) {
      titleInput.addEventListener('input', function() {
        if (!enableSlug.checked) return;
        var slug = this.value.toLowerCase()
          .replace(/[^a-z0-9\s-]/g, '')
          .replace(/\s+/g, '-')
          .replace(/-+/g, '-')
          .replace(/^-|-$/g, '');
        slugInput.value = slug;

        // Check for duplicate via AJAX
        if (slug.length > 2) {
          fetch('/library-manage/slug-preview?slug=' + encodeURIComponent(slug) + '&exclude={{ $item->id }}')
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.exists) {
                slugWarning.classList.remove('d-none');
                slugInput.value = slug + '-{{ $item->id }}';
              } else {
                slugWarning.classList.add('d-none');
              }
            })
            .catch(function() { slugWarning.classList.add('d-none'); });
        }
      });
    }
  })();
  </script>

@endsection
