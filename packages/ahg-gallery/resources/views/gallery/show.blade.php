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
    
    <div class="list-group list-group-flush">
      <a href="{{ route('gallery.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-th me-1"></i> {{ __('Browse artworks') }}
      </a>
      <a href="{{ route('gallery.artists') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-users me-1"></i> {{ __('Browse artists') }}
      </a>
    
  

  {{-- Artist information (if linked) --}}
  @if($galleryArtist)
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-user me-1"></i> Artist
      
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
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Work type') }}
            <div class="col-9 p-2">{{ $artwork->work_type }}
          
        @endif
        @if($artwork->classification)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Classification') }}
            <div class="col-9 p-2">{{ $artwork->classification }}
          
        @endif
        @if($artwork->identifier)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Identifier') }}
            <div class="col-9 p-2">{{ $artwork->identifier }}
          
        @endif
      
    </section>
  @endif

  {{-- Creator section --}}
  @if($artwork->creator_identity || $artwork->creator_role || $creators->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Creator') }}</div></h2>
      <div class="field-list">
        @if($artwork->creator_identity)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator') }}
            <div class="col-9 p-2">{{ $artwork->creator_identity }}
          
        @endif
        @if($artwork->creator_role)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Role') }}
            <div class="col-9 p-2">{{ $artwork->creator_role }}
          
        @endif
        @foreach($creators as $creator)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Authority record') }}
            <div class="col-9 p-2">
              <a href="/actor/{{ $creator->slug }}">{{ $creator->name }}</a>
            
          
        @endforeach
      
    </section>
  @endif

  {{-- Creation section --}}
  @if($artwork->creation_date_display || $artwork->creation_date_earliest || $artwork->creation_date_latest || $artwork->creation_place || $artwork->style || $artwork->period || $artwork->movement || $artwork->school)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Creation') }}</div></h2>
      <div class="field-list">
        @if($artwork->creation_date_display)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date') }}
            <div class="col-9 p-2">{{ $artwork->creation_date_display }}
          
        @endif
        @if($artwork->creation_date_earliest)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Earliest date') }}
            <div class="col-9 p-2">{{ $artwork->creation_date_earliest }}
          
        @endif
        @if($artwork->creation_date_latest)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Latest date') }}
            <div class="col-9 p-2">{{ $artwork->creation_date_latest }}
          
        @endif
        @if($artwork->creation_place)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place') }}
            <div class="col-9 p-2">{{ $artwork->creation_place }}
          
        @endif
        @if($artwork->style)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Style') }}
            <div class="col-9 p-2">{{ $artwork->style }}
          
        @endif
        @if($artwork->period)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Period') }}
            <div class="col-9 p-2">{{ $artwork->period }}
          
        @endif
        @if($artwork->movement)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Movement') }}
            <div class="col-9 p-2">{{ $artwork->movement }}
          
        @endif
        @if($artwork->school)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('School') }}
            <div class="col-9 p-2">{{ $artwork->school }}
          
        @endif
      
    </section>
  @endif

  {{-- Measurements section --}}
  @if($artwork->measurements || $artwork->dimensions)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Measurements') }}</div></h2>
      <div class="field-list">
        @if($artwork->measurements)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Measurements') }}
            <div class="col-9 p-2">{{ $artwork->measurements }}
          
        @endif
        @if($artwork->dimensions)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dimensions') }}
            <div class="col-9 p-2">{{ $artwork->dimensions }}
          
        @endif
      
    </section>
  @endif

  {{-- Materials / Techniques section --}}
  @if($artwork->materials || $artwork->techniques)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Materials / Techniques') }}</div></h2>
      <div class="field-list">
        @if($artwork->materials)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Materials') }}
            <div class="col-9 p-2">{{ $artwork->materials }}
          
        @endif
        @if($artwork->techniques)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Techniques') }}
            <div class="col-9 p-2">{{ $artwork->techniques }}
          
        @endif
      
    </section>
  @endif

  {{-- Subject / Description section --}}
  @if($artwork->scope_and_content)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Subject') }}</div></h2>
      <div class="field-list">
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Description') }}
          <div class="col-9 p-2">{!! nl2br(e($artwork->scope_and_content)) !!}
        
      
    </section>
  @endif

  {{-- Inscriptions section --}}
  @if($artwork->inscription || $artwork->mark_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Inscriptions') }}</div></h2>
      <div class="field-list">
        @if($artwork->inscription)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription') }}
            <div class="col-9 p-2">{!! nl2br(e($artwork->inscription)) !!}
          
        @endif
        @if($artwork->mark_description)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Marks') }}
            <div class="col-9 p-2">{!! nl2br(e($artwork->mark_description)) !!}
          
        @endif
      
    </section>
  @endif

  {{-- Condition section --}}
  @if($artwork->condition_term || $artwork->condition_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Condition') }}</div></h2>
      <div class="field-list">
        @if($artwork->condition_term)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition') }}
            <div class="col-9 p-2">{{ $artwork->condition_term }}
          
        @endif
        @if($artwork->condition_description)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition notes') }}
            <div class="col-9 p-2">{!! nl2br(e($artwork->condition_description)) !!}
          
        @endif
      
    </section>
  @endif

  {{-- Provenance section --}}
  @if($artwork->provenance || $artwork->current_location || $artwork->rights_type || $artwork->rights_holder)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Provenance') }}</div></h2>
      <div class="field-list">
        @if($artwork->provenance)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Provenance') }}
            <div class="col-9 p-2">{!! nl2br(e($artwork->provenance)) !!}
          
        @endif
        @if($artwork->current_location)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location') }}
            <div class="col-9 p-2">{{ $artwork->current_location }}
          
        @endif
        @if($artwork->rights_type)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights type') }}
            <div class="col-9 p-2">{{ $artwork->rights_type }}
          
        @endif
        @if($artwork->rights_holder)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights holder') }}
            <div class="col-9 p-2">{{ $artwork->rights_holder }}
          
        @endif
      
    </section>
  @endif

  {{-- Access points --}}
  @if($subjects->isNotEmpty() || $places->isNotEmpty() || $genres->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Access points') }}</div></h2>
      <div class="field-list">
        @if($subjects->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject access points') }}
            <div class="col-9 p-2">
              @foreach($subjects as $subj)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $subj->name }}</span>
              @endforeach
            
          
        @endif
        @if($places->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place access points') }}
            <div class="col-9 p-2">
              @foreach($places as $place)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $place->name }}</span>
              @endforeach
            
          
        @endif
        @if($genres->isNotEmpty())
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Genre access points') }}
            <div class="col-9 p-2">
              @foreach($genres as $genre)
                <span class="badge bg-light text-dark border me-1 mb-1">{{ $genre->name }}</span>
              @endforeach
            
          
        @endif
      
    </section>
  @endif

  {{-- Cataloging section --}}
  @if($artwork->cataloger_name || $artwork->cataloging_date || $repository)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Cataloging') }}</div></h2>
      <div class="field-list">
        @if($artwork->cataloger_name)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloger') }}
            <div class="col-9 p-2">{{ $artwork->cataloger_name }}
          
        @endif
        @if($artwork->cataloging_date)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloging date') }}
            <div class="col-9 p-2">{{ $artwork->cataloging_date }}
          
        @endif
        @if($repository)
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Repository') }}
            <div class="col-9 p-2">
              <a href="/repository/{{ $repository->slug }}">{{ $repository->name }}</a>
            
          
        @endif
      
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
        
      @endforeach
    </section>
  @endif

  {{-- Administration --}}
  <section class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Administration') }}</div></h2>
    <div class="field-list">
      @if($artwork->created_at)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Created') }}
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($artwork->created_at)->format('Y-m-d H:i') }}
        
      @endif
      @if($artwork->updated_at)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Updated') }}
          <div class="col-9 p-2">{{ \Carbon\Carbon::parse($artwork->updated_at)->format('Y-m-d H:i') }}
        
      @endif

  </section>

  @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $artwork, 'recordType' => 'record'])
  @endif
  @endif {{-- end ric_view_mode toggle --}}
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
      
    
  @elseif(!empty($digitalObjects['thumbnail']))
    <div class="card mb-3">
      <div class="card-body p-2 text-center">
        <img src="/uploads/{{ $digitalObjects['thumbnail']->path }}/{{ $digitalObjects['thumbnail']->name }}"
             class="img-fluid" alt="{{ $artwork->title ?: 'Artwork image' }}">
      
    
  @endif

  {{-- Events / Dates --}}
  @if($events->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-calendar me-1"></i> Dates

      <div class="list-group list-group-flush">
        @foreach($events as $event)
          <div class="list-group-item small">
            @if($event->date_display)
              {{ $event->date_display }}
            @elseif($event->start_date)
              {{ $event->start_date }}
              @if($event->end_date) &ndash; {{ $event->end_date }}@endif
            @endif

        @endforeach


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
  @include('ahg-core::partials._ner-modal', ['objectId' => $artwork->id, 'objectTitle' => $artwork->title])
@endsection
