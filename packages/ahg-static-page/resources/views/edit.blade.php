@extends('theme::layouts.1col')

@section('title', $page ? 'Edit ' . ($page->title ?? 'Untitled') : 'Add new page')
@section('body-class', 'edit staticpage')

@section('content')
  <h1>{{ $page ? 'Edit ' . ($page->title ?? 'Untitled') : 'Add new page' }}</h1>

  <form method="POST" action="{{ $page ? route('staticpage.update', $slug) : route('staticpage.store') }}">
    @csrf
    @if($page)
      @method('PUT')
    @endif

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-fields" aria-expanded="true">
            Page content
          </button>
        </h2>
        <div id="collapse-fields" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="title">Title <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $page->title ?? '') }}" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="slug">Slug <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $slug) }}" {{ (!empty($isProtected)) ? 'readonly' : '' }}>
              @if(!empty($isProtected))
                <div class="form-text">This is a protected page. The slug cannot be changed.</div>
              @endif
            </div>
            <div class="mb-3">
              <label class="form-label" for="content">Content <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea class="form-control" id="content" name="content" rows="15">{{ old('content', $page->content ?? '') }}</textarea>
              <div class="form-text">You can use HTML or Markdown (if enabled in settings).</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><input type="submit" class="btn atom-btn-outline-light" value="{{ $page ? 'Save' : 'Create' }}"></li>
        <li>
          @if($page)
            <a class="btn atom-btn-outline-light" href="{{ route('staticpage.show', $slug) }}">Cancel</a>
          @else
            <a class="btn atom-btn-outline-light" href="{{ route('staticpage.browse') }}">Cancel</a>
          @endif
        </li>
      </ul>
    </section>
  </form>
@endsection
