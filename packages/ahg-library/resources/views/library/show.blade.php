@extends('theme::layouts.3col')

@section('title', $item->title ?? 'Library item')
@section('body-class', 'view library')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Holdings / navigation                          --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Library navigation --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
      <i class="fas fa-book me-1"></i> Library
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('library.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-list me-1"></i> Browse all items
      </a>
      @if($item->material_type)
        <a href="{{ route('library.browse', ['material_type' => $item->material_type]) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-filter me-1"></i> Same material type
        </a>
      @endif
    </div>
  </div>

  {{-- Creators --}}
  @if($creators->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-user me-1"></i> Creators
      </div>
      <div class="list-group list-group-flush">
        @foreach($creators as $creator)
          <a href="{{ $creator->slug ? route('actor.show', $creator->slug) : '#' }}" class="list-group-item list-group-item-action small">
            {{ $creator->name ?? '[Unknown]' }}
            @if($creator->role)
              <span class="badge bg-secondary float-end">{{ ucfirst($creator->role) }}</span>
            @endif
          </a>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Subjects --}}
  @if($subjects->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-tags me-1"></i> Subjects
      </div>
      <div class="list-group list-group-flush">
        @foreach($subjects as $subject)
          <span class="list-group-item small">{{ $subject->name ?? '[Unknown]' }}</span>
        @endforeach
      </div>
    </div>
  @endif

  {{-- External links --}}
  @if($item->openlibrary_url || $item->ebook_preview_url || $item->openlibrary_id || $item->goodreads_id || $item->librarything_id)
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-external-link-alt me-1"></i> External links
      </div>
      <div class="list-group list-group-flush">
        @if($item->openlibrary_url)
          <a href="{{ $item->openlibrary_url }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-open me-1"></i> OpenLibrary
          </a>
        @elseif($item->openlibrary_id)
          <a href="https://openlibrary.org/works/{{ $item->openlibrary_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-open me-1"></i> OpenLibrary
          </a>
        @endif
        @if($item->goodreads_id)
          <a href="https://www.goodreads.com/book/show/{{ $item->goodreads_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-star me-1"></i> Goodreads
          </a>
        @endif
        @if($item->librarything_id)
          <a href="https://www.librarything.com/work/{{ $item->librarything_id }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-bookmark me-1"></i> LibraryThing
          </a>
        @endif
        @if($item->ebook_preview_url)
          <a href="{{ $item->ebook_preview_url }}" target="_blank" class="list-group-item list-group-item-action small">
            <i class="fas fa-book-reader me-1"></i> E-book preview
          </a>
        @endif
      </div>
    </div>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: Field sections (card-based like AtoM)          --}}
{{-- ============================================================ --}}
@section('content')

  {{-- Title --}}
  <h1 class="mb-2">
    @if($item->material_type)<span class="badge bg-secondary me-2">{{ ucfirst($item->material_type) }}</span>@endif
    {{ $item->title ?: '[Untitled]' }}
  </h1>

  @if($item->subtitle)
    <p class="text-muted fs-5 mb-3">{{ $item->subtitle }}</p>
  @endif

  {{-- ===== Basic Information ===== --}}
  <section class="card mb-4">
    <div class="card-header text-white" style="background:var(--ahg-primary);">
      <h5 class="mb-0"><i class="fas fa-book me-2"></i>Basic information</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        @if($item->title)
          <dt class="col-sm-4">Title</dt>
          <dd class="col-sm-8">{{ $item->title }}</dd>
        @endif

        @if($item->subtitle)
          <dt class="col-sm-4">Subtitle</dt>
          <dd class="col-sm-8">{{ $item->subtitle }}</dd>
        @endif

        @if($item->responsibility_statement)
          <dt class="col-sm-4">Statement of responsibility</dt>
          <dd class="col-sm-8">{{ $item->responsibility_statement }}</dd>
        @endif

        @if($item->identifier)
          <dt class="col-sm-4">Identifier</dt>
          <dd class="col-sm-8">{{ $item->identifier }}</dd>
        @endif

        @if($levelName)
          <dt class="col-sm-4">Level of description</dt>
          <dd class="col-sm-8">{{ $levelName }}</dd>
        @endif

        @if($item->material_type)
          <dt class="col-sm-4">Material type</dt>
          <dd class="col-sm-8">{{ ucfirst($item->material_type) }}</dd>
        @endif

        @if($item->language)
          <dt class="col-sm-4">Language</dt>
          <dd class="col-sm-8">{{ $item->language }}</dd>
        @endif
      </dl>
    </div>
  </section>

  {{-- ===== Creators / Authors ===== --}}
  @if($creators->isNotEmpty())
    <section class="card mb-4">
      <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Creators / Authors</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          @foreach($creators as $creator)
            <li class="mb-2">
              <strong>{{ $creator->name ?? '[Unknown]' }}</strong>
              <span class="badge bg-secondary ms-2">{{ ucfirst($creator->role ?? 'Author') }}</span>
              @if(!empty($creator->authority_uri))
                <a href="{{ $creator->authority_uri }}" target="_blank" class="ms-2" title="View authority record">
                  <i class="fas fa-external-link-alt"></i>
                </a>
              @endif
            </li>
          @endforeach
        </ul>
      </div>
    </section>
  @endif

  {{-- ===== Standard Identifiers ===== --}}
  @if($item->isbn || $item->issn || $item->doi || $item->lccn || $item->oclc_number || $item->barcode || $item->openlibrary_id)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>Standard identifiers</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->isbn)
            <dt class="col-sm-4">ISBN</dt>
            <dd class="col-sm-8"><code>{{ $item->isbn }}</code></dd>
          @endif

          @if($item->issn)
            <dt class="col-sm-4">ISSN</dt>
            <dd class="col-sm-8"><code>{{ $item->issn }}</code></dd>
          @endif

          @if($item->doi)
            <dt class="col-sm-4">DOI</dt>
            <dd class="col-sm-8">
              <a href="https://doi.org/{{ $item->doi }}" target="_blank">{{ $item->doi }}</a>
            </dd>
          @endif

          @if($item->lccn)
            <dt class="col-sm-4">LCCN</dt>
            <dd class="col-sm-8">{{ $item->lccn }}</dd>
          @endif

          @if($item->oclc_number)
            <dt class="col-sm-4">OCLC</dt>
            <dd class="col-sm-8">
              <a href="https://www.worldcat.org/oclc/{{ $item->oclc_number }}" target="_blank">{{ $item->oclc_number }}</a>
            </dd>
          @endif

          @if($item->barcode)
            <dt class="col-sm-4">Barcode</dt>
            <dd class="col-sm-8"><code>{{ $item->barcode }}</code></dd>
          @endif

          @if($item->openlibrary_id)
            <dt class="col-sm-4">Open Library</dt>
            <dd class="col-sm-8">
              <a href="https://openlibrary.org/books/{{ $item->openlibrary_id }}" target="_blank">{{ $item->openlibrary_id }}</a>
            </dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- ===== Classification ===== --}}
  @if($item->call_number || $item->dewey_decimal || $item->shelf_location || $item->classification_scheme || $item->copy_number || $item->volume_designation || $item->classification_number || $item->cutter_number)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Classification</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->call_number)
            <dt class="col-sm-4">Call number</dt>
            <dd class="col-sm-8"><code>{{ $item->call_number }}</code></dd>
          @endif

          @if($item->dewey_decimal)
            <dt class="col-sm-4">Dewey Decimal</dt>
            <dd class="col-sm-8"><code>{{ $item->dewey_decimal }}</code></dd>
          @endif

          @if($item->classification_scheme)
            <dt class="col-sm-4">Classification scheme</dt>
            <dd class="col-sm-8">{{ strtoupper($item->classification_scheme) }}</dd>
          @endif

          @if($item->classification_number)
            <dt class="col-sm-4">Classification number</dt>
            <dd class="col-sm-8">{{ $item->classification_number }}</dd>
          @endif

          @if($item->cutter_number)
            <dt class="col-sm-4">Cutter number</dt>
            <dd class="col-sm-8">{{ $item->cutter_number }}</dd>
          @endif

          @if($item->shelf_location)
            <dt class="col-sm-4">Shelf location</dt>
            <dd class="col-sm-8">{{ $item->shelf_location }}</dd>
          @endif

          @if($item->copy_number)
            <dt class="col-sm-4">Copy</dt>
            <dd class="col-sm-8">{{ $item->copy_number }}</dd>
          @endif

          @if($item->volume_designation)
            <dt class="col-sm-4">Volume</dt>
            <dd class="col-sm-8">{{ $item->volume_designation }}</dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- ===== Publication ===== --}}
  @if($item->publisher || $item->publication_place || $item->publication_date || $item->edition || $item->edition_statement || $item->series_title)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Publication information</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->publisher)
            <dt class="col-sm-4">Publisher</dt>
            <dd class="col-sm-8">{{ $item->publisher }}</dd>
          @endif

          @if($item->publication_place)
            <dt class="col-sm-4">Place</dt>
            <dd class="col-sm-8">{{ $item->publication_place }}</dd>
          @endif

          @if($item->publication_date)
            <dt class="col-sm-4">Date</dt>
            <dd class="col-sm-8">{{ $item->publication_date }}</dd>
          @endif

          @if($item->edition)
            <dt class="col-sm-4">Edition</dt>
            <dd class="col-sm-8">{{ $item->edition }}</dd>
          @endif

          @if($item->edition_statement)
            <dt class="col-sm-4">Edition statement</dt>
            <dd class="col-sm-8">{{ $item->edition_statement }}</dd>
          @endif

          @if($item->series_title)
            <dt class="col-sm-4">Series</dt>
            <dd class="col-sm-8">
              {{ $item->series_title }}
              @if($item->series_number)
                <span class="text-muted">({{ $item->series_number }})</span>
              @endif
            </dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- ===== Physical Description ===== --}}
  @if($item->pages || $item->pagination || $item->dimensions || $item->physical_details)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-ruler me-2"></i>Physical description</h5>
      </div>
      <div class="card-body">
        <dl class="row mb-0">
          @if($item->pages || $item->pagination)
            <dt class="col-sm-4">Extent</dt>
            <dd class="col-sm-8">{{ $item->pagination ?: $item->pages }}</dd>
          @endif

          @if($item->dimensions)
            <dt class="col-sm-4">Dimensions</dt>
            <dd class="col-sm-8">{{ $item->dimensions }}</dd>
          @endif

          @if($item->physical_details)
            <dt class="col-sm-4">Physical details</dt>
            <dd class="col-sm-8">{{ $item->physical_details }}</dd>
          @endif
        </dl>
      </div>
    </section>
  @endif

  {{-- ===== Subjects ===== --}}
  @if($subjects->isNotEmpty())
    <section class="card mb-4">
      <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Subjects</h5>
      </div>
      <div class="card-body">
        @foreach($subjects as $subject)
          @if($subject->slug ?? null)
            <a href="{{ route('term.show', $subject->slug) }}" class="badge bg-secondary me-1 mb-1 text-decoration-none">{{ $subject->name ?? '[Unknown]' }}</a>
          @else
            <span class="badge bg-secondary me-1 mb-1">{{ $subject->name ?? '[Unknown]' }}</span>
          @endif
        @endforeach
      </div>
    </section>
  @endif

  {{-- ===== Content ===== --}}
  @php
    $summary = $item->summary ?? ($item->abstract ?? '');
    $scopeAndContent = $item->scope_and_content ?? '';
    $contentsNote = $item->table_of_contents ?? ($item->contents_note ?? '');
  @endphp
  @if(!empty($summary) || !empty($scopeAndContent) || !empty($contentsNote))
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-align-left me-2"></i>Content</h5>
      </div>
      <div class="card-body">
        @if(!empty($summary))
          <h6>Summary</h6>
          <p>{!! nl2br(e($summary)) !!}</p>
        @endif

        @if(!empty($scopeAndContent))
          <h6>Scope and content</h6>
          <p>{!! nl2br(e($scopeAndContent)) !!}</p>
        @endif

        @if(!empty($contentsNote))
          <h6>Table of contents</h6>
          <p>{!! nl2br(e($contentsNote)) !!}</p>
        @endif
      </div>
    </section>
  @endif

  {{-- ===== Notes ===== --}}
  @if($item->general_note || $item->bibliography_note)
    <section class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
      </div>
      <div class="card-body">
        @if($item->general_note)
          <p>{!! nl2br(e($item->general_note)) !!}</p>
        @endif

        @if($item->bibliography_note)
          <p class="text-muted"><em>{!! nl2br(e($item->bibliography_note)) !!}</em></p>
        @endif
      </div>
    </section>
  @endif

  {{-- ===== Administration ===== --}}
  <section class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Administration area</h5>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        @if($item->created_at)
          <dt class="col-sm-4">Created</dt>
          <dd class="col-sm-8">{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d H:i') }}</dd>
        @endif

        @if($item->updated_at)
          <dt class="col-sm-4">Updated</dt>
          <dd class="col-sm-8">{{ \Carbon\Carbon::parse($item->updated_at)->format('Y-m-d H:i') }}</dd>
        @endif
      </dl>
    </div>
  </section>

@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Cover image, Actions, Barcode, Related, etc.  --}}
{{-- ============================================================ --}}
@section('right')

  <nav>
    {{-- Cover image --}}
    @if($item->cover_url)
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
          <i class="fas fa-image me-1"></i> Cover
        </div>
        <div class="card-body text-center p-2">
          <a href="{{ $item->cover_url_original ?: $item->cover_url }}" target="_blank">
            <img src="{{ $item->cover_url }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:300px;">
          </a>
        </div>
      </div>
    @endif

    {{-- User Actions (compact) --}}
    <div class="d-flex flex-wrap gap-1 mb-3 align-items-center">
      {{-- Clipboard --}}
      @include('ahg-core::clipboard._button', ['slug' => $item->slug, 'type' => 'library', 'wide' => false])
      {{-- Print --}}
      <button type="button" class="btn btn-sm atom-btn-white" onclick="window.print();" title="Print this page" data-bs-toggle="tooltip">
        <i class="fas fa-print"></i>
      </button>
    </div>

    {{-- Actions (authenticated users) --}}
    @auth
    <div class="card mb-3">
      <div class="card-header fw-bold text-white" style="background:var(--ahg-primary);">
        <i class="fas fa-cog me-1"></i> Actions
      </div>
      <div class="card-body">
        <a href="{{ route('library.edit', $item->slug) }}" class="btn atom-btn-white w-100 mb-2">
          <i class="fas fa-edit me-2"></i>Edit
        </a>
        <a href="{{ route('library.create', ['parent' => $item->slug]) }}" class="btn atom-btn-white w-100 mb-2">
          <i class="fas fa-plus me-2"></i>Add new
        </a>
        <form action="{{ route('library.destroy', $item->slug) }}" method="POST"
              onsubmit="return confirm('Are you sure you want to delete this library item?');">
          @csrf
          <button type="submit" class="btn atom-btn-outline-danger w-100 mb-2">
            <i class="fas fa-trash me-2"></i>Delete
          </button>
        </form>
        <a href="{{ url('/' . $item->slug . '/default/move') }}" class="btn atom-btn-white w-100 mb-2">
          <i class="fas fa-arrows-alt me-2"></i>Move
        </a>
        <a href="{{ route('library.browse') }}" class="btn atom-btn-white w-100 mb-2">
          <i class="fas fa-list me-2"></i>Browse library
        </a>
        <div class="dropdown">
          <button type="button" class="btn atom-btn-white w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-ellipsis-h me-2"></i>More
          </button>
          <ul class="dropdown-menu dropdown-menu-end w-100">
            <li><a class="dropdown-item" href="{{ route('library.rename', $item->slug) }}"><i class="fas fa-i-cursor me-2"></i>Rename</a></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/informationobject/updatePublicationStatus') }}"><i class="fas fa-eye me-2"></i>Update publication status</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/object/editPhysicalObjects') }}"><i class="fas fa-box me-2"></i>Link physical storage</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/object/addDigitalObject') }}"><i class="fas fa-file-upload me-2"></i>Link digital object</a></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/digitalobject/edit') }}"><i class="fas fa-edit me-2"></i>Edit digital object</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/' . $item->slug . '/right/edit') }}"><i class="fas fa-copyright me-2"></i>Create new rights</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="{{ url('/label/' . $item->slug) }}"><i class="fas fa-tag me-2"></i>Generate label</a></li>
          </ul>
        </div>
      </div>
    </div>
    @endauth

    {{-- ISBN Barcode --}}
    @php
      $isbn = $item->isbn ?? '';
      $cleanIsbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
    @endphp
    @if(!empty($cleanIsbn))
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-barcode me-1"></i> ISBN Barcode
        </div>
        <div class="card-body text-center">
          <svg id="isbn-barcode"></svg>
          <p class="text-muted small mt-2 mb-0">{{ $isbn }}</p>
        </div>
      </div>
      <script src="/vendor/JsBarcode.all.min.js"></script>
      <script>
      document.addEventListener('DOMContentLoaded', function() {
        try {
          JsBarcode("#isbn-barcode", "{{ $cleanIsbn }}", {
            format: "CODE128",
            background: "#ffffff",
            lineColor: "#000000",
            width: 2,
            height: 60,
            displayValue: false,
            margin: 10
          });
        } catch(e) {
          document.getElementById('isbn-barcode').style.display = 'none';
        }
      });
      </script>
    @endif

    {{-- Related Records --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-link me-1"></i> Related records
      </div>
      <div class="card-body">
        @if($parentItem ?? null)
          <a href="{{ route('library.show', $parentItem->slug) }}" class="btn btn-outline-secondary w-100 mb-2">
            <i class="fas fa-level-up-alt me-2"></i>Parent record
          </a>
        @endif
        @if(($childCount ?? 0) > 0)
          <a href="{{ route('informationobject.browse', ['collection' => $item->id, 'topLod' => 0]) }}" class="btn btn-outline-secondary w-100">
            <i class="fas fa-sitemap me-2"></i>{{ $childCount }} child records
          </a>
        @endif
        @if(!($parentItem ?? null) && ($childCount ?? 0) === 0)
          <p class="text-muted small mb-0">No related records found.</p>
        @endif
      </div>
    </div>

    {{-- E-book Access --}}
    @if($item->ebook_preview_url)
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-tablet-alt me-1"></i> E-book Access
        </div>
        <div class="card-body">
          <a href="{{ $item->ebook_preview_url }}" target="_blank" class="btn atom-btn-white w-100">
            <i class="fas fa-book-reader me-2"></i>Preview on Archive.org
          </a>
        </div>
      </div>
    @endif

    {{-- External Links --}}
    @if($item->openlibrary_url || $item->goodreads_id || $item->librarything_id)
      <div class="card mb-3">
        <div class="card-header fw-bold">
          <i class="fas fa-external-link-alt me-1"></i> External Links
        </div>
        <div class="card-body">
          @if($item->openlibrary_url)
            <a href="{{ $item->openlibrary_url }}" target="_blank" class="btn btn-outline-secondary w-100 mb-2">
              <i class="fas fa-book me-2"></i>Open Library
            </a>
          @endif

          @if($item->goodreads_id)
            <a href="https://www.goodreads.com/book/show/{{ $item->goodreads_id }}" target="_blank" class="btn btn-outline-secondary w-100 mb-2">
              <i class="fas fa-star me-2"></i>Goodreads
            </a>
          @endif

          @if($item->librarything_id)
            <a href="https://www.librarything.com/work/{{ $item->librarything_id }}" target="_blank" class="btn btn-outline-secondary w-100">
              <i class="fas fa-bookmark me-2"></i>LibraryThing
            </a>
          @endif
        </div>
      </div>
    @endif

    {{-- Print --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-print me-1"></i> Print
      </div>
      <div class="list-group list-group-flush">
        <a href="javascript:window.print();" class="list-group-item list-group-item-action small">
          <i class="fas fa-print me-1"></i> Print this page
        </a>
      </div>
    </div>
  </nav>

@endsection

{{-- ============================================================ --}}
{{-- AFTER CONTENT: Actions bar                                   --}}
{{-- ============================================================ --}}
@section('after-content')
  @auth
  <section class="actions mb-3 nav gap-2">
    <li>
      <a href="{{ route('library.edit', $item->slug) }}" class="btn atom-btn-outline-light">Edit</a>
    </li>
    <li>
      <form action="{{ route('library.destroy', $item->slug) }}" method="POST"
            onsubmit="return confirm('Are you sure you want to delete this library item?');">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger">Delete</button>
      </form>
    </li>
    <li>
      <a href="{{ route('library.create') }}" class="btn atom-btn-outline-light">Add new</a>
    </li>
    <li>
      <a href="{{ url('/' . $item->slug . '/default/move') }}" class="btn atom-btn-outline-light">Move</a>
    </li>
    <li>
      <a href="{{ url('/' . $item->slug . '/object/addDigitalObject') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-upload me-1"></i>Add digital object
      </a>
    </li>
  </section>
  @endauth
@endsection
