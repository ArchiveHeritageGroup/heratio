@extends('theme::layouts.1col')

@section('title', $page ? __('Edit page') : __('Add new page'))
@section('body-class', 'edit staticpage')

@section('content')

  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $page ? __('Edit page') : __('Add new page') }}
    </h1>
    @if($page)
      <span class="small" id="heading-label">
        {{ $page->title ?? __('Untitled') }}
      </span>
    @endif
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="POST" action="{{ $page ? route('staticpage.update', $slug) : route('staticpage.store') }}">
    @csrf
    @if($page)
      @method('PUT')
    @endif

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="elements-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#elements-collapse" aria-expanded="true" aria-controls="elements-collapse">
            {{ __('Elements area') }}
          </button>
        </h2>
        <div id="elements-collapse" class="accordion-collapse collapse show" aria-labelledby="elements-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="title">{{ __('Title') }}</label>
              <input type="text" class="form-control" id="title" name="title" value="{{ old('title', $page->title ?? '') }}" required>
            </div>

            <div class="mb-3">
              <label class="form-label" for="slug">{{ __('Slug') }}</label>
              @if(!empty($isProtected))
                <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $slug) }}" disabled>
              @else
                <input type="text" class="form-control" id="slug" name="slug" value="{{ old('slug', $slug) }}" pattern="^[a-zA-Z0-9\-_]+$">
              @endif
            </div>

            <div class="mb-3">
              <label class="form-label" for="content">{{ __('Content') }}</label>
              <textarea class="form-control" id="content" name="content" rows="15">{{ old('content', $page->content ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($page)
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('staticpage.show', $slug) }}">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}"></li>
      @else
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('staticpage.browse') }}">{{ __('Cancel') }}</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Create') }}"></li>
      @endif
    </ul>

  </form>

@endsection
