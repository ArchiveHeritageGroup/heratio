{{-- Create New Landing Page - migrated from ahgLandingPagePlugin/templates/createSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Create Landing Page')

@section('content')
<div class="container py-4" style="max-width: 600px;">
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="{{ route('landing-page.list') }}">Landing Pages</a>
      </li>
      <li class="breadcrumb-item active">Create New</li>
    </ol>
  </nav>

  <div class="card">
    <div class="card-header">
      <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Landing Page</h5>
    </div>
    <div class="card-body">
      @if ($errors->any())
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle"></i>
          @foreach ($errors->all() as $error)
            {{ $error }}
          @endforeach
        </div>
      @endif

      <form method="POST" action="{{ route('landing-page.create') }}">
        @csrf

        <div class="mb-3">
          <label class="form-label" for="name">Page Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="name" class="form-control" required
                 value="{{ old('name', '') }}"
                 placeholder="e.g., Home Page, About Us">
          <div class="form-text">Internal name for this landing page</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="slug">URL Slug <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text">/</span>
            <input type="text" name="slug" id="slug" class="form-control" required
                   value="{{ old('slug', '') }}"
                   placeholder="e.g., home, about-us"
                   pattern="[a-z0-9\-]+"
                   title="Lowercase letters, numbers, and hyphens only">
          </div>
          <div class="form-text">URL-friendly identifier (auto-generated if left empty)</div>
        </div>

        <div class="mb-3">
          <label class="form-label" for="description">Description</label>
          <textarea name="description" id="description" class="form-control" rows="3"
                    placeholder="Brief description of this page's purpose">{{ old('description', '') }}</textarea>
        </div>

        <div class="mb-3">
          <div class="form-check">
            <input type="checkbox" name="is_default" id="is_default" class="form-check-input" value="1"
                   {{ old('is_default') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_default">
              Set as default home page
            </label>
          </div>
          <div class="form-text">This page will be shown when visitors access the root URL</div>
        </div>

        <div class="mb-4">
          <div class="form-check">
            <input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
                   {{ old('is_active', '1') ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">
              Active (visible to public)
            </label>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg"></i> Create Page
          </button>
          <a href="{{ route('landing-page.list') }}" class="btn btn-outline-secondary">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    const slugInput = document.getElementById('slug');
    if (!slugInput.dataset.manual) {
        slugInput.value = this.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// Mark slug as manually edited
document.getElementById('slug').addEventListener('input', function() {
    this.dataset.manual = 'true';
});
</script>
@endsection
