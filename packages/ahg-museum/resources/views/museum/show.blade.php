@extends('theme::layouts.3col')

@section('title', ($museum->title ?? 'Museum object'))
@section('body-class', 'view museum')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR                                                  --}}
{{-- ============================================================ --}}
@section('sidebar')

  @auth
    <div class="card mb-3">
      <div class="card-header fw-bold">
        <i class="fas fa-cogs me-1"></i> Actions
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('museum.edit', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-pencil-alt me-1"></i> Edit
        </a>
        <form method="POST" action="{{ route('museum.destroy', $museum->slug) }}"
              onsubmit="return confirm('Are you sure you want to delete this museum object?');">
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
    @if($museum->work_type)<span class="text-muted">{{ $museum->work_type }}</span> @endif
    @if($museum->identifier){{ $museum->identifier }} - @endif
    {{ $museum->title ?: '[Untitled]' }}
  </h1>

  {{-- Breadcrumb trail --}}
  @if($museum->parent_id != 1 && !empty($breadcrumbs))
    <nav aria-label="Hierarchy">
      <ol class="breadcrumb">
        @foreach($breadcrumbs as $crumb)
          <li class="breadcrumb-item">
            <a href="{{ url('/' . $crumb->slug) }}">
              {{ $crumb->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">
          {{ $museum->title ?: '[Untitled]' }}
        </li>
      </ol>
    </nav>
  @endif

  {{-- Publication status badge --}}
  @auth
    @if($publicationStatus)
      <span class="badge {{ (isset($publicationStatusId) && $publicationStatusId == 159) ? 'bg-warning text-dark' : 'bg-info' }} mb-2">{{ $publicationStatus }}</span>
    @endif
  @endauth

@endsection

{{-- ============================================================ --}}
{{-- BEFORE CONTENT: Digital object reference image               --}}
{{-- ============================================================ --}}
@section('before-content')

  @if(isset($digitalObjects) && ($digitalObjects['master'] || $digitalObjects['reference'] || $digitalObjects['thumbnail']))
    @php
      $masterObj = $digitalObjects['master'];
      $refObj = $digitalObjects['reference'] ?? null;
      $thumbObj = $digitalObjects['thumbnail'] ?? null;
      $refUrl = $refObj ? \AhgCore\Services\DigitalObjectService::getUrl($refObj) : '';
      $thumbUrl = $thumbObj ? \AhgCore\Services\DigitalObjectService::getUrl($thumbObj) : '';
      $masterUrl = $masterObj ? \AhgCore\Services\DigitalObjectService::getUrl($masterObj) : '';
      $displayUrl = $refUrl ?: $thumbUrl ?: $masterUrl;
    @endphp

    @if($displayUrl)
      <div class="digital-object-reference text-center p-3 border-bottom">
        <a href="{{ $masterUrl ?: $displayUrl }}" target="_blank">
          <img src="{{ $displayUrl }}" alt="{{ $museum->title }}" class="img-fluid" style="max-height:400px;">
        </a>
      </div>
    @endif
  @endif

@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT                                                  --}}
{{-- ============================================================ --}}
@section('content')

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- ===== Object Identification ===== --}}
  <section class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Object Identification</div></h2>

    @if($museum->work_type)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Work type</h3>
        <div>{{ $museum->work_type }}</div>
      </div>
    @endif

    @if($museum->object_type)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Object type</h3>
        <div>{{ $museum->object_type }}</div>
      </div>
    @endif

    @if($museum->classification)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Classification</h3>
        <div>{{ $museum->classification }}</div>
      </div>
    @endif

    @if($museum->object_class)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Object class</h3>
        <div>{{ $museum->object_class }}</div>
      </div>
    @endif

    @if($museum->object_category)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Object category</h3>
        <div>{{ $museum->object_category }}</div>
      </div>
    @endif

    @if($museum->object_sub_category)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Object sub-category</h3>
        <div>{{ $museum->object_sub_category }}</div>
      </div>
    @endif

    @if($museum->identifier)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Identifier</h3>
        <div>{{ $museum->identifier }}</div>
      </div>
    @endif

    @if($museum->record_type)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Record type</h3>
        <div>{{ $museum->record_type }}</div>
      </div>
    @endif

    @if($museum->record_level)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Record level</h3>
        <div>{{ $museum->record_level }}</div>
      </div>
    @endif

    @if($levelName)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Level of description</h3>
        <div>{{ $levelName }}</div>
      </div>
    @endif
  </section>

  {{-- ===== Title ===== --}}
  <section class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Title</div></h2>

    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Title</h3>
      <div>{{ $museum->title ?: '[Untitled]' }}</div>
    </div>

    @if($museum->alternate_title)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Alternate title</h3>
        <div>{{ $museum->alternate_title }}</div>
      </div>
    @endif
  </section>

  {{-- ===== Creator ===== --}}
  @if($museum->creator_identity || $museum->creator_role || $museum->creator_extent || $museum->creator_qualifier || $museum->creator_attribution)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Creator</div></h2>

      @if($museum->creator_identity)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creator identity</h3>
          <div>{{ $museum->creator_identity }}</div>
        </div>
      @endif

      @if($museum->creator_role)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creator role</h3>
          <div>{{ $museum->creator_role }}</div>
        </div>
      @endif

      @if($museum->creator_extent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creator extent</h3>
          <div>{{ $museum->creator_extent }}</div>
        </div>
      @endif

      @if($museum->creator_qualifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creator qualifier</h3>
          <div>{{ $museum->creator_qualifier }}</div>
        </div>
      @endif

      @if($museum->creator_attribution)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creator attribution</h3>
          <div>{{ $museum->creator_attribution }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Creation ===== --}}
  @if($museum->creation_date_display || $museum->creation_date_earliest || $museum->creation_date_latest || $museum->creation_place || $museum->style || $museum->period || $museum->cultural_group || $museum->movement || $museum->school || $museum->dynasty || $museum->discovery_place)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Creation</div></h2>

      @if($museum->creation_date_display)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creation date</h3>
          <div>{{ $museum->creation_date_display }}</div>
        </div>
      @endif

      @if($museum->creation_date_earliest || $museum->creation_date_latest)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Date range</h3>
          <div>
            @if($museum->creation_date_earliest){{ $museum->creation_date_earliest }}@endif
            @if($museum->creation_date_earliest && $museum->creation_date_latest) &ndash; @endif
            @if($museum->creation_date_latest){{ $museum->creation_date_latest }}@endif
          </div>
        </div>
      @endif

      @if($museum->creation_date_qualifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Date qualifier</h3>
          <div>{{ $museum->creation_date_qualifier }}</div>
        </div>
      @endif

      @if($museum->creation_place)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creation place</h3>
          <div>{{ $museum->creation_place }}</div>
        </div>
      @endif

      @if($museum->creation_place_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creation place type</h3>
          <div>{{ $museum->creation_place_type }}</div>
        </div>
      @endif

      @if($museum->discovery_place)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Discovery place</h3>
          <div>{{ $museum->discovery_place }}</div>
        </div>
      @endif

      @if($museum->discovery_place_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Discovery place type</h3>
          <div>{{ $museum->discovery_place_type }}</div>
        </div>
      @endif

      @if($museum->style)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Style</h3>
          <div>{{ $museum->style }}</div>
        </div>
      @endif

      @if($museum->period)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Period</h3>
          <div>{{ $museum->period }}</div>
        </div>
      @endif

      @if($museum->cultural_group)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Cultural group</h3>
          <div>{{ $museum->cultural_group }}</div>
        </div>
      @endif

      @if($museum->movement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Movement</h3>
          <div>{{ $museum->movement }}</div>
        </div>
      @endif

      @if($museum->school)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">School</h3>
          <div>{{ $museum->school }}</div>
        </div>
      @endif

      @if($museum->dynasty)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dynasty</h3>
          <div>{{ $museum->dynasty }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Measurements ===== --}}
  @if($museum->measurements || $museum->dimensions || $museum->orientation || $museum->shape)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Measurements</div></h2>

      @if($museum->measurements)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Measurements</h3>
          <div>{{ $museum->measurements }}</div>
        </div>
      @endif

      @if($museum->dimensions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Dimensions</h3>
          <div>{{ $museum->dimensions }}</div>
        </div>
      @endif

      @if($museum->orientation)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Orientation</h3>
          <div>{{ $museum->orientation }}</div>
        </div>
      @endif

      @if($museum->shape)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Shape</h3>
          <div>{{ $museum->shape }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Materials / Techniques ===== --}}
  @if($museum->materials || $museum->techniques || $museum->technique_cco || $museum->facture_description || $museum->color || $museum->physical_appearance || $museum->extent_and_medium)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Materials / Techniques</div></h2>

      @if($museum->materials)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Materials</h3>
          <div>{!! nl2br(e($museum->materials)) !!}</div>
        </div>
      @endif

      @if($museum->techniques)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Techniques</h3>
          <div>{!! nl2br(e($museum->techniques)) !!}</div>
        </div>
      @endif

      @if($museum->technique_cco)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Technique (CCO)</h3>
          <div>{{ $museum->technique_cco }}</div>
        </div>
      @endif

      @if($museum->technique_qualifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Technique qualifier</h3>
          <div>{{ $museum->technique_qualifier }}</div>
        </div>
      @endif

      @if($museum->facture_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Facture description</h3>
          <div>{!! nl2br(e($museum->facture_description)) !!}</div>
        </div>
      @endif

      @if($museum->color)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Color</h3>
          <div>{{ $museum->color }}</div>
        </div>
      @endif

      @if($museum->physical_appearance)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Physical appearance</h3>
          <div>{!! nl2br(e($museum->physical_appearance)) !!}</div>
        </div>
      @endif

      @if($museum->extent_and_medium)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Extent and medium</h3>
          <div>{!! nl2br(e($museum->extent_and_medium)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Subject / Content ===== --}}
  @if($museum->scope_and_content || $museum->style_period || $museum->cultural_context || $museum->subject_display || $museum->historical_context || $museum->architectural_context || $museum->archaeological_context || $subjects->isNotEmpty() || $places->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Subject / Content</div></h2>

      @if($museum->scope_and_content)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope and content</h3>
          <div>{!! nl2br(e($museum->scope_and_content)) !!}</div>
        </div>
      @endif

      @if($museum->style_period)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Style / Period</h3>
          <div>{{ $museum->style_period }}</div>
        </div>
      @endif

      @if($museum->cultural_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Cultural context</h3>
          <div>{{ $museum->cultural_context }}</div>
        </div>
      @endif

      @if($museum->subject_indexing_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subject indexing type</h3>
          <div>{{ $museum->subject_indexing_type }}</div>
        </div>
      @endif

      @if($museum->subject_display)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subject display</h3>
          <div>{!! nl2br(e($museum->subject_display)) !!}</div>
        </div>
      @endif

      @if($museum->subject_extent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subject extent</h3>
          <div>{{ $museum->subject_extent }}</div>
        </div>
      @endif

      @if($museum->historical_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Historical context</h3>
          <div>{!! nl2br(e($museum->historical_context)) !!}</div>
        </div>
      @endif

      @if($museum->architectural_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Architectural context</h3>
          <div>{!! nl2br(e($museum->architectural_context)) !!}</div>
        </div>
      @endif

      @if($museum->archaeological_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Archaeological context</h3>
          <div>{!! nl2br(e($museum->archaeological_context)) !!}</div>
        </div>
      @endif

      @if($subjects->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subject access points</h3>
          <div>
            @foreach($subjects as $subject)
              <span class="badge bg-secondary me-1">{{ $subject->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($places->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Place access points</h3>
          <div>
            @foreach($places as $place)
              <span class="badge bg-secondary me-1">{{ $place->name }}</span>
            @endforeach
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Edition / State ===== --}}
  @if($museum->edition_description || $museum->edition_number || $museum->edition_size || $museum->state_identification || $museum->state_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Edition / State</div></h2>

      @if($museum->edition_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Edition description</h3>
          <div>{!! nl2br(e($museum->edition_description)) !!}</div>
        </div>
      @endif

      @if($museum->edition_number)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Edition number</h3>
          <div>{{ $museum->edition_number }}</div>
        </div>
      @endif

      @if($museum->edition_size)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Edition size</h3>
          <div>{{ $museum->edition_size }}</div>
        </div>
      @endif

      @if($museum->state_identification)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">State identification</h3>
          <div>{{ $museum->state_identification }}</div>
        </div>
      @endif

      @if($museum->state_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">State description</h3>
          <div>{!! nl2br(e($museum->state_description)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Inscriptions ===== --}}
  @if($museum->inscription || $museum->inscriptions || $museum->inscription_transcription || $museum->inscription_type || $museum->inscription_location || $museum->mark_type || $museum->mark_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Inscriptions</div></h2>

      @if($museum->inscription)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscription</h3>
          <div>{!! nl2br(e($museum->inscription)) !!}</div>
        </div>
      @endif

      @if($museum->inscriptions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscriptions (additional)</h3>
          <div>{!! nl2br(e($museum->inscriptions)) !!}</div>
        </div>
      @endif

      @if($museum->inscription_transcription)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscription transcription</h3>
          <div>{!! nl2br(e($museum->inscription_transcription)) !!}</div>
        </div>
      @endif

      @if($museum->inscription_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscription type</h3>
          <div>{{ $museum->inscription_type }}</div>
        </div>
      @endif

      @if($museum->inscription_location)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscription location</h3>
          <div>{{ $museum->inscription_location }}</div>
        </div>
      @endif

      @if($museum->inscription_language)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscription language</h3>
          <div>{{ $museum->inscription_language }}</div>
        </div>
      @endif

      @if($museum->inscription_translation)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Inscription translation</h3>
          <div>{!! nl2br(e($museum->inscription_translation)) !!}</div>
        </div>
      @endif

      @if($museum->mark_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Mark type</h3>
          <div>{{ $museum->mark_type }}</div>
        </div>
      @endif

      @if($museum->mark_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Mark description</h3>
          <div>{!! nl2br(e($museum->mark_description)) !!}</div>
        </div>
      @endif

      @if($museum->mark_location)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Mark location</h3>
          <div>{{ $museum->mark_location }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Condition ===== --}}
  @if($museum->condition_term || $museum->condition_date || $museum->condition_description || $museum->condition_notes || $museum->treatment_type || $museum->treatment_description)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Condition</div></h2>

      @if($museum->condition_term)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Condition term</h3>
          <div>{{ $museum->condition_term }}</div>
        </div>
      @endif

      @if($museum->condition_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Condition date</h3>
          <div>{{ $museum->condition_date }}</div>
        </div>
      @endif

      @if($museum->condition_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Condition description</h3>
          <div>{!! nl2br(e($museum->condition_description)) !!}</div>
        </div>
      @endif

      @if($museum->condition_notes)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Condition notes</h3>
          <div>{!! nl2br(e($museum->condition_notes)) !!}</div>
        </div>
      @endif

      @if($museum->condition_agent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Condition agent</h3>
          <div>{{ $museum->condition_agent }}</div>
        </div>
      @endif

      @if($museum->treatment_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Treatment type</h3>
          <div>{{ $museum->treatment_type }}</div>
        </div>
      @endif

      @if($museum->treatment_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Treatment date</h3>
          <div>{{ $museum->treatment_date }}</div>
        </div>
      @endif

      @if($museum->treatment_agent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Treatment agent</h3>
          <div>{{ $museum->treatment_agent }}</div>
        </div>
      @endif

      @if($museum->treatment_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Treatment description</h3>
          <div>{!! nl2br(e($museum->treatment_description)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Provenance / Location ===== --}}
  @if($museum->provenance || $museum->provenance_text || $museum->ownership_history || $museum->current_location || $museum->legal_status || $museum->rights_type || $museum->rights_holder || $repository)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Provenance / Location</div></h2>

      @if($museum->provenance)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Provenance</h3>
          <div>{!! nl2br(e($museum->provenance)) !!}</div>
        </div>
      @endif

      @if($museum->provenance_text)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Provenance text</h3>
          <div>{!! nl2br(e($museum->provenance_text)) !!}</div>
        </div>
      @endif

      @if($museum->ownership_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Ownership history</h3>
          <div>{!! nl2br(e($museum->ownership_history)) !!}</div>
        </div>
      @endif

      @if($museum->current_location)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Current location</h3>
          <div>{!! nl2br(e($museum->current_location)) !!}</div>
        </div>
      @endif

      @if($museum->current_location_repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Current location repository</h3>
          <div>{{ $museum->current_location_repository }}</div>
        </div>
      @endif

      @if($museum->current_location_geography)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Current location geography</h3>
          <div>{{ $museum->current_location_geography }}</div>
        </div>
      @endif

      @if($museum->current_location_coordinates)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Current location coordinates</h3>
          <div>{{ $museum->current_location_coordinates }}</div>
        </div>
      @endif

      @if($museum->current_location_ref_number)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Current location reference number</h3>
          <div>{{ $museum->current_location_ref_number }}</div>
        </div>
      @endif

      @if($museum->legal_status)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Legal status</h3>
          <div>{{ $museum->legal_status }}</div>
        </div>
      @endif

      @if($museum->rights_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights type</h3>
          <div>{{ $museum->rights_type }}</div>
        </div>
      @endif

      @if($museum->rights_holder)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights holder</h3>
          <div>{{ $museum->rights_holder }}</div>
        </div>
      @endif

      @if($museum->rights_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights date</h3>
          <div>{{ $museum->rights_date }}</div>
        </div>
      @endif

      @if($museum->rights_remarks)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights remarks</h3>
          <div>{!! nl2br(e($museum->rights_remarks)) !!}</div>
        </div>
      @endif

      @if($repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Repository</h3>
          <div>
            <a href="{{ url('/repository/' . $repository->slug) }}">{{ $repository->name }}</a>
          </div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Related Works ===== --}}
  @if($museum->related_work_type || $museum->related_work_relationship || $museum->related_work_label || $museum->related_work_id)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Related Works</div></h2>

      @if($museum->related_work_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related work type</h3>
          <div>{{ $museum->related_work_type }}</div>
        </div>
      @endif

      @if($museum->related_work_relationship)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related work relationship</h3>
          <div>{{ $museum->related_work_relationship }}</div>
        </div>
      @endif

      @if($museum->related_work_label)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related work label</h3>
          <div>{{ $museum->related_work_label }}</div>
        </div>
      @endif

      @if($museum->related_work_id)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Related work identifier</h3>
          <div>{{ $museum->related_work_id }}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Cataloging ===== --}}
  @if($museum->cataloger_name || $museum->cataloging_date || $museum->cataloging_institution || $museum->cataloging_remarks)
    <section class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Cataloging</div></h2>

      @if($museum->cataloger_name)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Cataloger name</h3>
          <div>{{ $museum->cataloger_name }}</div>
        </div>
      @endif

      @if($museum->cataloging_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Cataloging date</h3>
          <div>{{ $museum->cataloging_date }}</div>
        </div>
      @endif

      @if($museum->cataloging_institution)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Cataloging institution</h3>
          <div>{{ $museum->cataloging_institution }}</div>
        </div>
      @endif

      @if($museum->cataloging_remarks)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Cataloging remarks</h3>
          <div>{!! nl2br(e($museum->cataloging_remarks)) !!}</div>
        </div>
      @endif
    </section>
  @endif

  {{-- ===== Record Info ===== --}}
  <section class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Record Info</div></h2>

    @if($museum->created_at)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Created</h3>
        <div>{{ \Carbon\Carbon::parse($museum->created_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif

    @if($museum->updated_at)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Last updated</h3>
        <div>{{ \Carbon\Carbon::parse($museum->updated_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif
  </section>

@endsection
