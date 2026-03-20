@extends('theme::layouts.3col')

@section('title', $item->title ?? 'Library item')
@section('body-class', 'view library')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Related items                                  --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Library navigation --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">
      <i class="fas fa-book me-1"></i> Library
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('library.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-list me-1"></i> Browse all items
      </a>
      <a href="{{ route('library.browse', ['material_type' => $item->material_type]) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-filter me-1"></i> Same material type
      </a>
    </div>
  </div>

  {{-- Creators --}}
  @if($creators->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-user me-1"></i> Creators
      </div>
      <div class="list-group list-group-flush">
        @foreach($creators as $creator)
          <a href="{{ $creator->slug ? route('actor.show', $creator->slug) : '#' }}" class="list-group-item list-group-item-action small">
            {{ $creator->name ?? '[Unknown]' }}
            @if($creator->role)
              <span class="badge bg-secondary float-end">{{ $creator->role }}</span>
            @endif
          </a>
        @endforeach
      </div>
    </div>
  @endif

  {{-- Subjects --}}
  @if($subjects->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold">
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
      <div class="card-header fw-bold">
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
            <i class="fas fa-eye me-1"></i> E-book preview
          </a>
        @endif
      </div>
    </div>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT: Field sections                                 --}}
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
  <section id="basicInfoArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#basic-collapse">
        Basic information
      </a>
      @auth
        <a href="{{ route('library.edit', $item->slug) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="basic-collapse">

      @if($item->title)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Title</h3>
          <div class="col-9 p-2">{{ $item->title }}</div>
        </div>
      @endif

      @if($item->subtitle)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subtitle</h3>
          <div class="col-9 p-2">{{ $item->subtitle }}</div>
        </div>
      @endif

      @if($item->identifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Identifier</h3>
          <div class="col-9 p-2">{{ $item->identifier }}</div>
        </div>
      @endif

      @if($item->responsibility_statement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Statement of responsibility</h3>
          <div class="col-9 p-2">{{ $item->responsibility_statement }}</div>
        </div>
      @endif

      @if($levelName)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of description</h3>
          <div class="col-9 p-2">{{ $levelName }}</div>
        </div>
      @endif

      @if($item->material_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Material type</h3>
          <div class="col-9 p-2">{{ ucfirst($item->material_type) }}</div>
        </div>
      @endif

      @if($item->language)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Language</h3>
          <div class="col-9 p-2">{{ $item->language }}</div>
        </div>
      @endif

    </div>
  </section>

  {{-- ===== Standard Identifiers ===== --}}
  @if($item->isbn || $item->issn || $item->doi || $item->lccn || $item->oclc_number || $item->barcode || $item->openlibrary_id || $item->goodreads_id || $item->librarything_id)
    <section id="identifiersArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#identifiers-collapse">
          Standard identifiers
        </a>
      </h2>
      <div id="identifiers-collapse">

        @foreach([
          'isbn' => 'ISBN',
          'issn' => 'ISSN',
          'doi' => 'DOI',
          'lccn' => 'LCCN',
          'oclc_number' => 'OCLC number',
          'barcode' => 'Barcode',
          'openlibrary_id' => 'OpenLibrary ID',
          'goodreads_id' => 'Goodreads ID',
          'librarything_id' => 'LibraryThing ID',
        ] as $field => $label)
          @if($item->$field)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $label }}</h3>
              <div class="col-9 p-2">{{ $item->$field }}</div>
            </div>
          @endif
        @endforeach

        @if($item->openlibrary_url)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">OpenLibrary URL</h3>
            <div class="col-9 p-2"><a href="{{ $item->openlibrary_url }}" target="_blank">{{ $item->openlibrary_url }}</a></div>
          </div>
        @endif

      </div>
    </section>
  @endif

  {{-- ===== Classification ===== --}}
  @if($item->classification_scheme || $item->call_number || $item->dewey_decimal || $item->cutter_number || $item->shelf_location || $item->copy_number || $item->volume_designation)
    <section id="classificationArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#classification-collapse">
          Classification
        </a>
      </h2>
      <div id="classification-collapse">

        @foreach([
          'classification_scheme' => 'Classification scheme',
          'call_number' => 'Call number',
          'classification_number' => 'Classification number',
          'dewey_decimal' => 'Dewey Decimal number',
          'cutter_number' => 'Cutter number',
          'shelf_location' => 'Shelf location',
          'copy_number' => 'Copy number',
          'volume_designation' => 'Volume designation',
        ] as $field => $label)
          @if($item->$field)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $label }}</h3>
              <div class="col-9 p-2">{{ $item->$field }}</div>
            </div>
          @endif
        @endforeach

      </div>
    </section>
  @endif

  {{-- ===== Publication ===== --}}
  @if($item->publisher || $item->publication_place || $item->publication_date || $item->edition || $item->edition_statement || $item->series_title || $item->series_number)
    <section id="publicationArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#publication-collapse">
          Publication
        </a>
      </h2>
      <div id="publication-collapse">

        @foreach([
          'publisher' => 'Publisher',
          'publication_place' => 'Place of publication',
          'publication_date' => 'Date of publication',
          'edition' => 'Edition',
          'edition_statement' => 'Edition statement',
          'series_title' => 'Series title',
          'series_number' => 'Series number',
        ] as $field => $label)
          @if($item->$field)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $label }}</h3>
              <div class="col-9 p-2">{{ $item->$field }}</div>
            </div>
          @endif
        @endforeach

      </div>
    </section>
  @endif

  {{-- ===== Physical Description ===== --}}
  @if($item->pages || $item->dimensions || $item->physical_details)
    <section id="physicalArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#physical-collapse">
          Physical description
        </a>
      </h2>
      <div id="physical-collapse">

        @foreach([
          'pages' => 'Pages / Extent',
          'dimensions' => 'Dimensions',
          'physical_details' => 'Physical details',
        ] as $field => $label)
          @if($item->$field)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $label }}</h3>
              <div class="col-9 p-2">{{ $item->$field }}</div>
            </div>
          @endif
        @endforeach

      </div>
    </section>
  @endif

  {{-- ===== Content ===== --}}
  @if($item->scope_and_content || $item->abstract || $item->table_of_contents || $item->general_note || $item->bibliography_note)
    <section id="contentArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#content-show-collapse">
          Content
        </a>
      </h2>
      <div id="content-show-collapse">

        @if($item->scope_and_content || $item->abstract)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Abstract</h3>
            <div class="col-9 p-2">{!! nl2br(e($item->abstract ?: $item->scope_and_content)) !!}</div>
          </div>
        @endif

        @if($item->table_of_contents)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Table of contents</h3>
            <div class="col-9 p-2">{!! nl2br(e($item->table_of_contents)) !!}</div>
          </div>
        @endif

        @if($item->general_note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">General note</h3>
            <div class="col-9 p-2">{!! nl2br(e($item->general_note)) !!}</div>
          </div>
        @endif

        @if($item->bibliography_note)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Bibliography note</h3>
            <div class="col-9 p-2">{!! nl2br(e($item->bibliography_note)) !!}</div>
          </div>
        @endif

      </div>
    </section>
  @endif

  {{-- ===== Administration ===== --}}
  <section id="adminArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#admin-collapse">
        Administration area
      </a>
    </h2>
    <div id="admin-collapse">

      @if($item->created_at)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Created</h3>
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d H:i') }}</div>
        </div>
      @endif

      @if($item->updated_at)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Updated</h3>
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($item->updated_at)->format('Y-m-d H:i') }}</div>
        </div>
      @endif

    </div>
  </section>

@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Cover image, Print, Clipboard                 --}}
{{-- ============================================================ --}}
@section('right')

  <nav>
    {{-- Cover image --}}
    @if($item->cover_url)
      <div class="card mb-3">
        <div class="card-body text-center p-2">
          <a href="{{ $item->cover_url_original ?: $item->cover_url }}" target="_blank">
            <img src="{{ $item->cover_url }}" alt="{{ $item->title }}" class="img-fluid img-thumbnail" style="max-height:300px;">
          </a>
        </div>
      </div>
    @endif

    {{-- Clipboard --}}
    <div class="mb-3">
      @include('ahg-core::clipboard._button', ['slug' => $item->slug, 'type' => 'library', 'wide' => true])
    </div>

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

    {{-- Explore --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-cogs me-1"></i> Explore
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('library.browse') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list me-1"></i> Browse all library items
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
  <ul class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
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
  </ul>
  @endauth
@endsection
