@extends('theme::layouts.1col')

@section('title', 'Advanced search')
@section('body-class', 'search advanced-search')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-4">
    <i class="fas fa-3x fa-search-plus me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Advanced search</h1>
      <span class="small text-muted">Search archival descriptions with filters</span>
    </div>
  </div>

  <form action="{{ route('search') }}" method="get">
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong>Search criteria</strong>
      </div>
      <div class="card-body">

        {{-- Text query --}}
        <div class="mb-3">
          <label for="adv-query" class="form-label fw-semibold">Search terms <span class="badge bg-secondary ms-1">Optional</span></label>
          <input
            type="text"
            id="adv-query"
            name="q"
            class="form-control form-control-lg"
            placeholder="Enter keywords..."
            value="{{ $query }}"
            autocomplete="off"
          >
          <div class="form-text">
            Searches title, scope and content, identifier, reference code, and creator names.
          </div>
        </div>

        <div class="row">
          {{-- Repository --}}
          <div class="col-md-6 mb-3">
            <label for="adv-repository" class="form-label fw-semibold">Repository <span class="badge bg-warning ms-1">Recommended</span></label>
            <select id="adv-repository" name="repository" class="form-select">
              <option value="">-- Any repository --</option>
              @foreach($repositories as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>

          {{-- Level of description --}}
          <div class="col-md-6 mb-3">
            <label for="adv-level" class="form-label fw-semibold">Level of description <span class="badge bg-secondary ms-1">Optional</span></label>
            <select id="adv-level" name="level" class="form-select">
              <option value="">-- Any level --</option>
              @foreach($levels as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="row">
          {{-- Date from --}}
          <div class="col-md-3 mb-3">
            <label for="adv-dateFrom" class="form-label fw-semibold">Date from <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" id="adv-dateFrom" name="dateFrom" class="form-control">
          </div>

          {{-- Date to --}}
          <div class="col-md-3 mb-3">
            <label for="adv-dateTo" class="form-label fw-semibold">Date to <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" id="adv-dateTo" name="dateTo" class="form-control">
          </div>

          {{-- Media type --}}
          <div class="col-md-3 mb-3">
            <label for="adv-mediaType" class="form-label fw-semibold">Media type <span class="badge bg-secondary ms-1">Optional</span></label>
            <select id="adv-mediaType" name="mediaType" class="form-select">
              <option value="">-- Any type --</option>
              @foreach($mediaTypes as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>

          {{-- Sort --}}
          <div class="col-md-3 mb-3">
            <label for="adv-sort" class="form-label fw-semibold">Sort by <span class="badge bg-secondary ms-1">Optional</span></label>
            <select id="adv-sort" name="sort" class="form-select">
              <option value="relevance" {{ $sort === 'relevance' ? 'selected' : '' }}>Relevance</option>
              <option value="titleAsc" {{ $sort === 'titleAsc' ? 'selected' : '' }}>Title (A-Z)</option>
              <option value="titleDesc" {{ $sort === 'titleDesc' ? 'selected' : '' }}>Title (Z-A)</option>
              <option value="dateDesc" {{ $sort === 'dateDesc' ? 'selected' : '' }}>Date (newest first)</option>
              <option value="dateAsc" {{ $sort === 'dateAsc' ? 'selected' : '' }}>Date (oldest first)</option>
              <option value="identifierAsc" {{ $sort === 'identifierAsc' ? 'selected' : '' }}>Identifier (A-Z)</option>
              <option value="lastUpdated" {{ $sort === 'lastUpdated' ? 'selected' : '' }}>Last updated</option>
            </select>
          </div>
        </div>

        {{-- Has digital object --}}
        <div class="mb-3">
          <div class="form-check">
            <input type="checkbox" id="adv-hasDo" name="hasDigitalObject" value="1" class="form-check-input">
            <label for="adv-hasDo" class="form-check-label">Only show descriptions with digital objects</label>
          </div>
        </div>

      </div>

      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn atom-btn-outline-success">
          <i class="fas fa-search" aria-hidden="true"></i>
          Search
        </button>
        <a href="{{ route('search.advanced') }}" class="btn atom-btn-white">
          <i class="fas fa-undo" aria-hidden="true"></i>
          Reset
        </a>
        <a href="{{ route('search') }}" class="btn atom-btn-white ms-auto">
          <i class="fas fa-arrow-left" aria-hidden="true"></i>
          Back to simple search
        </a>
      </div>
    </div>
  </form>
@endsection
