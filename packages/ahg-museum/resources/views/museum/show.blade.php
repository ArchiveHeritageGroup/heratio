@extends('theme::layouts.3col')

@section('title', ($museum->title ?? 'Museum object'))
@section('body-class', 'view museum')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR — full AtoM contextMenu equivalent (cloned from --}}
{{-- IO show; museum objects are IOs so io.* routes resolve)      --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Repository logo --}}
  @if(isset($repository) && $repository)
    @php
      $repoLogoPath = null;
      $repoDigitalObject = \Illuminate\Support\Facades\DB::table('digital_object')
        ->where('object_id', $repository->id)
        ->first();
      if ($repoDigitalObject) {
        $repoLogoPath = \AhgCore\Services\DigitalObjectService::getUrl($repoDigitalObject);
      }
    @endphp
    <div class="text-center mb-3">
      @if($repoLogoPath)
        <a href="{{ route('repository.show', $repository->slug) }}">
          <img src="{{ $repoLogoPath }}" alt="{{ $repository->name }}" class="img-fluid" style="max-height:80px;">
        </a>
      @else
        <a href="{{ route('repository.show', $repository->slug) }}" class="text-decoration-none">
          <strong>{{ $repository->name }}</strong>
        </a>
      @endif
    </div>
  @endif

  {{-- Static pages menu --}}
  @include('ahg-menu-manage::_static-pages-menu')

  {{-- Dynamic treeview hierarchy --}}
  @include('ahg-io-manage::partials._treeview', ['io' => $museum])

  {{-- Quick search within this collection --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-search me-1"></i> {{ __('Search within') }}
    </div>
    <div class="card-body p-2">
      <form action="{{ route('informationobject.browse') }}" method="GET">
        <input type="hidden" name="collection" value="{{ $museum->id }}">
        <div class="input-group input-group-sm">
          <input type="text" name="subquery" class="form-control" placeholder="{{ __('Search...') }}">
          <button class="btn atom-btn-white" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  @auth

    {{-- Collections Management --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ProvenanceController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-archive me-1"></i> {{ __('Collections Management') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.provenance', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-project-diagram me-1"></i> {{ __('Provenance') }}
        </a>
        <a href="{{ route('io.condition', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Condition assessment') }}
        </a>
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgSpectrumPlugin'))
        <a href="{{ route('io.spectrum', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-chart-bar me-1"></i> {{ __('Spectrum data') }}
        </a>
        @endif
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgHeritageAccountingPlugin'))
        <a href="{{ route('io.heritage', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-landmark me-1"></i> {{ __('Heritage Assets') }}
        </a>
        @endif
        <a href="{{ route('io.research.citation', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-quote-left me-1"></i> {{ __('Cite this Record') }}
        </a>
      </div>
    </div>
    @endif

    {{-- Digital Preservation (OAIS) --}}
    @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgPreservationPlugin'))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-shield-alt me-1"></i> {{ __('Digital Preservation (OAIS)') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.preservation', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-box-open me-1"></i> {{ __('Preservation packages') }}
        </a>
      </div>
    </div>
    @endif

    {{-- AI Tools --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\AiController::class) && \AhgCore\Services\MenuService::isPluginEnabled('ahgAIPlugin'))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-robot me-1"></i> {{ __('AI Tools') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#describeModal">
          <i class="fas fa-eye me-1"></i> {{ __('Describe Object/Image') }}
        </a>
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#nerModal">
          <i class="fas fa-brain me-1"></i> {{ __('Extract Entities (NER)') }}
        </a>
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#summaryModal">
          <i class="fas fa-file-alt me-1"></i> {{ __('Generate Summary') }}
        </a>
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#ahgTranslateModal-{{ $museum->id }}">
          <i class="fas fa-language me-1"></i> {{ __('Translate') }}
        </a>
        <a href="{{ route('io.ai.review') }}?object_id={{ $museum->id }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list-check me-1"></i> {{ __('NER Review') }}
        </a>
      </div>
    </div>
    @endif

    {{-- Privacy & PII --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\PrivacyController::class) && \AhgCore\Services\MenuService::isPluginEnabled('ahgPrivacyPlugin'))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-user-shield me-1"></i> {{ __('Privacy & PII') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.privacy.scan', $museum->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-search me-1"></i> {{ __('Scan for PII') }}
        </a>
        @if(isset($digitalObjects) && $digitalObjects['master'])
          <a href="{{ route('io.privacy.redaction', $museum->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-eraser me-1"></i> {{ __('Visual Redaction') }}
          </a>
        @endif
        <a href="{{ route('io.privacy.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Privacy Dashboard') }}
        </a>
      </div>
    </div>
    @endif

    {{-- Rights --}}
    @php
      $hasExtRights = \Illuminate\Support\Facades\Schema::hasTable('extended_rights')
          && \Illuminate\Support\Facades\DB::table('extended_rights')->where('object_id', $museum->id)->exists();
      $activeEmbargoSidebar = \Illuminate\Support\Facades\Schema::hasTable('embargo')
          ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $museum->id)->where('is_active', 1)->first()
          : null;
    @endphp
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff;">
        <i class="fas fa-copyright me-1"></i> {{ __('Rights') }}
      </div>
      <div class="card-body py-2">
        @if($hasExtRights)
          <span class="badge bg-success me-1"><i class="fas fa-check-circle me-1"></i>{{ __('Extended rights applied') }}</span>
        @endif
        @if($activeEmbargoSidebar)
          <span class="badge bg-danger me-1"><i class="fas fa-ban me-1"></i>{{ __('Under embargo') }}</span>
        @endif
        @if(!$hasExtRights && !$activeEmbargoSidebar)
          <span class="badge bg-secondary"><i class="fas fa-info-circle me-1"></i>{{ __('No extended rights or embargo') }}</span>
        @endif
      </div>
      <div class="list-group list-group-flush">
        @if(\Illuminate\Support\Facades\Route::has('io.rights.manage'))
          <a href="{{ route('io.rights.manage', $museum->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-copyright me-1"></i> {{ ($hasExtRights || $activeEmbargoSidebar) ? __('Edit rights') : __('Add rights') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.extended'))
          <a href="{{ route('io.rights.extended', $museum->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-shield-alt me-1"></i> {{ $hasExtRights ? __('Edit extended rights') : __('Add extended rights') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.embargo'))
          <a href="{{ route('io.rights.embargo', $museum->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-ban me-1"></i> {{ $activeEmbargoSidebar ? __('Manage embargo') : __('Add embargo') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.export'))
          <a href="{{ route('io.rights.export', $museum->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-download me-1"></i> {{ __('Export rights (JSON-LD)') }}
          </a>
        @endif
      </div>
    </div>

    {{-- Research Tools --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ResearchController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-graduation-cap me-1"></i> {{ __('Research Tools') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.research.assessment', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Source Assessment') }}
        </a>
        <a href="{{ route('io.research.annotations', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-highlighter me-1"></i> {{ __('Annotation Studio') }}
        </a>
        <a href="{{ route('io.research.trust', $museum->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-star-half-alt me-1"></i> {{ __('Trust Score') }}
        </a>
        <a href="{{ route('io.research.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-graduation-cap me-1"></i> {{ __('Research Dashboard') }}
        </a>
      </div>
    </div>
    @endif

  @endauth

  {{-- Access points (subject / name / place) — sidebar mode --}}
  @include('ahg-core::_subject-access-points', ['resource' => $museum, 'sidebar' => true])
  @include('ahg-core::_place-access-points', ['resource' => $museum, 'sidebar' => true])
  @include('ahg-core::_name-access-points', ['resource' => $museum, 'sidebar' => true])

@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- ============================================================ --}}
@section('title-block')

  {{-- Validation errors --}}
  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="list-unstyled mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Description header: Work type / Identifier - Title --}}
  <h1 class="mb-2">
    @if($museum->work_type)<span class="text-muted">{{ $museum->work_type }}</span> @endif
    @if($museum->identifier){{ $museum->identifier }} - @endif
    {{ $museum->title ?: '[Untitled]' }}
    {{-- ICIP cultural-sensitivity badge (issue #36 Phase 2b) — visible to anyone
         who can see the title; lets viewers know up-front whether the item
         carries access restrictions before they engage with it. --}}
    @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $museum->icip_sensitivity ?? null])
  </h1>

  {{-- Breadcrumb trail --}}
  @if($museum->parent_id != 1 && !empty($breadcrumbs))
    <nav aria-label="{{ __('Hierarchy') }}">
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

  {{-- Publication status badge (auth only) --}}
  @auth
    @if($publicationStatus)
      <span class="badge {{ (isset($publicationStatusId) && $publicationStatusId == 159) ? 'bg-warning text-dark' : 'bg-info' }} mb-2">{{ $publicationStatus }}</span>
    @endif
  @endauth

  {{-- Translation links (other cultures available) --}}
  @if(isset($translationLinks) && !empty($translationLinks) && \AhgCore\Services\MenuService::isPluginEnabled('ahgTranslationPlugin'))
    <div class="dropdown d-inline-block mb-3 translation-links">
      <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" id="translation-links-button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-globe-europe me-1" aria-hidden="true"></i>
        {{ __('Other languages available') }}
      </button>
      <ul class="dropdown-menu mt-2" aria-labelledby="translation-links-button">
        @foreach($translationLinks as $code => $translation)
          <li>
            <a class="dropdown-item" href="{{ route('museum.show', $museum->slug) }}?sf_culture={{ $code }}">
              {{ $translation['language'] }} &raquo; {{ $translation['name'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

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

  {{-- Translation-provenance bulk-load for AI-disclosure badges (issue #36 Phase 4) --}}
  @php
    $translationSources = \AhgTranslation\Helpers\TranslationProvenance::forRecord(
        (int) $museum->id,
        app()->getLocale()
    );
  @endphp

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
            title="{{ __('Read metadata aloud') }}" data-bs-toggle="tooltip">
      <i class="fas fa-volume-up"></i>
    </button>

    {{-- TTS PDF: read PDF content aloud (when a PDF derivative exists) --}}
    @if($pdfDigitalObject)
      <button type="button" class="btn btn-sm btn-outline-info"
              data-tts-action="read-pdf" data-tts-pdf-id="{{ $pdfDigitalObject->id }}"
              title="{{ __('Read PDF content aloud') }}" data-bs-toggle="tooltip">
        <i class="fas fa-file-pdf"></i>
      </button>
    @endif

    {{-- Favourites — @auth + plugin gate --}}
    @auth
      @if($favouritesEnabled)
        @if($favoriteId)
          <form method="POST" action="{{ route('favorites.remove', $favoriteId) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove from Favorites') }}" data-bs-toggle="tooltip">
              <i class="fas fa-heart-broken"></i>
            </button>
          </form>
        @else
          <a href="{{ route('favorites.add', $museum->slug) }}" class="btn btn-sm btn-outline-danger" title="{{ __('Add to Favorites') }}" data-bs-toggle="tooltip">
            <i class="fas fa-heart"></i>
          </a>
        @endif
      @endif
    @endauth

    {{-- Feedback — public, plugin-gated --}}
    @if($feedbackEnabled)
      <a href="{{ url('/feedback/submit/' . $museum->slug) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Item Feedback') }}" data-bs-toggle="tooltip">
        <i class="fas fa-comment"></i>
      </a>
    @endif

    {{-- Request to Publish — public, plugin-gated, requires DO --}}
    @if($requestToPublishEnabled && $hasDigitalObject)
      <a href="{{ url('/request-to-publish/' . $museum->slug) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Request to Publish') }}" data-bs-toggle="tooltip">
        <i class="fas fa-paper-plane"></i>
      </a>
    @endif

    {{-- Cart — public, plugin-gated, requires DO --}}
    @if($cartEnabled && $hasDigitalObject)
      @if($cartId)
        <a href="{{ route('cart.browse') }}" class="btn btn-sm btn-outline-success" title="{{ __('Go to Cart') }}" data-bs-toggle="tooltip">
          <i class="fas fa-shopping-cart"></i>
        </a>
      @else
        <a href="{{ route('cart.add', $museum->slug) }}" class="btn btn-sm btn-outline-success" title="{{ __('Add to Cart') }}" data-bs-toggle="tooltip">
          <i class="fas fa-cart-plus"></i>
        </a>
      @endif
    @endif

    {{-- Loan — @auth + plugin gate --}}
    @auth
      @if($loanEnabled)
        <a href="{{ route('loan.create', ['type' => 'out', 'sector' => 'museum', 'object_id' => $museum->id]) }}" class="btn btn-sm btn-outline-warning" title="{{ __('New Loan') }}" data-bs-toggle="tooltip">
          <i class="fas fa-hand-holding"></i>
        </a>
        <a href="{{ route('loan.index', ['sector' => 'museum', 'object_id' => $museum->id]) }}" class="btn btn-sm btn-outline-info" title="{{ __('Manage Loans') }}" data-bs-toggle="tooltip">
          <i class="fas fa-exchange-alt"></i>
        </a>
      @endif
    @endauth
  </div>

  {{-- TTS content area: everything below this is read aloud when TTS toggles on --}}
  <div id="tts-content-area" data-tts-content>

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-museum', ['museum' => $museum])
  @else

  {{-- ===== Object Identification ===== --}}
  <section id="objectIdentificationArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#objectIdentification-collapse">
        {{ __('Object Identification') }}
      </a>
      @auth
        <a href="{{ route('museum.edit', $museum->slug) }}#objectIdentification-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Object Identification') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="objectIdentification-collapse">

    @if($museum->work_type)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Work type') }}</h3>
        <div>{{ $museum->work_type }}</div>
      </div>
    @endif

    @if($museum->object_type)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Object type') }}</h3>
        <div>{{ $museum->object_type }}</div>
      </div>
    @endif

    @if($museum->classification)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Classification') }}</h3>
        <div>{{ $museum->classification }}</div>
      </div>
    @endif

    @if($museum->object_class)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Object class') }}</h3>
        <div>{{ $museum->object_class }}</div>
      </div>
    @endif

    @if($museum->object_category)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Object category') }}</h3>
        <div>{{ $museum->object_category }}</div>
      </div>
    @endif

    @if($museum->object_sub_category)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Object sub-category') }}</h3>
        <div>{{ $museum->object_sub_category }}</div>
      </div>
    @endif

    @if($museum->identifier)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Identifier') }}</h3>
        <div>{{ $museum->identifier }}</div>
      </div>
    @endif

    @if($museum->record_type)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Record type') }}</h3>
        <div>{{ $museum->record_type }}</div>
      </div>
    @endif

    @if($museum->record_level)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Record level') }}</h3>
        <div>{{ $museum->record_level }}</div>
      </div>
    @endif

    @if($levelName)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Level of description') }}</h3>
        <div>{{ $levelName }}</div>
      </div>
    @endif
    </div>
  </section>

  {{-- ===== Title ===== --}}
  <section id="titleArea" class="border-bottom">
    <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <a class="text-decoration-none text-white" href="#title-collapse">
        {{ __('Title') }}
      </a>
      @auth
        <a href="{{ route('museum.edit', $museum->slug) }}#title-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Title') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="title-collapse">

    <div class="field text-break row g-0">
      <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Title') }}</h3>
      <div>{{ $museum->title ?: '[Untitled]' }}@include('ahg-translation::components.badge', ['source' => $translationSources['title'] ?? null])</div>
    </div>

    @if($museum->alternate_title)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Alternate title') }}</h3>
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
          {{ __('Creator') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#creator-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Creator') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="creator-collapse">

      @if($museum->creator_identity)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator identity') }}</h3>
          <div>{{ $museum->creator_identity }}</div>
        </div>
      @endif

      @if($museum->creator_role)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator role') }}</h3>
          <div>{{ $museum->creator_role }}</div>
        </div>
      @endif

      @if($museum->creator_extent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator extent') }}</h3>
          <div>{{ $museum->creator_extent }}</div>
        </div>
      @endif

      @if($museum->creator_qualifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator qualifier') }}</h3>
          <div>{{ $museum->creator_qualifier }}</div>
        </div>
      @endif

      @if($museum->creator_attribution)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator attribution') }}</h3>
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
          {{ __('Creation') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#creation-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Creation') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="creation-collapse">

      @if($museum->creation_date_display)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creation date') }}</h3>
          <div>{{ $museum->creation_date_display }}</div>
        </div>
      @endif

      @if($museum->creation_date_earliest || $museum->creation_date_latest)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date range') }}</h3>
          <div>
            @if($museum->creation_date_earliest){{ $museum->creation_date_earliest }}@endif
            @if($museum->creation_date_earliest && $museum->creation_date_latest) &ndash; @endif
            @if($museum->creation_date_latest){{ $museum->creation_date_latest }}@endif
          </div>
        </div>
      @endif

      @if($museum->creation_date_qualifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date qualifier') }}</h3>
          <div>{{ $museum->creation_date_qualifier }}</div>
        </div>
      @endif

      @if($museum->creation_place)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creation place') }}</h3>
          <div>{{ $museum->creation_place }}</div>
        </div>
      @endif

      @if($museum->creation_place_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creation place type') }}</h3>
          <div>{{ $museum->creation_place_type }}</div>
        </div>
      @endif

      @if($museum->discovery_place)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Discovery place') }}</h3>
          <div>{{ $museum->discovery_place }}</div>
        </div>
      @endif

      @if($museum->discovery_place_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Discovery place type') }}</h3>
          <div>{{ $museum->discovery_place_type }}</div>
        </div>
      @endif

      @if($museum->style)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Style') }}</h3>
          <div>{{ $museum->style }}</div>
        </div>
      @endif

      @if($museum->period)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Period') }}</h3>
          <div>{{ $museum->period }}</div>
        </div>
      @endif

      @if($museum->cultural_group)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cultural group') }}</h3>
          <div>{{ $museum->cultural_group }}</div>
        </div>
      @endif

      @if($museum->movement)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Movement') }}</h3>
          <div>{{ $museum->movement }}</div>
        </div>
      @endif

      @if($museum->school)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('School') }}</h3>
          <div>{{ $museum->school }}</div>
        </div>
      @endif

      @if($museum->dynasty)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dynasty') }}</h3>
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
          {{ __('Measurements') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#measurements-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Measurements') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="measurements-collapse">

      @if($museum->measurements)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Measurements') }}</h3>
          <div>{{ $museum->measurements }}</div>
        </div>
      @endif

      @if($museum->dimensions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Dimensions') }}</h3>
          <div>{{ $museum->dimensions }}</div>
        </div>
      @endif

      @if($museum->orientation)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Orientation') }}</h3>
          <div>{{ $museum->orientation }}</div>
        </div>
      @endif

      @if($museum->shape)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Shape') }}</h3>
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
          {{ __('Materials / Techniques') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#materials-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Materials / Techniques') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="materials-collapse">

      @if($museum->materials)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Materials') }}</h3>
          <div>{!! nl2br(e($museum->materials)) !!}</div>
        </div>
      @endif

      @if($museum->techniques)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Techniques') }}</h3>
          <div>{!! nl2br(e($museum->techniques)) !!}</div>
        </div>
      @endif

      @if($museum->technique_cco)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Technique (CCO)') }}</h3>
          <div>{{ $museum->technique_cco }}</div>
        </div>
      @endif

      @if($museum->technique_qualifier)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Technique qualifier') }}</h3>
          <div>{{ $museum->technique_qualifier }}</div>
        </div>
      @endif

      @if($museum->facture_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Facture description') }}</h3>
          <div>{!! nl2br(e($museum->facture_description)) !!}</div>
        </div>
      @endif

      @if($museum->color)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Color') }}</h3>
          <div>{{ $museum->color }}</div>
        </div>
      @endif

      @if($museum->physical_appearance)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Physical appearance') }}</h3>
          <div>{!! nl2br(e($museum->physical_appearance)) !!}</div>
        </div>
      @endif

      @if($museum->extent_and_medium)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Extent and medium') }}</h3>
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
          {{ __('Subject / Content') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#subjectContent-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Subject / Content') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="subjectContent-collapse">

      @if($museum->scope_and_content)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Scope and content') }}</h3>
          <div>{!! nl2br(e($museum->scope_and_content)) !!}@include('ahg-translation::components.badge', ['source' => $translationSources['scope_and_content'] ?? null])</div>
        </div>
      @endif

      @if($museum->style_period)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Style / Period') }}</h3>
          <div>{{ $museum->style_period }}</div>
        </div>
      @endif

      @if($museum->cultural_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cultural context') }}</h3>
          <div>{{ $museum->cultural_context }}</div>
        </div>
      @endif

      @if($museum->subject_indexing_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject indexing type') }}</h3>
          <div>{{ $museum->subject_indexing_type }}</div>
        </div>
      @endif

      @if($museum->subject_display)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject display') }}</h3>
          <div>{!! nl2br(e($museum->subject_display)) !!}</div>
        </div>
      @endif

      @if($museum->subject_extent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject extent') }}</h3>
          <div>{{ $museum->subject_extent }}</div>
        </div>
      @endif

      @if($museum->historical_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Historical context') }}</h3>
          <div>{!! nl2br(e($museum->historical_context)) !!}</div>
        </div>
      @endif

      @if($museum->architectural_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Architectural context') }}</h3>
          <div>{!! nl2br(e($museum->architectural_context)) !!}</div>
        </div>
      @endif

      @if($museum->archaeological_context)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Archaeological context') }}</h3>
          <div>{!! nl2br(e($museum->archaeological_context)) !!}</div>
        </div>
      @endif

      @if($subjects->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subject access points') }}</h3>
          <div>
            @foreach($subjects as $subject)
              <span class="badge bg-secondary me-1">{{ $subject->name }}</span>
            @endforeach
          </div>
        </div>
      @endif

      @if($places->isNotEmpty())
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Place access points') }}</h3>
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
          {{ __('Edition / State') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#editionState-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Edition / State') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="editionState-collapse">

      @if($museum->edition_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Edition description') }}</h3>
          <div>{!! nl2br(e($museum->edition_description)) !!}</div>
        </div>
      @endif

      @if($museum->edition_number)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Edition number') }}</h3>
          <div>{{ $museum->edition_number }}</div>
        </div>
      @endif

      @if($museum->edition_size)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Edition size') }}</h3>
          <div>{{ $museum->edition_size }}</div>
        </div>
      @endif

      @if($museum->state_identification)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('State identification') }}</h3>
          <div>{{ $museum->state_identification }}</div>
        </div>
      @endif

      @if($museum->state_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('State description') }}</h3>
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
          {{ __('Inscriptions') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#inscriptions-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Inscriptions') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="inscriptions-collapse">

      @if($museum->inscription)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription') }}</h3>
          <div>{!! nl2br(e($museum->inscription)) !!}</div>
        </div>
      @endif

      @if($museum->inscriptions)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscriptions (additional)') }}</h3>
          <div>{!! nl2br(e($museum->inscriptions)) !!}</div>
        </div>
      @endif

      @if($museum->inscription_transcription)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription transcription') }}</h3>
          <div>{!! nl2br(e($museum->inscription_transcription)) !!}</div>
        </div>
      @endif

      @if($museum->inscription_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription type') }}</h3>
          <div>{{ $museum->inscription_type }}</div>
        </div>
      @endif

      @if($museum->inscription_location)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription location') }}</h3>
          <div>{{ $museum->inscription_location }}</div>
        </div>
      @endif

      @if($museum->inscription_language)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription language') }}</h3>
          <div>{{ $museum->inscription_language }}</div>
        </div>
      @endif

      @if($museum->inscription_translation)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Inscription translation') }}</h3>
          <div>{!! nl2br(e($museum->inscription_translation)) !!}</div>
        </div>
      @endif

      @if($museum->mark_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Mark type') }}</h3>
          <div>{{ $museum->mark_type }}</div>
        </div>
      @endif

      @if($museum->mark_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Mark description') }}</h3>
          <div>{!! nl2br(e($museum->mark_description)) !!}</div>
        </div>
      @endif

      @if($museum->mark_location)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Mark location') }}</h3>
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
          {{ __('Condition') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#condition-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Condition') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="condition-collapse">

      @if($museum->condition_term)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition term') }}</h3>
          <div>{{ $museum->condition_term }}</div>
        </div>
      @endif

      @if($museum->condition_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition date') }}</h3>
          <div>{{ $museum->condition_date }}</div>
        </div>
      @endif

      @if($museum->condition_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition description') }}</h3>
          <div>{!! nl2br(e($museum->condition_description)) !!}</div>
        </div>
      @endif

      @if($museum->condition_notes)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition notes') }}</h3>
          <div>{!! nl2br(e($museum->condition_notes)) !!}</div>
        </div>
      @endif

      @if($museum->condition_agent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Condition agent') }}</h3>
          <div>{{ $museum->condition_agent }}</div>
        </div>
      @endif

      @if($museum->treatment_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Treatment type') }}</h3>
          <div>{{ $museum->treatment_type }}</div>
        </div>
      @endif

      @if($museum->treatment_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Treatment date') }}</h3>
          <div>{{ $museum->treatment_date }}</div>
        </div>
      @endif

      @if($museum->treatment_agent)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Treatment agent') }}</h3>
          <div>{{ $museum->treatment_agent }}</div>
        </div>
      @endif

      @if($museum->treatment_description)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Treatment description') }}</h3>
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
          {{ __('Provenance / Location') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#provenanceLocation-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Provenance / Location') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="provenanceLocation-collapse">

      @if($museum->provenance)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Provenance') }}</h3>
          <div>{!! nl2br(e($museum->provenance)) !!}</div>
        </div>
      @endif

      @if($museum->provenance_text)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Provenance text') }}</h3>
          <div>{!! nl2br(e($museum->provenance_text)) !!}</div>
        </div>
      @endif

      @if($museum->ownership_history)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Ownership history') }}</h3>
          <div>{!! nl2br(e($museum->ownership_history)) !!}</div>
        </div>
      @endif

      @if($museum->current_location)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location') }}</h3>
          <div>{!! nl2br(e($museum->current_location)) !!}</div>
        </div>
      @endif

      @if($museum->current_location_repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location repository') }}</h3>
          <div>{{ $museum->current_location_repository }}</div>
        </div>
      @endif

      @if($museum->current_location_geography)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location geography') }}</h3>
          <div>{{ $museum->current_location_geography }}</div>
        </div>
      @endif

      @if($museum->current_location_coordinates)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location coordinates') }}</h3>
          <div>{{ $museum->current_location_coordinates }}</div>
        </div>
      @endif

      @if($museum->current_location_ref_number)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Current location reference number') }}</h3>
          <div>{{ $museum->current_location_ref_number }}</div>
        </div>
      @endif

      @if($museum->legal_status)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Legal status') }}</h3>
          <div>{{ $museum->legal_status }}</div>
        </div>
      @endif

      @if($museum->rights_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights type') }}</h3>
          <div>{{ $museum->rights_type }}</div>
        </div>
      @endif

      @if($museum->rights_holder)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights holder') }}</h3>
          <div>{{ $museum->rights_holder }}</div>
        </div>
      @endif

      @if($museum->rights_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights date') }}</h3>
          <div>{{ $museum->rights_date }}</div>
        </div>
      @endif

      @if($museum->rights_remarks)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights remarks') }}</h3>
          <div>{!! nl2br(e($museum->rights_remarks)) !!}</div>
        </div>
      @endif

      @if($repository)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Repository') }}</h3>
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
          {{ __('Related Works') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#relatedWorks-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Related Works') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="relatedWorks-collapse">

      @if($museum->related_work_type)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related work type') }}</h3>
          <div>{{ $museum->related_work_type }}</div>
        </div>
      @endif

      @if($museum->related_work_relationship)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related work relationship') }}</h3>
          <div>{{ $museum->related_work_relationship }}</div>
        </div>
      @endif

      @if($museum->related_work_label)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related work label') }}</h3>
          <div>{{ $museum->related_work_label }}</div>
        </div>
      @endif

      @if($museum->related_work_id)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Related work identifier') }}</h3>
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
          {{ __('Cataloging') }}
        </a>
        @auth
          <a href="{{ route('museum.edit', $museum->slug) }}#cataloging-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Cataloging') }}">
            <i class="fas fa-pencil-alt"></i>
          </a>
        @endauth
      </h2>
      <div id="cataloging-collapse">

      @if($museum->cataloger_name)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloger name') }}</h3>
          <div>{{ $museum->cataloger_name }}</div>
        </div>
      @endif

      @if($museum->cataloging_date)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloging date') }}</h3>
          <div>{{ $museum->cataloging_date }}</div>
        </div>
      @endif

      @if($museum->cataloging_institution)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloging institution') }}</h3>
          <div>{{ $museum->cataloging_institution }}</div>
        </div>
      @endif

      @if($museum->cataloging_remarks)
        <div class="field text-break row g-0">
          <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Cataloging remarks') }}</h3>
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
        {{ __('Record Info') }}
      </a>
      @auth
        <a href="{{ route('museum.edit', $museum->slug) }}#recordInfo-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Record Info') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      @endauth
    </h2>
    <div id="recordInfo-collapse">

    @if($museum->created_at)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Created') }}</h3>
        <div>{{ \Carbon\Carbon::parse($museum->created_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif

    @if($museum->updated_at)
      <div class="field text-break row g-0">
        <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Last updated') }}</h3>
        <div>{{ \Carbon\Carbon::parse($museum->updated_at)->format('Y-m-d H:i') }}</div>
      </div>
    @endif
    </div>
  </section>

  @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
    @include('ahg-ric::_ric-entities-panel', ['record' => $museum, 'recordType' => 'record'])
  @endif

  @endif {{-- end ric_view_mode toggle --}}
  </div> {{-- /#tts-content-area --}}
@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR: Extras                                         --}}
{{-- ============================================================ --}}
@section('right')

  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects ?? []])

  <div class="d-flex gap-1 mb-3">
    <button class="btn btn-sm atom-btn-white" onclick="window.print()" title="{{ __('Print') }}">
      <i class="fas fa-print"></i>
    </button>
    @include('ahg-core::clipboard._button', ['slug' => $museum->slug, 'type' => 'informationObject'])
  </div>

  @include('ahg-core::partials._record-sidebar-extras', ['objectId' => $museum->id, 'slug' => $museum->slug, 'title' => $museum->title, 'hideNer' => true, 'hideRights' => true])

@endsection

@section('after-content')
  @auth
  @php $isAdmin = auth()->user()->is_admin; @endphp
  <ul class="actions mb-3 nav gap-2">
    {{-- Edit --}}
    <li>
      <a href="{{ route('museum.edit', $museum->slug) }}" class="btn atom-btn-outline-light">{{ __('Edit') }}</a>
    </li>

    {{-- Delete (admin only) --}}
    @if($isAdmin)
    <li>
      <form action="{{ route('museum.destroy', $museum->slug) }}" method="POST"
            onsubmit="return confirm('Are you sure you want to delete this museum object?');">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger">{{ __('Delete') }}</button>
      </form>
    </li>
    @endif

    {{-- Add new (creates a child museum object) --}}
    <li>
      <a href="{{ route('museum.create', ['parent_id' => $museum->id]) }}" class="btn atom-btn-outline-light">{{ __('Add new') }}</a>
    </li>

    {{-- Duplicate (pre-populated from this record) --}}
    <li>
      <a href="{{ route('museum.create', ['parent_id' => $museum->id, 'copy_from' => $museum->id]) }}" class="btn atom-btn-outline-light">{{ __('Duplicate') }}</a>
    </li>

    {{-- Move (admin only) --}}
    @if($isAdmin)
    <li>
      <a href="{{ url('/' . $museum->slug . '/default/move') }}" class="btn atom-btn-outline-light">{{ __('Move') }}</a>
    </li>
    @endif

    {{-- More dropdown --}}
    <li>
      <div class="dropup">
        <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          {{ __('More') }}
        </button>
        <ul class="dropdown-menu mb-2">
          @if(\Illuminate\Support\Facades\Route::has('informationobject.rename'))
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.rename', $museum->slug) }}">
              <i class="fas fa-i-cursor me-2"></i>{{ __('Rename') }}
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          @endif
          <li>
            <a class="dropdown-item" href="{{ route('museum.edit', ['slug' => $museum->slug, 'storage' => 1]) }}">
              <i class="fas fa-box me-2"></i>{{ __('Link physical storage') }}
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          @if(isset($digitalObjects) && ($digitalObjects['master'] ?? null))
            <li>
              <a class="dropdown-item" href="{{ route('io.digitalobject.show', $digitalObjects['master']->id) }}">
                <i class="fas fa-photo-video me-2"></i>{{ __('Edit digital object') }}
              </a>
            </li>
          @else
            <li>
              <a class="dropdown-item" href="{{ route('museum.edit', ['slug' => $museum->slug, 'upload' => 1]) }}">
                <i class="fas fa-link me-2"></i>{{ __('Link digital object') }}
              </a>
            </li>
          @endif
          <li>
            <a class="dropdown-item" href="{{ route('museum.multi-upload', $museum->slug) }}">
              <i class="fas fa-file-import me-2"></i>{{ __('Import digital objects') }}
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ url('/' . $museum->slug . '/right/edit') }}">
              <i class="fas fa-balance-scale me-2"></i>{{ __('Create new rights') }}
            </a>
          </li>
          @if(isset($hasChildren) && $hasChildren)
            <li>
              <a class="dropdown-item" href="{{ url('/' . $museum->slug . '/right/manage') }}">
                <i class="fas fa-sitemap me-2"></i>{{ __('Manage rights inheritance') }}
              </a>
            </li>
          @endif
          @if(\Illuminate\Support\Facades\Route::has('io.showUpdateStatus'))
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('io.showUpdateStatus', $museum->slug) }}">
              <i class="fas fa-eye me-2"></i>{{ __('Update publication status') }}
            </a>
          </li>
          @endif
          @if(isset($auditLogEnabled) && $auditLogEnabled)
          <li>
            <a class="dropdown-item" href="{{ route('audit.browse', ['type' => 'QubitInformationObject', 'id' => $museum->id]) }}">
              <i class="fas fa-history me-2"></i>{{ __('Modification history') }}
            </a>
          </li>
          @endif
          @auth
            @if(\Illuminate\Support\Facades\Route::has('ahgtranslation.translate'))
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateModal-{{ $museum->id }}">
                  <i class="fas fa-language me-2"></i>{{ __('Translate this record') }}
                </a>
              </li>
            @endif
          @endauth
        </ul>
      </div>
    </li>

    {{-- Print --}}
    @if(\Illuminate\Support\Facades\Route::has('informationobject.print'))
    <li>
      <a href="{{ route('informationobject.print', $museum->slug) }}" class="btn atom-btn-outline-light" target="_blank">
        <i class="fas fa-print me-1"></i>{{ __('Print') }}
      </a>
    </li>
    @endif
  </ul>
  @endauth

  @include('ahg-core::partials._ner-modal', ['objectId' => $museum->id, 'objectTitle' => $museum->title])

  @auth
    @if(view()->exists('ahg-translation::_translate-modal'))
      @include('ahg-translation::_translate-modal', ['objectId' => $museum->id])
    @endif
  @endauth
@endsection
