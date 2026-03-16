@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      @if($term)
        Edit term
      @else
        Add new term
      @endif
    </h1>
    @if($term)
      <span class="small">{{ $term->name }}</span>
    @endif
  </div>
@endsection

@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $term ? route('term.update', $term->slug) : route('term.store') }}"
        id="editForm">
    @csrf
    @if($term)
      @method('PUT')
    @endif

    <div class="mb-3">
      @if($term)
        {{-- When editing, show taxonomy name as read-only --}}
        <label class="form-label">Taxonomy</label>
        <input type="text" class="form-control" value="{{ $taxonomyName }}" disabled>
      @else
        {{-- When creating, show taxonomy dropdown --}}
        <label for="taxonomy_id" class="form-label">
          Taxonomy <span class="form-required text-danger" title="This is a mandatory element.">*</span>
        </label>
        <select name="taxonomy_id" id="taxonomy_id" class="form-select" required>
          <option value="">-- Select --</option>
          @foreach($taxonomies as $taxonomy)
            <option value="{{ $taxonomy->id }}" @selected(old('taxonomy_id', $selectedTaxonomyId ?? '') == $taxonomy->id)>
              {{ $taxonomy->name }}
            </option>
          @endforeach
        </select>
      @endif
    </div>

    <div class="mb-3">
      <label for="name" class="form-label">
        Name <span class="form-required text-danger" title="This is a mandatory element.">*</span>
      </label>
      <input type="text" name="name" id="name" class="form-control" required
             value="{{ old('name', $term->name ?? '') }}">
    </div>

    <div class="mb-3">
      <label for="code" class="form-label">Code</label>
      <input type="text" name="code" id="code" class="form-control"
             value="{{ old('code', $term->code ?? '') }}">
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($term)
        <li><a href="{{ route('term.show', $term->slug) }}" class="btn btn-outline-secondary">Cancel</a></li>
        <li><input class="btn btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('taxonomy.browse') }}" class="btn btn-outline-secondary">Cancel</a></li>
        <li><input class="btn btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>

@endsection
