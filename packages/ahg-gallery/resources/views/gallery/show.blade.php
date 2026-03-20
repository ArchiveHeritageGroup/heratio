@extends('theme::layouts.3col')

@section('title', ($artwork->title ?? 'Gallery artwork'))
@section('body-class', 'view gallery')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR                                                  --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Gallery navigation --}}
  <div class="card mb-3">
    <div class="card-header fw-bold">
      <i class="fas fa-palette me-1"></i> Gallery
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('gallery.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-th me-1"></i> Browse artworks
      </a>
      <a href="{{ route('gallery.artists') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-users me-1"></i> Browse artists
      </a>
    </div>
  </div>

  {{-- Artist information (if linked) --}}
  @if($galleryArtist)
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-user me-1"></i> Artist
      </div>
      <div class="card-body p-2">
        <h6 class="mb-1">
          <a href="{{ route('gallery.artists.show', $galleryArtist->id) }}">{{ $galleryArtist->display_name }}</a>
        </h6>
        @if($galleryArtist->nationality)
          <p class="small text-muted mb-1">{{ $galleryArtist->nationality }}</p>
        @endif
        @if($galleryArtist->birth_date || $galleryArtist->death_date)
          <p class="small text-muted mb-1">
            {{ $galleryArtist->birth_date ?? '?' }} &ndash; {{ $galleryArtist->death_date ?? 'present' }}
          </p>
        @endif
        @if($galleryArtist->medium_specialty)
          <p class="small text-muted mb-0">{{ $galleryArtist->medium_specialty }}</p>
        @endif
      </div>
    </div>
  @endif

  @auth
    {{-- Management --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-cog me-1"></i> Actions
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('gallery.edit', $artwork->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-pencil-alt me-1"></i> Edit
        </a>
        <form action="{{ route('gallery.destroy', $artwork->slug) }}" method="POST"
              onsubmit="return confirm('Are you sure you want to delete this artwork?');">
          @csrf
          <button type="submit" class="list-group-item list-group-item-action small text-danger border-0 w-100 text-start">
            <i class="fas fa-trash me-1"></i> Delete
          </button>
        </form>
      </div>
    </div>
  @endauth

@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- ============================================================ --}}
@section('title-block')

  <h1 class="mb-2">
    @if($artwork->work_type)<span class="badge bg-secondary me-1">{{ $artwork->work_type }}</span>@endif
    {{ $artwork->title ?: '[Untitled]' }}
  </h1>

  @if(!empty($breadcrumbs))
    <nav aria-label="Hierarchy">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('gallery.browse') }}">Gallery</a></li>
        @foreach($breadcrumbs as $crumb)
          <li class="breadcrumb-item">
            <a href="{{ route('gallery.show', $crumb->slug) }}">{{ $crumb->title ?: '[Untitled]' }}</a>
          </li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">{{ $artwork->title ?: '[Untitled]' }}</li>
      </ol>
    </nav>
  @endif

  @auth
    @if($publicationStatus)
      <span class="badge {{ (isset($publicationStatusId) && $publicationStatusId == 159) ? 'bg-warning text-dark' : 'bg-info' }} mb-2">{{ $publicationStatus }}</span>
    @endif
  @endauth

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT                                                 --}}
{{-- ============================================================ --}}
@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Object/Work section --}}
  @if($artwork->work_type || $artwork->classification || $artwork->identifier)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Object/Work</h2>
      <div class="field-list">
        @if($artwork->work_type)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Work type</div>
            <div class="col-sm-8">{{ $artwork->work_type }}</div>
          </div>
        @endif
        @if($artwork->classification)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Classification</div>
            <div class="col-sm-8">{{ $artwork->classification }}</div>
          </div>
        @endif
        @if($artwork->identifier)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Identifier</div>
            <div class="col-sm-8">{{ $artwork->identifier }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Creator section --}}
  @if($artwork->creator_identity || $artwork->creator_role || $creators->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Creator</h2>
      <div class="field-list">
        @if($artwork->creator_identity)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Creator</div>
            <div class="col-sm-8">{{ $artwork->creator_identity }}</div>
          </div>
        @endif
        @if($artwork->creator_role)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Role</div>
            <div class="col-sm-8">{{ $artwork->creator_role }}</div>
          </div>
        @endif
        @foreach($creators as $creator)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Authority record</div>
            <div class="col-sm-8">
              <a href="/actor/{{ $creator->slug }}">{{ $creator->name }}</a>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Creation section --}}
  @if($artwork->creation_date_display || $artwork->creation_date_earliest || $artwork->creation_date_latest || $artwork->creation_place || $artwork->style || $artwork->period || $artwork->movement || $artwork->school)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Creation</h2>
      <div class="field-list">
        @if($artwork->creation_date_display)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Date</div>
            <div class="col-sm-8">{{ $artwork->creation_date_display }}</div>
          </div>
        @endif
        @if($artwork->creation_date_earliest)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Earliest date</div>
            <div class="col-sm-8">{{ $artwork->creation_date_earliest }}</div>
          </div>
        @endif
        @if($artwork->creation_date_latest)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Latest date</div>
            <div class="col-sm-8">{{ $artwork->creation_date_latest }}</div>
          </div>
        @endif
        @if($artwork->creation_place)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Place</div>
            <div class="col-sm-8">{{ $artwork->creation_place }}</div>
          </div>
        @endif
        @if($artwork->style)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Style</div>
            <div class="col-sm-8">{{ $artwork->style }}</div>
          </div>
        @endif
        @if($artwork->period)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Period</div>
            <div class="col-sm-8">{{ $artwork->period }}</div>
          </div>
        @endif
        @if($artwork->movement)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Movement</div>
            <div class="col-sm-8">{{ $artwork->movement }}</div>
          </div>
        @endif
        @if($artwork->school)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">School</div>
            <div class="col-sm-8">{{ $artwork->school }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Measurements section --}}
  @if($artwork->measurements || $artwork->dimensions)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Measurements</h2>
      <div class="field-list">
        @if($artwork->measurements)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Measurements</div>
            <div class="col-sm-8">{{ $artwork->measurements }}</div>
          </div>
        @endif
        @if($artwork->dimensions)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Dimensions</div>
            <div class="col-sm-8">{{ $artwork->dimensions }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Materials / Techniques section --}}
  @if($artwork->materials || $artwork->techniques)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Materials / Techniques</h2>
      <div class="field-list">
        @if($artwork->materials)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Materials</div>
            <div class="col-sm-8">{{ $artwork->materials }}</div>
          </div>
        @endif
        @if($artwork->techniques)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Techniques</div>
            <div class="col-sm-8">{{ $artwork->techniques }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Subject / Description section --}}
  @if($artwork->scope_and_content)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Subject</h2>
      <div class="field-list">
        <div class="row mb-1">
          <div class="col-sm-4 fw-bold">Description</div>
          <div class="col-sm-8">{!! nl2br(e($artwork->scope_and_content)) !!}</div>
        </div>
      </div>
    </section>
  @endif

  {{-- Inscriptions section --}}
  @if($artwork->inscription || $artwork->mark_description)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Inscriptions</h2>
      <div class="field-list">
        @if($artwork->inscription)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Inscription</div>
            <div class="col-sm-8">{!! nl2br(e($artwork->inscription)) !!}</div>
          </div>
        @endif
        @if($artwork->mark_description)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Marks</div>
            <div class="col-sm-8">{!! nl2br(e($artwork->mark_description)) !!}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Condition section --}}
  @if($artwork->condition_term || $artwork->condition_description)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Condition</h2>
      <div class="field-list">
        @if($artwork->condition_term)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Condition</div>
            <div class="col-sm-8">{{ $artwork->condition_term }}</div>
          </div>
        @endif
        @if($artwork->condition_description)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Condition notes</div>
            <div class="col-sm-8">{!! nl2br(e($artwork->condition_description)) !!}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Provenance section --}}
  @if($artwork->provenance || $artwork->current_location || $artwork->rights_type || $artwork->rights_holder)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Provenance</h2>
      <div class="field-list">
        @if($artwork->provenance)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Provenance</div>
            <div class="col-sm-8">{!! nl2br(e($artwork->provenance)) !!}</div>
          </div>
        @endif
        @if($artwork->current_location)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Current location</div>
            <div class="col-sm-8">{{ $artwork->current_location }}</div>
          </div>
        @endif
        @if($artwork->rights_type)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Rights type</div>
            <div class="col-sm-8">{{ $artwork->rights_type }}</div>
          </div>
        @endif
        @if($artwork->rights_holder)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Rights holder</div>
            <div class="col-sm-8">{{ $artwork->rights_holder }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Access points --}}
  @if($subjects->isNotEmpty() || $places->isNotEmpty() || $genres->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Access points</h2>
      <div class="field-list">
        @if($subjects->isNotEmpty())
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Subject access points</div>
            <div class="col-sm-8">
              @foreach($subjects as $subj)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $subj->name }}</span>
              @endforeach
            </div>
          </div>
        @endif
        @if($places->isNotEmpty())
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Place access points</div>
            <div class="col-sm-8">
              @foreach($places as $place)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $place->name }}</span>
              @endforeach
            </div>
          </div>
        @endif
        @if($genres->isNotEmpty())
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Genre access points</div>
            <div class="col-sm-8">
              @foreach($genres as $genre)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $genre->name }}</span>
              @endforeach
            </div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Cataloging section --}}
  @if($artwork->cataloger_name || $artwork->cataloging_date || $repository)
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Cataloging</h2>
      <div class="field-list">
        @if($artwork->cataloger_name)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Cataloger</div>
            <div class="col-sm-8">{{ $artwork->cataloger_name }}</div>
          </div>
        @endif
        @if($artwork->cataloging_date)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Cataloging date</div>
            <div class="col-sm-8">{{ $artwork->cataloging_date }}</div>
          </div>
        @endif
        @if($repository)
          <div class="row mb-1">
            <div class="col-sm-4 fw-bold">Repository</div>
            <div class="col-sm-8">
              <a href="/repository/{{ $repository->slug }}">{{ $repository->name }}</a>
            </div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Notes --}}
  @if($notes->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Notes</h2>
      @foreach($notes as $note)
        <div class="mb-2">
          @if(!empty($noteTypeNames[$note->type_id]))
            <strong>{{ $noteTypeNames[$note->type_id] }}</strong>
          @endif
          <p>{!! nl2br(e($note->content)) !!}</p>
        </div>
      @endforeach
    </section>
  @endif

  {{-- Physical storage --}}
  @if($physicalObjects->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Physical storage</h2>
      @foreach($physicalObjects as $po)
        <div class="mb-1">
          @if($po->name)<strong>{{ $po->name }}</strong>@endif
          @if($po->location) &mdash; {{ $po->location }}@endif
        </div>
      @endforeach
    </section>
  @endif

  {{-- Administration --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Administration</h2>
    <div class="field-list">
      @if($artwork->created_at)
        <div class="row mb-1">
          <div class="col-sm-4 fw-bold">Created</div>
          <div class="col-sm-8">{{ \Carbon\Carbon::parse($artwork->created_at)->format('Y-m-d H:i') }}</div>
        </div>
      @endif
      @if($artwork->updated_at)
        <div class="row mb-1">
          <div class="col-sm-4 fw-bold">Updated</div>
          <div class="col-sm-8">{{ \Carbon\Carbon::parse($artwork->updated_at)->format('Y-m-d H:i') }}</div>
        </div>
      @endif
    </div>
  </section>

@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Digital object / image                         --}}
{{-- ============================================================ --}}
@section('right')

  {{-- Digital object display --}}
  @if(!empty($digitalObjects['reference']))
    <div class="card mb-3">
      <div class="card-body p-2 text-center">
        <a href="{{ $digitalObjects['master'] ? '/uploads/' . $digitalObjects['master']->path . '/' . $digitalObjects['master']->name : '#' }}"
           target="_blank">
          <img src="/uploads/{{ $digitalObjects['reference']->path }}/{{ $digitalObjects['reference']->name }}"
               class="img-fluid" alt="{{ $artwork->title ?: 'Artwork image' }}">
        </a>
      </div>
    </div>
  @elseif(!empty($digitalObjects['thumbnail']))
    <div class="card mb-3">
      <div class="card-body p-2 text-center">
        <img src="/uploads/{{ $digitalObjects['thumbnail']->path }}/{{ $digitalObjects['thumbnail']->name }}"
             class="img-fluid" alt="{{ $artwork->title ?: 'Artwork image' }}">
      </div>
    </div>
  @endif

  {{-- Events / Dates --}}
  @if($events->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-calendar me-1"></i> Dates
      </div>
      <div class="list-group list-group-flush">
        @foreach($events as $event)
          <div class="list-group-item small">
            @if($event->date_display)
              {{ $event->date_display }}
            @elseif($event->start_date)
              {{ $event->start_date }}
              @if($event->end_date) &ndash; {{ $event->end_date }}@endif
            @endif
          </div>
        @endforeach
      </div>
    </div>
  @endif

@endsection
