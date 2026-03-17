{{--
  GLAM Browser – browse.blade.php
  Migrated from AtoM browseSuccess.php (Display plugin)
--}}
@extends('theme::layouts.master')

@section('title', 'GLAM Browser')
@section('body-class', 'browse glam-browse')

@php
  // Type configuration map
  $typeConfig = [
      'archive' => ['icon' => 'fa-archive',  'color' => 'success', 'label' => 'Archive'],
      'museum'  => ['icon' => 'fa-landmark', 'color' => 'warning', 'label' => 'Museum'],
      'gallery' => ['icon' => 'fa-palette',  'color' => 'info',    'label' => 'Gallery'],
      'library' => ['icon' => 'fa-book',     'color' => 'primary', 'label' => 'Library'],
      'dam'     => ['icon' => 'fa-images',   'color' => 'danger',  'label' => 'Photo/DAM'],
  ];

  // Sort labels
  $sortLabels = [
      'date'       => 'Date modified',
      'title'      => 'Title',
      'identifier' => 'Identifier',
      'refcode'    => 'Reference code',
      'startdate'  => 'Start date',
      'enddate'    => 'End date',
  ];

  // Helper: build GLAM browse URL with filter params
  function glamBrowseUrl($fp, $add = [], $remove = []) {
      $params = array_merge(array_filter($fp, function($v) { return $v !== null && $v !== ''; }), $add);
      foreach ($remove as $key) { unset($params[$key]); }
      unset($params['page']);
      return route('glam.browse', $params);
  }

  // Current filter params (for URL building)
  $fp = $filterParams ?? [];

  // Pagination helpers
  $from = ($page - 1) * $limit + 1;
  $to   = min($page * $limit, $total);
@endphp

@section('layout-content')

{{-- Inline styles --}}
<style>
  .glam-filter-header { background-color: #1d6a52; color: #fff; }
  .cursor-pointer { cursor: pointer; }
  .facet-link { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
  .browse-table { width: 100%; table-layout: auto; }
  .browse-table th, .browse-table td { vertical-align: middle; }
  .col-thumb { width: 60px; min-width: 60px; }
  .col-identifier { width: 140px; }
  .col-level { width: 130px; }
  .col-actions { width: 80px; }
  .browse-thumb-lg { width: 56px; height: 56px; object-fit: cover; border-radius: 4px; }
  .browse-placeholder-lg { width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 4px; color: #adb5bd; font-size: 1.3rem; }
  .grid-img-wrapper { width: 100%; height: 160px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f8f9fa; }
  .grid-img { max-width: 100%; max-height: 160px; object-fit: contain; }
  .full-img-bg { width: 280px; min-width: 280px; height: 220px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 6px; }
  .full-img { max-width: 100%; max-height: 220px; object-fit: contain; }
  .card-img-browse { width: 80px; min-width: 80px; height: 80px; object-fit: cover; border-radius: 4px; }
  .browse-hidden { display: none; }

  /* Table resizable columns */
  .browse-table th { position: relative; }
  .browse-table th .resize-handle {
    position: absolute; right: 0; top: 0; bottom: 0; width: 5px;
    cursor: col-resize; background: transparent;
  }
  .browse-table th .resize-handle:hover { background: rgba(0,0,0,0.1); }
</style>

{{-- Advanced Search Panel (matching AtoM) --}}
@include('ahg-display::display._advanced-search')

@if(($viewMode ?? 'card') !== 'full')
  {{-- 2-column layout: sidebar + main --}}
  <div class="row">

    {{-- ========== SIDEBAR (LEFT COLUMN) ========== --}}
    <div class="col-md-3 col-lg-3 mb-3" id="glam-sidebar">

      {{-- Sidebar header --}}
      <div class="card mb-2">
        <div class="card-header glam-filter-header py-2 px-3">
          <strong><i class="fas fa-filter me-1"></i> Filters</strong>
        </div>
      </div>

      {{-- GLAM Type facet (open by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-type" aria-expanded="true">
          <i class="fas fa-caret-down me-1"></i> Type
        </div>
        <div id="facet-type" class="collapse show">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($typeFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['type']) }}" class="text-decoration-none {{ empty($typeFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($types))
              @foreach($types as $type)
                @php
                  $typeKey = $type->object_type ?? $type->name ?? '';
                  $tc = $typeConfig[$typeKey] ?? ['icon' => 'fa-question', 'color' => 'secondary', 'label' => ucfirst($typeKey)];
                @endphp
                <li class="list-group-item py-1 px-3 {{ ($typeFilter ?? '') === $typeKey ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['type' => $typeKey]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($typeFilter ?? '') === $typeKey ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $tc['label'] }}">
                      <i class="fas {{ $tc['icon'] }} me-1 text-{{ $tc['color'] }}"></i> {{ $tc['label'] }}
                    </span>
                    <span class="badge bg-{{ $tc['color'] }} rounded-pill">{{ number_format($type->count) }}</span>
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Creator facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-creator" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Creator
        </div>
        <div id="facet-creator" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($creatorFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['creator']) }}" class="text-decoration-none {{ empty($creatorFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($creators))
              @foreach($creators as $creator)
                <li class="list-group-item py-1 px-3 {{ ($creatorFilter ?? '') == $creator->id ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['creator' => $creator->id]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($creatorFilter ?? '') == $creator->id ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $creator->name }}">{{ $creator->name }}</span>
                    <span class="badge bg-secondary rounded-pill">{{ number_format($creator->count) }}</span>
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Place facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-place" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Place
        </div>
        <div id="facet-place" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($placeFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['place']) }}" class="text-decoration-none {{ empty($placeFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($places))
              @foreach($places as $place)
                <li class="list-group-item py-1 px-3 {{ ($placeFilter ?? '') == $place->id ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['place' => $place->id]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($placeFilter ?? '') == $place->id ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $place->name }}">{{ $place->name }}</span>
                    <span class="badge bg-secondary rounded-pill">{{ number_format($place->count) }}</span>
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Subject facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-subject" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Subject
        </div>
        <div id="facet-subject" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($subjectFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['subject']) }}" class="text-decoration-none {{ empty($subjectFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($subjects))
              @foreach($subjects as $subject)
                <li class="list-group-item py-1 px-3 {{ ($subjectFilter ?? '') == $subject->id ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['subject' => $subject->id]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($subjectFilter ?? '') == $subject->id ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $subject->name }}">{{ $subject->name }}</span>
                    <span class="badge bg-secondary rounded-pill">{{ number_format($subject->count) }}</span>
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Genre facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-genre" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Genre
        </div>
        <div id="facet-genre" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($genreFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['genre']) }}" class="text-decoration-none {{ empty($genreFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($genres))
              @foreach($genres as $genre)
                <li class="list-group-item py-1 px-3 {{ ($genreFilter ?? '') == $genre->id ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['genre' => $genre->id]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($genreFilter ?? '') == $genre->id ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $genre->name }}">{{ $genre->name }}</span>
                    <span class="badge bg-secondary rounded-pill">{{ number_format($genre->count) }}</span>
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Level of description facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-level" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Level of description
        </div>
        <div id="facet-level" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($levelFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['level']) }}" class="text-decoration-none {{ empty($levelFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($levels))
              @foreach($levels as $level)
                <li class="list-group-item py-1 px-3 {{ ($levelFilter ?? '') == $level->id ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['level' => $level->id]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($levelFilter ?? '') == $level->id ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $level->name }}">{{ $level->name }}</span>
                    @if($level->count)
                      <span class="badge bg-secondary rounded-pill">{{ number_format($level->count) }}</span>
                    @endif
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Media type facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-media" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Media type
        </div>
        <div id="facet-media" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($mediaFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['media']) }}" class="text-decoration-none {{ empty($mediaFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($mediaTypes))
              @foreach($mediaTypes as $mt)
                <li class="list-group-item py-1 px-3 {{ ($mediaFilter ?? '') == $mt->media_type ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['media' => $mt->media_type]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($mediaFilter ?? '') == $mt->media_type ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $mt->media_type }}">{{ $mt->media_type }}</span>
                    @if($mt->count)
                      <span class="badge bg-secondary rounded-pill">{{ number_format($mt->count) }}</span>
                    @endif
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

      {{-- Repository facet (closed by default) --}}
      <div class="card mb-2">
        <div class="card-header py-2 px-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#facet-repo" aria-expanded="false">
          <i class="fas fa-caret-right me-1"></i> Repository
        </div>
        <div id="facet-repo" class="collapse">
          <ul class="list-group list-group-flush">
            <li class="list-group-item py-1 px-3 {{ empty($repoFilter) ? 'active' : '' }}">
              <a href="{{ glamBrowseUrl($fp, [], ['repo']) }}" class="text-decoration-none {{ empty($repoFilter) ? 'text-white' : '' }}">
                All
              </a>
            </li>
            @if(!empty($repositories))
              @foreach($repositories as $repo)
                <li class="list-group-item py-1 px-3 {{ ($repoFilter ?? '') == $repo->id ? 'active' : '' }}">
                  <a href="{{ glamBrowseUrl($fp, ['repo' => $repo->id]) }}"
                     class="text-decoration-none d-flex justify-content-between align-items-center {{ ($repoFilter ?? '') == $repo->id ? 'text-white' : '' }}">
                    <span class="facet-link" title="{{ $repo->name }}">{{ $repo->name }}</span>
                    @if($repo->count)
                      <span class="badge bg-secondary rounded-pill">{{ number_format($repo->count) }}</span>
                    @endif
                  </a>
                </li>
              @endforeach
            @endif
          </ul>
        </div>
      </div>

    </div>{{-- /sidebar --}}

    {{-- ========== MAIN CONTENT (RIGHT COLUMN) ========== --}}
    <div class="col-md-9 col-lg-9" id="glam-main">
      @include('ahg-display::display._browse_content')
    </div>

  </div>{{-- /row --}}

@else
  {{-- Full width (1-column) layout --}}
  <div class="row">
    <div class="col-12" id="glam-main">
      @include('ahg-display::display._browse_content')
    </div>
  </div>
@endif

{{-- ========== SEMANTIC SEARCH MODAL ========== --}}
<div class="modal fade" id="semanticSearchModal" tabindex="-1" aria-labelledby="semanticSearchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header glam-filter-header">
        <h5 class="modal-title" id="semanticSearchModalLabel">
          <i class="fas fa-brain me-2"></i> Semantic Search
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="GET" action="{{ route('glam.browse') }}">
        {{-- Preserve existing filter params --}}
        @foreach($fp as $key => $val)
          @if($key !== 'query' && $key !== 'semantic' && $key !== 'page' && $val !== null && $val !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $val }}">
          @endif
        @endforeach
        <div class="modal-body">
          <div class="mb-3">
            <label for="semantic-query" class="form-label fw-bold">Search query</label>
            <input type="text" class="form-control form-control-lg" id="semantic-query" name="query"
                   value="{{ $queryFilter ?? '' }}" placeholder="Enter your search terms..."
                   autofocus>
          </div>
          <div class="mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" role="switch" id="semantic-toggle" name="semantic" value="1"
                     {{ !empty($fp['semantic']) ? 'checked' : '' }}>
              <label class="form-check-label" for="semantic-toggle">
                <i class="fas fa-brain me-1"></i> Enable AI-powered semantic expansion
              </label>
            </div>
            <small class="text-muted d-block mt-1">
              Semantic search uses AI to understand meaning, expanding your query with synonyms, related terms, and named entities.
            </small>
          </div>
          <div id="semantic-expansion-preview" class="browse-hidden">
            <label class="form-label fw-bold">Expansion preview</label>
            <div class="border rounded p-3 bg-light">
              <div id="semantic-preview-content">
                <span class="text-muted">Enter a query and enable semantic search to see expansion preview...</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">
            <i class="fas fa-search me-1"></i> Search
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ========== TABLE COLUMN RESIZE SCRIPT ========== --}}
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Toggle caret direction on facet collapse/expand
  document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(el) {
    var target = document.querySelector(el.getAttribute('data-bs-target'));
    if (target) {
      target.addEventListener('show.bs.collapse', function() {
        var icon = el.querySelector('.fas');
        if (icon) { icon.classList.replace('fa-caret-right', 'fa-caret-down'); }
      });
      target.addEventListener('hide.bs.collapse', function() {
        var icon = el.querySelector('.fas');
        if (icon) { icon.classList.replace('fa-caret-down', 'fa-caret-right'); }
      });
    }
  });

  // Semantic modal: toggle preview visibility
  var semToggle = document.getElementById('semantic-toggle');
  var semPreview = document.getElementById('semantic-expansion-preview');
  if (semToggle && semPreview) {
    semToggle.addEventListener('change', function() {
      semPreview.classList.toggle('browse-hidden', !this.checked);
    });
    if (semToggle.checked) { semPreview.classList.remove('browse-hidden'); }
  }

  // Table column resize
  document.querySelectorAll('.browse-table th .resize-handle').forEach(function(handle) {
    var startX, startWidth, th;
    handle.addEventListener('mousedown', function(e) {
      th = handle.parentElement;
      startX = e.pageX;
      startWidth = th.offsetWidth;
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup', onMouseUp);
      e.preventDefault();
    });
    function onMouseMove(e) {
      if (th) {
        th.style.width = (startWidth + e.pageX - startX) + 'px';
      }
    }
    function onMouseUp() {
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup', onMouseUp);
      th = null;
    }
  });
});
</script>
@endpush

@endsection
