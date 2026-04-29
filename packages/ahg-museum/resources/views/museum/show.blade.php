@extends('theme::layouts.3col')

@section('title', ($museum->title ?? 'Museum object'))
@section('body-class', 'view museum')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR — matches AtoM ahgMuseumPlugin contextMenu      --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Access points (subject / name / place) — sidebar mode --}}
  @include('ahg-core::_subject-access-points', ['resource' => $museum, 'sidebar' => true])
  @include('ahg-core::_place-access-points', ['resource' => $museum, 'sidebar' => true])
  @include('ahg-core::_name-access-points', ['resource' => $museum, 'sidebar' => true])

@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- ============================================================ --}}
@section('title-block')

  <h1 class="mb-2 d-flex align-items-start">
    <span class="flex-grow-1">
      @if($museum->work_type)<span class="text-muted">{{ $museum->work_type }}</span> @endif
      @if($museum->identifier){{ $museum->identifier }} - @endif
      {{ $museum->title ?: '[Untitled]' }}
    </span>
    @auth
      {{-- Inline edit / delete affordance (descriptionHeader equivalent in AtoM) --}}
      <span class="ms-2 d-inline-flex gap-1" style="font-size:1rem;">
        <a href="{{ route('museum.edit', $museum->slug) }}" class="btn btn-sm atom-btn-white" title="Edit" data-bs-toggle="tooltip">
          <i class="fas fa-pencil-alt"></i>
        </a>
        <form method="POST" action="{{ route('museum.destroy', $museum->slug) }}" class="d-inline"
              onsubmit="return confirm('Are you sure you want to delete this museum object?');">
          @csrf
          <button type="submit" class="btn btn-sm atom-btn-white text-danger" title="Delete" data-bs-toggle="tooltip">
            <i class="fas fa-trash"></i>
          </button>
        </form>
      </span>
    @endauth
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
{{-- BEFORE CONTENT: Digital object reference image (carousel/IIIF) --}}
{{-- ============================================================ --}}
@section('before-content')
  @include('ahg-information-object-manage::partials._digital-object-viewer', ['io' => $museum])
@endsection

{{-- ============================================================ --}}
{{-- MAIN CONTENT                                                  --}}
{{-- ============================================================ --}}
@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'Spectrum'])
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-museum', ['museum' => $museum])
  @else

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- AtoM-parity action toolbar (ahgMuseumPlugin/.../indexSuccess.php lines 263-309).
       Order: TTS, PDF-read, Favourites, Feedback, Request-to-Publish, Cart, Loan-New,
       Manage-Loans. Auth gating matches AtoM: TTS / Feedback / Request-to-Publish /
       Cart are public; Favourites / Loan require auth. --}}
  @php
    $userId = auth()->id();
    $favouritesEnabled = \AhgCore\Services\MenuService::isPluginEnabled('ahgFavoritesPlugin');
    $feedbackEnabled = \AhgCore\Services\MenuService::isPluginEnabled('ahgFeedbackPlugin');
    $requestToPublishEnabled = \AhgCore\Services\MenuService::isPluginEnabled('ahgRequestToPublishPlugin');
    $cartEnabled = \AhgCore\Services\MenuService::isPluginEnabled('ahgCartPlugin');
    $loanEnabled = \AhgCore\Services\MenuService::isPluginEnabled('ahgLoanPlugin');
    $hasDigitalObject = isset($digitalObjects) && ($digitalObjects['master'] ?? null);
    $pdfDigitalObject = \Illuminate\Support\Facades\DB::table('digital_object')
        ->where('object_id', $museum->id)
        ->where('mime_type', 'application/pdf')
        ->first();
    $favoriteId = $userId
        ? \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $userId)
            ->where('archival_description_id', $museum->id)
            ->value('id')
        : null;
    $cartId = null;
    if ($userId) {
        $cartId = \Illuminate\Support\Facades\DB::table('cart')
            ->where('user_id', $userId)
            ->where('archival_description_id', $museum->id)
            ->whereNull('completed_at')->value('id');
    } elseif (session()->getId()) {
        $cartId = \Illuminate\Support\Facades\DB::table('cart')
            ->where('session_id', session()->getId())
            ->where('archival_description_id', $museum->id)
            ->whereNull('completed_at')->value('id');
    }
  @endphp

  <div class="d-flex flex-wrap gap-1 mb-3 align-items-center">
    {{-- TTS: read metadata aloud --}}
    <button type="button" class="btn btn-sm btn-outline-secondary"
            data-tts-action="toggle" data-tts-target="#tts-content-area"
            title="Read metadata aloud" data-bs-toggle="tooltip">
      <i class="fas fa-volume-up"></i>
    </button>

    {{-- TTS PDF: read PDF content aloud (when a PDF derivative exists) --}}
    @if($pdfDigitalObject)
      <button type="button" class="btn btn-sm btn-outline-info"
              data-tts-action="read-pdf" data-tts-pdf-id="{{ $pdfDigitalObject->id }}"
              title="Read PDF content aloud" data-bs-toggle="tooltip">
        <i class="fas fa-file-pdf"></i>
      </button>
    @endif

    {{-- Favourites — @auth + plugin gate --}}
    @auth
      @if($favouritesEnabled)
        @if($favoriteId)
          <form method="POST" action="{{ route('favorites.remove', $favoriteId) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from Favorites" data-bs-toggle="tooltip">
              <i class="fas fa-heart-broken"></i>
            </button>
          </form>
        @else
          <a href="{{ route('favorites.add', $museum->slug) }}" class="btn btn-sm btn-outline-danger" title="Add to Favorites" data-bs-toggle="tooltip">
            <i class="fas fa-heart"></i>
          </a>
        @endif
      @endif
    @endauth

    {{-- Feedback — public, plugin-gated --}}
    @if($feedbackEnabled)
      <a href="{{ url('/feedback/submit/' . $museum->slug) }}" class="btn btn-sm btn-outline-secondary" title="Item Feedback" data-bs-toggle="tooltip">
        <i class="fas fa-comment"></i>
      </a>
    @endif

    {{-- Request to Publish — public, plugin-gated, requires DO --}}
    @if($requestToPublishEnabled && $hasDigitalObject)
      <a href="{{ url('/request-to-publish/' . $museum->slug) }}" class="btn btn-sm btn-outline-primary" title="Request to Publish" data-bs-toggle="tooltip">
        <i class="fas fa-paper-plane"></i>
      </a>
    @endif

    {{-- Cart — public, plugin-gated, requires DO --}}
    @if($cartEnabled && $hasDigitalObject)
      @if($cartId)
        <a href="{{ route('cart.browse') }}" class="btn btn-sm btn-outline-success" title="Go to Cart" data-bs-toggle="tooltip">
          <i class="fas fa-shopping-cart"></i>
        </a>
      @else
        <a href="{{ route('cart.add', $museum->slug) }}" class="btn btn-sm btn-outline-success" title="Add to Cart" data-bs-toggle="tooltip">
          <i class="fas fa-cart-plus"></i>
        </a>
      @endif
    @endif

    {{-- Loan — @auth + plugin gate --}}
    @auth
      @if($loanEnabled)
        <a href="{{ route('loan.create', ['type' => 'out', 'sector' => 'museum', 'object_id' => $museum->id]) }}" class="btn btn-sm btn-outline-warning" title="New Loan" data-bs-toggle="tooltip">
          <i class="fas fa-hand-holding"></i>
        </a>
        <a href="{{ route('loan.index', ['sector' => 'museum', 'object_id' => $museum->id]) }}" class="btn btn-sm btn-outline-info" title="Manage Loans" data-bs-toggle="tooltip">
          <i class="fas fa-exchange-alt"></i>
        </a>
      @endif
    @endauth
  </div>

  {{-- TTS content area: everything below this is read aloud when TTS toggles on --}}
  <div id="tts-content-area" data-tts-content>

  {{-- ===== Object Identification ===== --}}
  <section id="objectIdentificationArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#objectIdentification-collapse">
        Object Identification
      </a>
      @auth
        <a href="{{ route('museum.edit', $museum->slug) }}#objectIdentification-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Object Identification">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="objectIdentification-collapse">

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
    </div>
  </section>

  {{-- ===== Title ===== --}}
  <section id="titleArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#title-collapse">
        Title
      </a>
      @auth
        <a href="{{ route('museum.edit', $museum->slug) }}#title-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Title">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="title-collapse">

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
    </div>
  </section>

  {{-- ===== Creator ===== --}}
  @if($museum->creator_identity || $museum->creator_role || $museum->creator_extent || $museum->creator_qualifier || $museum->creator_attribution)
    <section id="creatorArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#creator-collapse">
          Creator
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#creator-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Creator">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="creator-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Creation ===== --}}
  @if($museum->creation_date_display || $museum->creation_date_earliest || $museum->creation_date_latest || $museum->creation_place || $museum->style || $museum->period || $museum->cultural_group || $museum->movement || $museum->school || $museum->dynasty || $museum->discovery_place)
    <section id="creationArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#creation-collapse">
          Creation
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#creation-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Creation">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="creation-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Measurements ===== --}}
  @if($museum->measurements || $museum->dimensions || $museum->orientation || $museum->shape)
    <section id="measurementsArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#measurements-collapse">
          Measurements
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#measurements-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Measurements">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="measurements-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Materials / Techniques ===== --}}
  @if($museum->materials || $museum->techniques || $museum->technique_cco || $museum->facture_description || $museum->color || $museum->physical_appearance || $museum->extent_and_medium)
    <section id="materialsArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#materials-collapse">
          Materials / Techniques
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#materials-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Materials / Techniques">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="materials-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Subject / Content ===== --}}
  @if($museum->scope_and_content || $museum->style_period || $museum->cultural_context || $museum->subject_display || $museum->historical_context || $museum->architectural_context || $museum->archaeological_context || $subjects->isNotEmpty() || $places->isNotEmpty())
    <section id="subjectContentArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#subjectContent-collapse">
          Subject / Content
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#subjectContent-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Subject / Content">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="subjectContent-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Edition / State ===== --}}
  @if($museum->edition_description || $museum->edition_number || $museum->edition_size || $museum->state_identification || $museum->state_description)
    <section id="editionStateArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#editionState-collapse">
          Edition / State
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#editionState-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Edition / State">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="editionState-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Inscriptions ===== --}}
  @if($museum->inscription || $museum->inscriptions || $museum->inscription_transcription || $museum->inscription_type || $museum->inscription_location || $museum->mark_type || $museum->mark_description)
    <section id="inscriptionsArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#inscriptions-collapse">
          Inscriptions
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#inscriptions-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Inscriptions">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="inscriptions-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Condition ===== --}}
  @if($museum->condition_term || $museum->condition_date || $museum->condition_description || $museum->condition_notes || $museum->treatment_type || $museum->treatment_description)
    <section id="conditionArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#condition-collapse">
          Condition
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#condition-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Condition">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="condition-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Provenance / Location ===== --}}
  @if($museum->provenance || $museum->provenance_text || $museum->ownership_history || $museum->current_location || $museum->legal_status || $museum->rights_type || $museum->rights_holder || $repository)
    <section id="provenanceLocationArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#provenanceLocation-collapse">
          Provenance / Location
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#provenanceLocation-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Provenance / Location">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="provenanceLocation-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Related Works ===== --}}
  @if($museum->related_work_type || $museum->related_work_relationship || $museum->related_work_label || $museum->related_work_id)
    <section id="relatedWorksArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#relatedWorks-collapse">
          Related Works
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#relatedWorks-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Related Works">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="relatedWorks-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Cataloging ===== --}}
  @if($museum->cataloger_name || $museum->cataloging_date || $museum->cataloging_institution || $museum->cataloging_remarks)
    <section id="catalogingArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#cataloging-collapse">
          Cataloging
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#cataloging-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Cataloging">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="cataloging-collapse">

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
      </div>
    </section>
  @endif

  {{-- ===== Record Info ===== --}}
  <section id="recordInfoArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#recordInfo-collapse">
        Record Info
      </a>
      @auth
        <a href="{{ route('museum.edit', $museum->slug) }}#recordInfo-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="Edit Record Info">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="recordInfo-collapse">

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
    </div>
  </section>

  @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $museum, 'recordType' => 'record'])
  @endif

  </div> {{-- /#tts-content-area --}}
  @endif {{-- end ric_view_mode toggle --}}
@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Extras                                         --}}
{{-- ============================================================ --}}
@section('right')

  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects ?? []])

  <div class="d-flex gap-1 mb-3">
    <button class="btn btn-sm atom-btn-white" onclick="window.print()" title="Print">
      <i class="fas fa-print"></i>
    </button>
    @include('ahg-core::clipboard._button', ['slug' => $museum->slug, 'type' => 'informationObject'])
  </div>

  @include('ahg-core::partials._record-sidebar-extras', ['objectId' => $museum->id, 'slug' => $museum->slug, 'title' => $museum->title])

@endsection

@section('after-content')
  @include('ahg-core::partials._ner-modal', ['objectId' => $museum->id, 'objectTitle' => $museum->title])
@endsection
