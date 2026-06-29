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
        <i class="fas fa-th me-1"></i> {{ __('Browse artworks') }}
      </a>
      <a href="{{ route('gallery.artists') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-users me-1"></i> {{ __('Browse artists') }}
      </a>
      {{-- Provenance (sector-neutral; works for any catalogue record) --}}
      @if(class_exists(\AhgInformationObjectManage\Controllers\ProvenanceController::class) && !empty($artwork->slug))
      <a href="{{ route('io.provenance', $artwork->slug) }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-project-diagram me-1"></i> {{ __('Provenance') }}
      </a>
      @endif
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
    @php
      $canUpdate = \AhgCore\Services\AclService::check($artwork, 'update');
      $canDelete = \AhgCore\Services\AclService::check($artwork, 'delete');
    @endphp
    @if($canUpdate || $canDelete)
    {{-- Management --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-cog me-1"></i> Actions
      </div>
      <div class="list-group list-group-flush">
        @if($canUpdate)
        <a href="{{ route('gallery.edit', $artwork->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-pencil-alt me-1"></i> {{ __('Edit') }}
        </a>
        @endif
        @if($canDelete)
        <form action="{{ route('gallery.destroy', $artwork->slug) }}" method="POST"
              onsubmit="return confirm('Are you sure you want to delete this artwork?');">
          @csrf
          <button type="submit" class="list-group-item list-group-item-action small text-danger border-0 w-100 text-start">
            <i class="fas fa-trash me-1"></i> {{ __('Delete') }}
          </button>
        </form>
        @endif
        @if($canUpdate)
        @if(!empty($digitalObjects['reference']) || !empty($digitalObjects['thumbnail']))
          <a href="{{ url('/' . $artwork->slug . '/digitalobject/edit') }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-edit me-1"></i> {{ __('Edit digital object') }}
          </a>
          @if($canDelete)
          <a href="{{ url('/' . $artwork->slug . '/digitalobject/delete') }}" class="list-group-item list-group-item-action small text-danger">
            <i class="fas fa-times-circle me-1"></i> {{ __('Delete digital object') }}
          </a>
          @endif
        @else
          <a href="{{ route('io.digitalobject.add', $artwork->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-link me-1"></i> {{ __('Link digital object') }}
          </a>
        @endif
        <a href="{{ url('/' . $artwork->slug . '/right/edit') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-gavel me-1"></i> {{ __('Edit rights') }}
        </a>
        @if(\Illuminate\Support\Facades\Route::has('ahgtranslation.translate')
            && \AhgCore\Services\AclService::check($artwork, 'translate'))
          <a class="list-group-item list-group-item-action small" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateSbsModal-{{ $artwork->id }}">
            <i class="fas fa-columns me-1"></i>{{ __('Translate (side-by-side)') }}
          </a>
          @if(\Illuminate\Support\Facades\Schema::hasTable('museum_metadata') && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $artwork->id)->exists())
            <a class="list-group-item list-group-item-action small text-warning" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateCcoValuesModal-{{ $artwork->id }}">
              <i class="fas fa-landmark me-1"></i>{{ __('Translate field data values (CCO)') }}
            </a>
          @endif
        @endif
        @endif
      </div>
    </div>
    @endif {{-- end $canUpdate || $canDelete management card --}}

    {{-- Translate modal — side-by-side per-field translator + CCO values --}}
    @if(view()->exists('ahg-translation::_translate-sbs') && \AhgCore\Services\AclService::check($artwork, 'translate'))
      @include('ahg-translation::_translate-sbs', ['objectId' => $artwork->id])
      @if(\Illuminate\Support\Facades\Schema::hasTable('museum_metadata') && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $artwork->id)->exists())
        @include('ahg-translation::_translate-cco-values', ['objectId' => $artwork->id])
      @endif
    @endif

    {{-- Marketplace (admin) --}}
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-store me-1"></i> {{ __('Marketplace') }}
      </div>
      @if($marketplaceListing ?? null)
        <div class="card-body p-2 small">
          @if($marketplaceListing->price_on_request)
            <div><span class="text-muted">{{ __('Price:') }}</span> <strong>{{ __('On request') }}</strong></div>
          @elseif($marketplaceListing->price !== null)
            <div><span class="text-muted">{{ __('Price:') }}</span>
              <strong>{{ $marketplaceListing->currency ?: 'ZAR' }} {{ number_format((float) $marketplaceListing->price, 2) }}</strong>
            </div>
          @else
            <div class="text-muted fst-italic">Price not set</div>
          @endif
          <div><span class="text-muted">{{ __('Type:') }}</span> {{ str_replace('_', ' ', $marketplaceListing->listing_type) }}</div>
          <div><span class="text-muted">{{ __('Status:') }}</span> <span class="badge bg-secondary">{{ $marketplaceListing->status }}</span></div>
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('ahgmarketplace.seller-listing-edit', ['id' => $marketplaceListing->id]) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-edit me-1"></i> {{ __('Edit listing &amp; price') }}
          </a>
          <a href="{{ route('ahgmarketplace.seller-listing-images', ['id' => $marketplaceListing->id]) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-images me-1"></i> {{ __('Manage images') }}
          </a>
          @if($marketplaceListing->status === 'published')
            <a href="{{ route('ahgmarketplace.listing', ['slug' => $marketplaceListing->slug]) }}" class="list-group-item list-group-item-action small" target="_blank">
              <i class="fas fa-external-link-alt me-1"></i> {{ __('View public listing') }}
            </a>
          @endif
        </div>
      @else
        <div class="list-group list-group-flush">
          <a href="{{ route('ahgmarketplace.seller-listing-create', ['io' => $artwork->id]) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-tag me-1"></i> {{ __('Add to marketplace &amp; set price') }}
          </a>
        </div>
      @endif
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
    {{-- ICIP cultural-sensitivity badge (issue #36 Phase 2b). --}}
    @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $artwork->icip_sensitivity ?? null])
  </h1>

  @if(!empty($breadcrumbs))
    <nav aria-label="{{ __('Hierarchy') }}">
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

  @include('ahg-ric::_view-switch', ['standard' => 'CDWA'])
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-gallery', ['artwork' => $artwork])
  @else

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Object/Work section --}}
  @if($artwork->work_type || $artwork->classification || $artwork->identifier)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Object/Work') }}</div></h2>
      <div class="field-list">
        @if($artwork->work_type)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Work type') }}</h3>
            <div class="col-9 p-2">{{ $artwork->work_type }}</div>
          </div>
        @endif
        @if($artwork->classification)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Classification') }}</h3>
            <div class="col-9 p-2">{{ $artwork->classification }}</div>
          </div>
        @endif
        @if($artwork->identifier)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Identifier') }}</h3>
            <div class="col-9 p-2">{{ $artwork->identifier }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Creator section --}}
  @if($artwork->creator_identity || $artwork->creator_role || $creators->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Creator') }}</div></h2>
      <div class="field-list">
        @if($artwork->creator_identity)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator') }}</h3>
            <div class="col-9 p-2">{{ $artwork->creator_identity }}</div>
          </div>
        @endif
        @if($artwork->creator_role)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Role') }}</h3>
            <div class="col-9 p-2">{{ $artwork->creator_role }}</div>
          </div>
        @endif
        @foreach($creators as $creator)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Authority record') }}</h3>
            <div class="col-9 p-2">
              <a href="/actor/{{ $creator->slug }}">{{ $creator->name }}</a>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Creation section --}}
  @if($artwork->creation_date_display || $artwork->creation_date_earliest || $artwork->creation_date_latest || $artwork->creation_place || $artwork->style || $artwork->period || $artwork->movement || $artwork->school)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Creation') }}</div></h2>
      <div class="field-list">
        @if($artwork->creation_date_display)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date') }}</h3>
            <div class="col-9 p-2">{{ $artwork->creation_date_display }}</div>
          </div>
        @endif
        @if($artwork->creation_date_earliest)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Earliest date') }}</h3>
            <div class="col-9 p-2">{{ $artwork->creation_date_earliest }}</div>
          </div>
        @endif
        @if($artwork->creation_date_latest)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Latest date') }}</h3>
            <div class="col-9 p-2">{{ $artwork->creation_date_latest }}</div>
          </div>
        @endif
        @if($artwork->creation_place)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place') }}</h3>
            <div class="col-9 p-2">{{ $artwork->creation_place }}</div>
          </div>
        @endif
        @if($artwork->style)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Style') }}</h3>
            <div class="col-9 p-2">{{ $artwork->style }}</div>
          </div>
        @endif
        @if($artwork->period)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Period') }}</h3>
            <div class="col-9 p-2">{{ $artwork->period }}</div>
          </div>
        @endif
        @if($artwork->movement)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Movement') }}</h3>
            <div class="col-9 p-2">{{ $artwork->movement }}</div>
          </div>
        @endif
        @if($artwork->school)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('School') }}</h3>
            <div class="col-9 p-2">{{ $artwork->school }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Measurements section --}}
  @if($artwork->measurements || $artwork->dimensions)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Measurements') }}</div></h2>
      <div class="field-list">
        @if($artwork->measurements)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Measurements') }}</h3>
            <div class="col-9 p-2">{{ $artwork->measurements }}</div>
          </div>
        @endif
        @if($artwork->dimensions)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dimensions') }}</h3>
            <div class="col-9 p-2">{{ $artwork->dimensions }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Materials / Techniques section --}}
  @if($artwork->materials || $artwork->techniques)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Materials / Techniques') }}</div></h2>
      <div class="field-list">
        @if($artwork->materials)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Materials') }}</h3>
            <div class="col-9 p-2">{{ $artwork->materials }}</div>
          </div>
        @endif
        @if($artwork->techniques)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Techniques') }}</h3>
            <div class="col-9 p-2">{{ $artwork->techniques }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Subject / Description section --}}
  @if($artwork->scope_and_content)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Subject') }}</div></h2>
      <div class="field-list">
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Description') }}</h3>
          <div class="col-9 p-2">{!! nl2br(e($artwork->scope_and_content)) !!}</div>
        </div>
      </div>
    </section>
  @endif

  {{-- Inscriptions section --}}
  @if($artwork->inscription || $artwork->mark_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Inscriptions') }}</div></h2>
      <div class="field-list">
        @if($artwork->inscription)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($artwork->inscription)) !!}</div>
          </div>
        @endif
        @if($artwork->mark_description)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Marks') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($artwork->mark_description)) !!}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Condition section --}}
  @if($artwork->condition_term || $artwork->condition_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Condition') }}</div></h2>
      <div class="field-list">
        @if($artwork->condition_term)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition') }}</h3>
            <div class="col-9 p-2">{{ $artwork->condition_term }}</div>
          </div>
        @endif
        @if($artwork->condition_description)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition notes') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($artwork->condition_description)) !!}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Provenance section --}}
  @if($artwork->provenance || $artwork->current_location || $artwork->rights_type || $artwork->rights_holder)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Provenance') }}</div></h2>
      <div class="field-list">
        @if($artwork->provenance)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Provenance') }}</h3>
            <div class="col-9 p-2">{!! nl2br(e($artwork->provenance)) !!}</div>
          </div>
        @endif
        @if($artwork->current_location)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location') }}</h3>
            <div class="col-9 p-2">{{ $artwork->current_location }}</div>
          </div>
        @endif
        @if($artwork->rights_type)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights type') }}</h3>
            <div class="col-9 p-2">{{ $artwork->rights_type }}</div>
          </div>
        @endif
        @if($artwork->rights_holder)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights holder') }}</h3>
            <div class="col-9 p-2">{{ $artwork->rights_holder }}</div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Access points --}}
  @if($subjects->isNotEmpty() || $places->isNotEmpty() || $genres->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Access points') }}</div></h2>
      <div class="field-list">
        @if($subjects->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject access points') }}</h3>
            <div class="col-9 p-2">
              @foreach($subjects as $subj)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $subj->name }}</span>
              @endforeach
            </div>
          </div>
        @endif
        @if($places->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place access points') }}</h3>
            <div class="col-9 p-2">
              @foreach($places as $place)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $place->name }}</span>
              @endforeach
            </div>
          </div>
        @endif
        @if($genres->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Genre access points') }}</h3>
            <div class="col-9 p-2">
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
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Cataloging') }}</div></h2>
      <div class="field-list">
        @if($artwork->cataloger_name)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloger') }}</h3>
            <div class="col-9 p-2">{{ $artwork->cataloger_name }}</div>
          </div>
        @endif
        @if($artwork->cataloging_date)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloging date') }}</h3>
            <div class="col-9 p-2">{{ $artwork->cataloging_date }}</div>
          </div>
        @endif
        @if($repository)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Repository') }}</h3>
            <div class="col-9 p-2">
              <a href="/repository/{{ $repository->slug }}">{{ $repository->name }}</a>
            </div>
          </div>
        @endif
      </div>
    </section>
  @endif

  {{-- Notes --}}
  @if($notes->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Notes') }}</div></h2>
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
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ config('app.ui_label_physicalobject', 'Physical storage') }}</div></h2>
      @foreach($physicalObjects as $po)
        <div class="mb-1">
          @if($po->name)<strong>{{ $po->name }}</strong>@endif
          @if($po->location) &mdash; {{ $po->location }}@endif
        </div>
      @endforeach
    </section>
  @endif

  {{-- Administration --}}
  <section class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Administration') }}</div></h2>
    <div class="field-list">
      @if($artwork->created_at)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Created') }}</h3>
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($artwork->created_at)->format('Y-m-d H:i') }}</div>
        </div>
      @endif
      @if($artwork->updated_at)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Updated') }}</h3>
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($artwork->updated_at)->format('Y-m-d H:i') }}</div>
        </div>
      @endif
    </div>
  </section>

  @endif {{-- end ric_view_mode toggle --}}

  {{-- RiC Context / OpenRiC / Validate — only in RiC view mode, mirroring the
       IO show page. Previously this sat inside the @else (standard/CDWA) branch,
       so it wrongly showed on the standard view and was hidden in RiC view. --}}
  @if(session('ric_view_mode') === 'ric' && class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $artwork, 'recordType' => 'record'])
  @endif
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

  <div class="d-flex gap-1 mb-3">
    <button class="btn btn-sm atom-btn-white" onclick="window.print()" title="{{ __('Print') }}">
      <i class="fas fa-print"></i>
    </button>
  </div>

  @include('ahg-io-manage::partials._right-blocks', [
    'record'           => $artwork,
    'slug'             => $artwork->slug,
    'type'             => 'informationObject',
    'skipExport'       => true,
    'skipActiveLoans'  => true,
  ])

  @include('ahg-core::partials._record-sidebar-extras', ['objectId' => $artwork->id, 'slug' => $artwork->slug, 'title' => $artwork->title])

@endsection

@section('after-content')
  {{-- Horizontal Actions toolbar - parity with the IO / museum sector show
       pages (which render `<ul class="actions">` in after-content). The gallery
       previously had only the sidebar context card here, so the standard
       sector Actions bar was missing. Same inline ACL gating as the sidebar. --}}
  @auth
    @php
      $canUpdate = \AhgCore\Services\AclService::check($artwork, 'update');
      $canDelete = \AhgCore\Services\AclService::check($artwork, 'delete');
    @endphp
    @if($canUpdate || $canDelete)
    <ul class="actions mb-3 nav gap-2">
      @if($canUpdate)
        <li><a href="{{ route('gallery.edit', $artwork->slug) }}" class="btn atom-btn-outline-light"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</a></li>
      @endif
      @if($canDelete)
        <li>
          <form action="{{ route('gallery.destroy', $artwork->slug) }}" method="POST"
                onsubmit="return confirm('{{ __('Are you sure you want to delete this artwork?') }}');">
            @csrf
            <button type="submit" class="btn atom-btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</button>
          </form>
        </li>
      @endif
      @if($canUpdate)
        <li><a href="{{ route('gallery.create') }}" class="btn atom-btn-outline-light"><i class="fas fa-plus me-1"></i>{{ __('Add new') }}</a></li>
      @endif
    </ul>
    @endif
  @endauth
  @include('ahg-core::partials._ner-modal', ['objectId' => $artwork->id, 'objectTitle' => $artwork->title])
@endsection
