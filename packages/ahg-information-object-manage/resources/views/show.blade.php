@extends('theme::layouts.3col')

@section('title', ($io->title ?? config('app.ui_label_informationobject', 'Archival description')))
@section('body-class', 'view informationobject')

{{-- ============================================================ --}}
{{-- LEFT SIDEBAR: Treeview / Holdings + Quick search             --}}
{{-- ============================================================ --}}
@section('sidebar')

  {{-- Repository logo (matching AtoM context menu) --}}
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

  {{-- Static pages menu (visible to all users, matching AtoM context menu) --}}
  @include('ahg-menu-manage::_static-pages-menu')

  {{-- Dynamic treeview hierarchy --}}
  @include('ahg-io-manage::partials._treeview', ['io' => $io])

  {{-- Quick search within this collection --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-search me-1"></i> {{ __('Search within') }}
    </div>
    <div class="card-body p-2">
      <form action="{{ route('informationobject.browse') }}" method="GET">
        <input type="hidden" name="collection" value="{{ $io->id }}">
        <div class="input-group input-group-sm">
          <input type="text" name="subquery" class="form-control" placeholder="{{ __('Search...') }}">
          <button class="btn atom-btn-white" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- ===== Authenticated-only management sections ===== --}}
  @auth

    {{-- Collections Management (only if collections management controllers are available) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ProvenanceController::class) && \AhgCore\Services\AclService::check($io, 'update'))
    {{-- Collections Management --}}
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-archive me-1"></i> {{ __('Collections Management') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.provenance', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-project-diagram me-1"></i> {{ __('Provenance') }}
        </a>
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgConditionPlugin'))
        <a href="{{ route('io.condition', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Condition assessment') }}
        </a>
        @endif
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgSpectrumPlugin'))
        <a href="{{ route('io.spectrum', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-chart-bar me-1"></i> {{ __('Spectrum data') }}
        </a>
        @endif
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgHeritageAccountingPlugin'))
        <a href="{{ route('io.heritage', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-landmark me-1"></i> {{ __('Heritage Assets') }}
        </a>
        @endif
        <a href="{{ route('io.research.citation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-quote-left me-1"></i> {{ __('Cite this Record') }}
        </a>
      </div>
    </div>
    @endif {{-- end Collections Management package check --}}

    {{-- Digital Preservation (OAIS) — gated by ahgPreservationPlugin --}}
    @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgPreservationPlugin'))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-shield-alt me-1"></i> {{ __('Digital Preservation (OAIS)') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.preservation', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-box-open me-1"></i> {{ __('Preservation packages') }}
        </a>
      </div>
    </div>
    @endif

    {{-- AI Tools (only if AI controller is available, AI plugin enabled, and user is authenticated) --}}
    @auth
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
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#translateModal">
          <i class="fas fa-language me-1"></i> {{ __('AI Translate (MT wizard)') }}
        </a>
        <a href="{{ route('io.ai.review') }}?object_id={{ $io->id }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-list-check me-1"></i> {{ __('NER Review') }}
        </a>
        @if(isset($nerEntityCount) && $nerEntityCount > 0)
          <a href="{{ route('io.ai.extract', $io->id) }}#entities" class="list-group-item list-group-item-action small d-flex justify-content-between align-items-center">
            <span><i class="fas fa-file-pdf me-1"></i> {{ __('View PDF Entities') }}</span>
            <span class="badge bg-success rounded-pill">{{ $nerEntityCount }}</span>
          </a>
        @endif

        {{-- AI image-to-video (SVD on 8 GB; CogVideoX/WAN on 24 GB) ----------- --}}
        @if(class_exists(\AhgImageAr\Services\AnimationService::class)
            && app(\AhgImageAr\Services\AnimationService::class)->isEnabled()
            && \Illuminate\Support\Facades\DB::table('digital_object')
                  ->where('object_id', $io->id)->where('mime_type', 'like', 'image/%')->exists())
          @php
            $existingAnim = \Illuminate\Support\Facades\DB::table('object_image_ar')
              ->where('object_id', $io->id)->first(['id']);
            $aiDefaults = app(\AhgImageAr\Services\AnimationService::class)->defaults();
          @endphp
          <a class="list-group-item list-group-item-action small d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#aiAnimateForm" role="button">
            <span><i class="fas fa-magic me-1"></i> {{ $existingAnim ? __('Regenerate animation') : __('Animate image (AI)') }}</span>
            <i class="fas fa-chevron-down small text-muted"></i>
          </a>
          <div id="aiAnimateForm" class="collapse">
            <form action="{{ route('image-ar.generate', ['ioId' => $io->id]) }}" method="POST"
                  onsubmit="return confirm('Send to AI server ({{ $aiDefaults['server_url'] }})?\n\nModel: {{ $aiDefaults['model'] }}\nFrames: {{ $aiDefaults['num_frames'] }} @ {{ $aiDefaults['fps'] }} fps\n\nOn the 8 GB GPU this can take 3–8 minutes. The page will sit and wait.');"
                  class="p-2 border-top">
              @csrf
              <label class="form-label small mb-1">{{ __('Prompt (optional — used by CogVideoX/WAN, ignored by SVD)') }}</label>
              <textarea name="prompt" rows="2" class="form-control form-control-sm mb-2"
                        placeholder="{{ __('e.g. the elephant walks slowly forward, the egret on its back lifts off and flies right') }}">{{ $aiDefaults['prompt'] }}</textarea>
              <div class="row g-1 mb-2">
                <div class="col-6">
                  <label class="form-label small mb-0">{{ __('Frames') }}</label>
                  <input type="number" name="num_frames" value="{{ $aiDefaults['num_frames'] }}" min="8" max="25" class="form-control form-control-sm">
                </div>
                <div class="col-6">
                  <label class="form-label small mb-0">{{ __('Motion') }}</label>
                  <input type="number" name="motion_bucket_id" value="{{ $aiDefaults['motion_bucket_id'] }}" min="1" max="255" class="form-control form-control-sm" title="{{ __('SVD motion strength 1–255') }}">
                </div>
              </div>
              <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="fas fa-play me-1"></i>{{ __('Generate') }}
              </button>
              <div class="form-text small mt-1">
                <i class="fas fa-info-circle me-1"></i>{{ __('Model:') }} <code>{{ $aiDefaults['model'] }}</code>
              </div>
            </form>
          </div>
        @endif
      </div>
    </div>
    @endif {{-- end AI Tools package check --}}
    @endauth

    {{-- Privacy & PII (controller exists AND ahgPrivacyPlugin is enabled) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\PrivacyController::class) && \AhgCore\Services\MenuService::isPluginEnabled('ahgPrivacyPlugin'))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-user-shield me-1"></i> {{ __('Privacy & PII') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.privacy.scan', $io->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-search me-1"></i> {{ __('Scan for PII') }}
        </a>
        @if(isset($digitalObjects) && $digitalObjects['master'])
          <a href="{{ route('io.privacy.redaction', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-eraser me-1"></i> {{ __('Visual Redaction') }}
          </a>
        @endif
        <a href="{{ route('io.privacy.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Privacy Dashboard') }}
        </a>
      </div>
    </div>
    @endif {{-- end Privacy package check --}}

    {{-- Rights (matching library style) --}}
    @auth
    @if(\AhgCore\Services\AclService::check($io, 'update'))
    @php
      $hasExtRights = \Illuminate\Support\Facades\Schema::hasTable('extended_rights')
          && \Illuminate\Support\Facades\DB::table('extended_rights')->where('object_id', $io->id)->exists();
      $activeEmbargoSidebar = \Illuminate\Support\Facades\Schema::hasTable('embargo')
          ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $io->id)->where('is_active', 1)->first()
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
          <a href="{{ route('io.rights.manage', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-copyright me-1"></i> {{ ($hasExtRights || $activeEmbargoSidebar) ? __('Edit rights') : __('Add rights') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.export'))
          <a href="{{ route('io.rights.export', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-download me-1"></i> {{ __('Export rights (JSON-LD)') }}
          </a>
        @endif
      </div>
    </div>
    @endif {{-- end Rights admin-only --}}
    @endauth

    {{-- Research Tools (only if research controller is available) --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ResearchController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-graduation-cap me-1"></i> {{ __('Research Tools') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.research.assessment', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Source Assessment') }}
        </a>
        <a href="{{ route('io.research.annotations', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-highlighter me-1"></i> {{ __('Annotation Studio') }}
        </a>
        <a href="{{ route('io.research.trust', $io->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-star-half-alt me-1"></i> {{ __('Trust Score') }}
        </a>
        <a href="{{ route('io.research.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-graduation-cap me-1"></i> {{ __('Research Dashboard') }}
        </a>
      </div>
    </div>
    @endif {{-- end Research Tools package check --}}

  @endauth


@endsection

{{-- ============================================================ --}}
{{-- TITLE BLOCK                                                  --}}
{{-- ============================================================ --}}
@section('title-block')

  {{-- Validation errors / error schema (matching AtoM errorSchema display) --}}
  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="list-unstyled mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Description header: Level Identifier - Title --}}
  <h1 class="mb-2">
    @if($levelName)<span class="text-muted">{{ $levelName }}</span>@endif
    @if($io->identifier){{ $io->identifier }} - @endif
    {{ $io->title ?: '[Untitled]' }}
    {{-- ICIP cultural-sensitivity badge (issue #36 Phase 2b) — visible to anyone
         who can see the title; lets viewers know up-front whether the item
         carries access restrictions before they engage with it. --}}
    @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $io->icip_sensitivity ?? null])
  </h1>

  {{-- OCAP® overlay badges (rendered only if overlay enabled and the IO has ICIP signal) --}}
  @if(view()->exists('icip::partials.ocap-badges'))
    @include('icip::partials.ocap-badges', ['ioId' => $io->id])
  @endif

  {{-- PSIS-parity PII redaction badge: warns the viewer that one or more
       text values have been masked. Admin viewers bypass masking entirely so
       the service short-circuits and we don't render the badge for them. --}}
  @if(\AhgInformationObjectManage\Services\PiiMaskingService::hasRedactedPii((int) $io->id) && !\AhgInformationObjectManage\Services\PiiMaskingService::canBypassMasking())
    <div class="alert alert-warning py-1 px-2 mb-2 d-inline-block small">
      <i class="fas fa-shield-alt me-1"></i>
      {{ __('This record contains redacted PII') }}
    </div>
  @endif

  {{-- Breadcrumb trail --}}
  @if($io->parent_id != 1 && !empty($breadcrumbs))
    <nav aria-label="{{ __('Hierarchy') }}">
      <ol class="breadcrumb">
        @foreach($breadcrumbs as $crumb)
          <li class="breadcrumb-item">
            <a href="{{ route('informationobject.show', $crumb->slug) }}">
              {{ $crumb->title ?: '[Untitled]' }}
            </a>
          </li>
        @endforeach
        <li class="breadcrumb-item active" aria-current="page">
          {{ $io->title ?: '[Untitled]' }}
        </li>
      </ol>
    </nav>
  @endif

  {{-- Publication status badge (authenticated only) --}}
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
            <a class="dropdown-item" href="{{ route('informationobject.show', $io->slug) }}?sf_culture={{ $code }}">
              {{ $translation['language'] }} &raquo; {{ $translation['name'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- BEFORE CONTENT: Digital object reference image               --}}
{{-- ============================================================ --}}
@section('before-content')
  @include('ahg-information-object-manage::partials._redaction-overlay')
  @include('ahg-information-object-manage::partials._digital-object-viewer')
@endsection


{{-- ============================================================ --}}
{{-- MAIN CONTENT: ISAD(G) sections                              --}}
{{-- ============================================================ --}}
@section('content')

  @include('ahg-ric::_view-switch', ['standard' => 'ISAD(G)'])

  {{-- Bulk-load translation provenance for this record + current culture so
       per-field AI-disclosure badges render without N+1 lookups. See issue
       #36 Phase 4 + ahg-translation::components.badge. --}}
  @php
    $translationSources = \AhgTranslation\Helpers\TranslationProvenance::forRecord(
        (int) $io->id,
        app()->getLocale()
    );
  @endphp

  {{-- Animated companion clip (Ken Burns / 2.5D) ------------------------------ --}}
  @php
    $companionAnim = null;
    try {
      $companionAnim = \Illuminate\Support\Facades\DB::table('object_image_ar')
        ->where('object_id', $io->id)
        ->first();
    } catch (\Throwable $e) { /* table may not exist yet */ }
  @endphp
  @if($companionAnim && $companionAnim->mp4_path)
    <div class="card mb-3 border-info">
      <div class="card-header bg-info bg-opacity-10 fw-bold d-flex justify-content-between align-items-center">
        <span><i class="fas fa-film me-1 text-info"></i> {{ __('Animated preview') }}</span>
        <span class="badge bg-warning text-dark"><i class="fas fa-flask me-1"></i>{{ __('Auto-generated, non-authoritative') }}</span>
      </div>
      <div class="card-body text-center">
        <video src="{{ $companionAnim->mp4_path }}"
               autoplay muted loop playsinline controls
               style="max-width:100%;max-height:420px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.2);">
        </video>
        <div class="small text-muted mt-2">
          @if(!empty($companionAnim->ai_model))
            <i class="fas fa-brain me-1"></i>{{ $companionAnim->ai_model }}
            &middot; {{ $companionAnim->mp4_duration_secs }}s @ {{ $companionAnim->mp4_fps }}fps
            &middot; {{ number_format(($companionAnim->mp4_size ?? 0) / 1024, 0) }} KB
            @if($companionAnim->generation_secs)
              &middot; {{ __('Took') }} {{ (int) $companionAnim->generation_secs }}s
            @endif
          @else
            <i class="fas fa-video me-1"></i>{{ $companionAnim->mp4_motion }}
            &middot; {{ $companionAnim->mp4_duration_secs }}s
            &middot; {{ number_format(($companionAnim->mp4_size ?? 0) / 1024, 0) }} KB
          @endif
          @auth
            &middot;
            <form action="{{ route('image-ar.delete', ['id' => $companionAnim->id]) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Delete this animation?');">
              @csrf
              <button type="submit" class="btn btn-link btn-sm p-0 text-danger align-baseline">
                <i class="fas fa-trash-alt"></i> {{ __('Delete') }}
              </button>
            </form>
          @endauth
        </div>
        @if(!empty($companionAnim->ai_prompt))
          <div class="small text-muted mt-1 fst-italic">"{{ $companionAnim->ai_prompt }}"</div>
        @endif
      </div>
    </div>
  @endif

  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-io', ['io' => $io])
  @else

  {{-- TTS (Text-to-Speech) controls — only for text-heavy archival descriptions, not museum/3D/media objects --}}
  @if((!empty($io->scope_and_content) || !empty($io->archival_history) || !empty($io->arrangement))
      && (!isset($digitalObjects) || !($digitalObjects['master'] ?? null)
          || !in_array(\AhgCore\Services\DigitalObjectService::getMediaType($digitalObjects['master']), ['video', 'audio', 'other'])))
    @include('ahg-io-manage::_tts-controls', ['target' => '[data-tts-content]', 'style' => 'full', 'position' => 'inline'])
  @endif

  <div data-tts-content>

  {{-- User action buttons (matching AtoM: TTS, Favorites, Cart, Loan) --}}
  <div class="d-flex flex-wrap gap-1 mb-3 align-items-center">
    @auth
      @php
        $userId = auth()->id();
        $cartEnabled = \AhgCore\Services\MenuService::isPluginEnabled('ahgCartPlugin');
        $isFavorited = \Illuminate\Support\Facades\DB::table('favorites')
          ->where('user_id', $userId)->where('archival_description_id', $io->id)->exists();
        $inCart = $cartEnabled && \Illuminate\Support\Facades\DB::table('cart')
          ->where('user_id', $userId)->where('archival_description_id', $io->id)
          ->whereNull('completed_at')->exists();
        $hasDigitalObject = isset($digitalObjects) && ($digitalObjects['master'] ?? null);
      @endphp

      {{-- Favorites toggle --}}
      @if($isFavorited)
        <form method="POST" action="{{ route('favorites.remove', \Illuminate\Support\Facades\DB::table('favorites')->where('user_id', $userId)->where('archival_description_id', $io->id)->value('id')) }}" class="d-inline">
          @csrf
          <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="{{ __('Remove from Favorites') }}" data-bs-toggle="tooltip">
            <i class="fas fa-heart-broken"></i>
          </button>
        </form>
      @else
        <a href="{{ route('favorites.add', $io->slug) }}"
           class="btn btn-sm atom-btn-outline-danger" title="{{ __('Add to Favorites') }}" data-bs-toggle="tooltip">
          <i class="fas fa-heart"></i>
        </a>
      @endif

      {{-- Cart (only when ahgCartPlugin is enabled) --}}
      @if($cartEnabled && $hasDigitalObject)
        @if($inCart)
          <a href="{{ route('cart.browse') }}" class="btn btn-sm atom-btn-outline-success" title="{{ __('Go to Cart') }}" data-bs-toggle="tooltip">
            <i class="fas fa-shopping-cart"></i>
          </a>
        @else
          <a href="{{ route('cart.add', $io->slug) }}" class="btn btn-sm atom-btn-outline-success" title="{{ __('Add to Cart') }}" data-bs-toggle="tooltip">
            <i class="fas fa-cart-plus"></i>
          </a>
        @endif
      @endif

      {{-- Feedback --}}
      <a href="{{ url('/feedback/submit/' . $io->slug) }}" class="btn btn-sm atom-btn-white" title="{{ __('Item Feedback') }}" data-bs-toggle="tooltip">
        <i class="fas fa-comment"></i>
      </a>

      {{-- Request to Publish (uses cart route — gated by cart plugin) --}}
      @if($cartEnabled && $hasDigitalObject)
        <a href="{{ route('cart.add', $io->slug) }}" class="btn btn-sm atom-btn-white" title="{{ __('Request to Publish') }}" data-bs-toggle="tooltip">
          <i class="fas fa-paper-plane"></i>
        </a>
      @endif

      {{-- Loan: New + Manage --}}
      <a href="{{ route('loan.create', ['object_id' => $io->id]) }}" class="btn btn-sm atom-btn-white" title="{{ __('New Loan') }}" data-bs-toggle="tooltip">
        <i class="fas fa-hand-holding"></i>
      </a>
      <a href="{{ route('loan.index', ['object_id' => $io->id]) }}" class="btn btn-sm atom-btn-white" title="{{ __('Manage Loans') }}" data-bs-toggle="tooltip">
        <i class="fas fa-exchange-alt"></i>
      </a>
    @endauth
  </div>

  {{-- #98 Phase 1: extracted section, see partials/sections/identity-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.identity-area')

  {{-- #98 Phase 1: extracted section, see partials/sections/context-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.context-area')

  {{-- #98 Phase 1: extracted section, see partials/sections/content-and-structure-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.content-and-structure-area')

  {{-- #98 Phase 1: extracted section, see partials/sections/conditions-of-access-and-use-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.conditions-of-access-and-use-area')
{{-- #98 Phase 1: extracted section, see partials/sections/security-classification-area.blade.php --}}
@include('ahg-information-object-manage::partials.sections.security-classification-area')
  {{-- #98 Phase 1: extracted section, see partials/sections/allied-materials-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.allied-materials-area')

  {{-- ===== 6. Notes area (#98 Phase 1: extracted to partials/sections/) ===== --}}
  @include('ahg-information-object-manage::partials.sections.notes-area')

  {{-- #98 Phase 1: extracted section, see partials/sections/access-points-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.access-points-area')

  {{-- #98 Phase 1: extracted section, see partials/sections/description-control-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.description-control-area')

  {{-- ===== 8b. Administration area (authenticated only, matching AtoM _adminInfo.php) ===== --}}
  @auth
    <section id="administrationArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#admin-collapse">
          {{ __('Administration area') }}
        </a>
        <a href="{{ route('informationobject.edit', $io->slug) }}#admin-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit Administration area') }}">
          <i class="fas fa-pencil-alt"></i>
        </a>
      </h2>
      <div id="admin-collapse">
        <div class="p-0">
          <div class="row g-0">

                <div class="col-md-6">
                  {{-- Source language --}}
                  @if(isset($sourceLanguageName) && $sourceLanguageName)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">{{ __('Source language') }}</h3>
                      <div class="col-8 p-2">
                        @if($io->source_culture && $io->source_culture !== app()->getLocale())
                          <div class="default-translation">
                            <a href="{{ route('informationobject.show', $io->slug) }}?sf_culture={{ $io->source_culture }}">
                              {{ $sourceLanguageName }}
                            </a>
                          </div>
                        @else
                          {{ $sourceLanguageName }}
                        @endif
                      </div>
                    </div>
                  @endif

                  {{-- Last updated --}}
                  @if($io->updated_at)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">{{ __('Last updated') }}</h3>
                      <div class="col-8 p-2">{{ \Carbon\Carbon::parse($io->updated_at)->format('j F Y') }}</div>
                    </div>
                  @endif

                  {{-- Source name (from keymap) --}}
                  @if(isset($keymapEntries) && $keymapEntries->isNotEmpty())
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">{{ __('Source name') }}</h3>
                      <div class="col-8 p-2">
                        @foreach($keymapEntries as $keymap)
                          <p class="mb-1">{{ $keymap->source_name }}</p>
                        @endforeach
                      </div>
                    </div>
                  @endif

                  {{-- Collection type --}}
                  @if(isset($collectionTypeName) && $collectionTypeName)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">{{ __('Collection type') }}</h3>
                      <div class="col-8 p-2">{{ $collectionTypeName }}</div>
                    </div>
                  @endif
                </div>

                <div class="col-md-6">
                  {{-- Display standard (read-only on show, editable via edit page) --}}
                  @if(isset($displayStandardName) && $displayStandardName)
                    <div class="field text-break row g-0">
                      <h3 class="h6 lh-base m-0 text-muted col-4 border-end text-end p-2">{{ __('Display standard') }}</h3>
                      <div class="col-8 p-2">{{ $displayStandardName }}</div>
                    </div>
                  @endif
                </div>

          </div>
        </div>
      </div>
    </section>
  @endauth

  {{-- ===== 9. Rights & Access (combined PREMIS + Extended, library-style) ===== --}}
  @php
    $culture = app()->getLocale();

    // PREMIS rights
    $premisRights = \Illuminate\Support\Facades\DB::table('rights')
        ->join('relation', function ($j) use ($io) {
            $j->on('rights.id', '=', 'relation.subject_id')
               ->where('relation.object_id', '=', $io->id)
               ->where('relation.type_id', '=', 168);
        })
        ->leftJoin('rights_i18n', function ($j) use ($culture) {
            $j->on('rights.id', '=', 'rights_i18n.id')->where('rights_i18n.culture', '=', $culture);
        })
        ->select('rights.*', 'rights_i18n.rights_note', 'rights_i18n.copyright_note',
                 'rights_i18n.license_terms', 'rights_i18n.license_note',
                 'rights_i18n.statute_note', 'rights_i18n.identifier_type',
                 'rights_i18n.identifier_value')
        ->get()
        ->map(function ($r) use ($culture) {
            $r->basis_name = $r->basis_id ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $r->basis_id)->where('culture', $culture)->value('name') : null;
            $r->copyright_status_name = $r->copyright_status_id ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $r->copyright_status_id)->where('culture', $culture)->value('name') : null;
            $r->rights_holder_name = $r->rights_holder_id ? \Illuminate\Support\Facades\DB::table('actor_i18n')->where('id', $r->rights_holder_id)->where('culture', $culture)->value('authorized_form_of_name') : null;
            $r->granted = \Illuminate\Support\Facades\DB::table('granted_right')->where('rights_id', $r->id)->get()->map(function ($gr) use ($culture) {
                $gr->act_name = $gr->act_id ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $gr->act_id)->where('culture', $culture)->value('name') : null;
                $gr->restriction_label = match((int)($gr->restriction ?? -1)) { 0 => 'Allow', 1 => 'Disallow', 2 => 'Conditional', default => '' };
                return $gr;
            });
            return $r;
        });

    // Extended rights
    $extRightsData = \Illuminate\Support\Facades\Schema::hasTable('extended_rights')
        ? \Illuminate\Support\Facades\DB::table('extended_rights as er')
            ->leftJoin('extended_rights_i18n as eri', function ($j) use ($culture) {
                $j->on('eri.extended_rights_id', '=', 'er.id')->where('eri.culture', '=', $culture);
            })
            ->where('er.object_id', $io->id)->first()
        : null;

    $extRsName = ($extRightsData && ($extRightsData->rights_statement_id ?? null)) ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $extRightsData->rights_statement_id)->where('culture', $culture)->value('name') : null;
    $extCcName = ($extRightsData && ($extRightsData->creative_commons_license_id ?? null)) ? \Illuminate\Support\Facades\DB::table('term_i18n')->where('id', $extRightsData->creative_commons_license_id)->where('culture', $culture)->value('name') : null;
    $tkLabels = ($extRightsData && \Illuminate\Support\Facades\Schema::hasTable('extended_rights_tk_label'))
        ? \Illuminate\Support\Facades\DB::table('extended_rights_tk_label')->where('extended_rights_id', $extRightsData->id)->get()
        : collect();
    $embargoData = \Illuminate\Support\Facades\Schema::hasTable('embargo')
        ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $io->id)->where('is_active', 1)->first()
        : null;
    $primaryHolder = $premisRights->pluck('rights_holder_name')->filter()->first();
    $holderDisplay = $primaryHolder ?? ($extRightsData->rights_holder ?? null);
    $holderUri = $extRightsData->rights_holder_uri ?? null;
    $allNotes = collect();
    foreach ($premisRights as $pr) { if ($pr->rights_note) $allNotes->push($pr->rights_note); }
    if ($extRightsData && ($extRightsData->rights_note ?? null) && !$allNotes->contains($extRightsData->rights_note)) $allNotes->push($extRightsData->rights_note);
    $hasAnyRights = $premisRights->isNotEmpty() || $extRightsData || $embargoData;
  @endphp

  @if($hasAnyRights || auth()->check())
    <section id="rightsArea" class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#rights-collapse">
          <i class="fas fa-copyright me-2"></i>{{ __('Rights & Access') }}
        </a>
        @auth
          <a href="{{ route('informationobject.edit', $io->slug) }}#rights-collapse" class="float-end text-white opacity-75" style="font-size:.75rem;"><i class="fas fa-pencil-alt"></i></a>
        @endauth
      </h2>
      <div id="rights-collapse" class="px-3 py-2">

        @if($embargoData)
          <div class="alert alert-danger d-flex align-items-center mb-3">
            <i class="fas fa-ban me-2 fa-lg"></i>
            <div><strong>{{ __('Under Embargo') }}</strong> — {{ ucfirst($embargoData->embargo_type ?? 'full') }} embargo since {{ $embargoData->start_date }}
              @if($embargoData->end_date) until {{ $embargoData->end_date }} @else (no end date) @endif
            </div>
          </div>
        @endif

        <dl class="row mb-0">
          @if($extRsName)<dt class="col-sm-4">Rights statement</dt><dd class="col-sm-8">{{ $extRsName }}</dd>@endif
          @foreach($premisRights as $pr)
            @if($pr->basis_name)<dt class="col-sm-4">Basis</dt><dd class="col-sm-8">{{ $pr->basis_name }}</dd>@endif
          @endforeach
          @if($holderDisplay)
            <dt class="col-sm-4">Rights holder</dt>
            <dd class="col-sm-8">{{ $holderDisplay }}@if($holderUri) <a href="{{ $holderUri }}" target="_blank" class="ms-1"><i class="fas fa-external-link-alt small"></i></a>@endif</dd>
          @endif
          @php $startDate = $premisRights->pluck('start_date')->filter()->first() ?? ($extRightsData->rights_date ?? null); $endDate = $premisRights->pluck('end_date')->filter()->first() ?? ($extRightsData->expiry_date ?? null); @endphp
          @if($startDate)<dt class="col-sm-4">Start date</dt><dd class="col-sm-8">{{ $startDate }}</dd>@endif
          @if($endDate)<dt class="col-sm-4">End / Expiry date</dt><dd class="col-sm-8">{{ $endDate }}</dd>@endif
          @foreach($premisRights as $pr)
            @if($pr->copyright_status_name)<dt class="col-sm-4">Copyright status</dt><dd class="col-sm-8">{{ $pr->copyright_status_name }}</dd>@endif
            @if($pr->copyright_jurisdiction ?? null)<dt class="col-sm-4">Jurisdiction</dt><dd class="col-sm-8">{{ $pr->copyright_jurisdiction }}</dd>@endif
            @if($pr->copyright_note)<dt class="col-sm-4">Copyright note</dt><dd class="col-sm-8">{{ $pr->copyright_note }}</dd>@endif
          @endforeach
          @if($extCcName)<dt class="col-sm-4">Creative Commons</dt><dd class="col-sm-8">{{ $extCcName }}</dd>@endif
          @foreach($premisRights as $pr)
            @if($pr->license_terms ?? null)<dt class="col-sm-4">License terms</dt><dd class="col-sm-8">{{ $pr->license_terms }}</dd>@endif
            @if($pr->license_note ?? null)<dt class="col-sm-4">License note</dt><dd class="col-sm-8">{{ $pr->license_note }}</dd>@endif
            @if($pr->statute_note ?? null)<dt class="col-sm-4">Statute note</dt><dd class="col-sm-8">{{ $pr->statute_note }}</dd>@endif
          @endforeach
          @if($extRightsData && ($extRightsData->usage_conditions ?? null))<dt class="col-sm-4">Usage conditions</dt><dd class="col-sm-8">{{ $extRightsData->usage_conditions }}</dd>@endif
          @if($extRightsData && ($extRightsData->copyright_notice ?? null))<dt class="col-sm-4">Copyright notice</dt><dd class="col-sm-8">{{ $extRightsData->copyright_notice }}</dd>@endif
          @if($allNotes->isNotEmpty())<dt class="col-sm-4">Notes</dt><dd class="col-sm-8">@foreach($allNotes as $note)<p class="mb-1">{{ $note }}</p>@endforeach</dd>@endif
          @foreach($premisRights as $pr)
            @if(($pr->identifier_type ?? null) || ($pr->identifier_value ?? null))<dt class="col-sm-4">Identifier</dt><dd class="col-sm-8">{{ $pr->identifier_type }}{{ ($pr->identifier_type && $pr->identifier_value) ? ': ' : '' }}{{ $pr->identifier_value }}</dd>@endif
          @endforeach
        </dl>

        @if($tkLabels->isNotEmpty())
          <div class="mt-2"><strong class="small text-muted">{{ __('Traditional Knowledge Labels') }}</strong>
            <div class="d-flex flex-wrap gap-1 mt-1">@foreach($tkLabels as $tk)<span class="badge bg-dark">{{ $tk->label_name ?? $tk->label_code ?? '' }}</span>@endforeach</div>
          </div>
        @endif

        @php $allGranted = $premisRights->flatMap(fn($pr) => $pr->granted); @endphp
        @if($allGranted->isNotEmpty())
          <hr><h6 class="text-muted mb-2">{{ __('Granted Rights') }}</h6>
          <table class="table table-sm table-bordered mb-0">
            <thead><tr class="table-light"><th>{{ __('Act') }}</th><th>{{ __('Restriction') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Notes') }}</th></tr></thead>
            <tbody>
              @foreach($allGranted as $gr)
                <tr>
                  <td>{{ $gr->act_name ?? '' }}</td>
                  <td><span class="badge bg-{{ $gr->restriction == 0 ? 'success' : ($gr->restriction == 1 ? 'danger' : 'warning') }}">{{ $gr->restriction_label }}</span></td>
                  <td>{{ $gr->start_date ?? '' }}</td>
                  <td>{{ $gr->end_date ?? '' }}</td>
                  <td>{{ $gr->notes ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif

      </div>
    </section>
  @endif

  {{-- ===== 9b. Extended Rights visual badges ===== --}}
  @include('ahg-io-manage::partials._rights-badges')

  {{-- ===== 9c. Provenance & Chain of Custody (from provenance_entry table) ===== --}}
  @if(isset($provenanceEntries) && $provenanceEntries->isNotEmpty())
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        {{ __('Provenance & Chain of Custody') }}
      </h2>
      <div class="provenance-chain px-3 py-2">
        @foreach($provenanceEntries as $i => $entry)
          <div class="d-flex mb-2 align-items-start">
            <div class="me-3">
              <span class="badge rounded-pill bg-{{ $i === 0 ? 'primary' : 'secondary' }}">{{ $provenanceEntries->count() - $i }}</span>
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between">
                <div>
                  <strong>{{ $entry->owner_name }}</strong>
                  @if($entry->owner_type && $entry->owner_type !== 'unknown')
                    <span class="badge bg-info ms-1">{{ ucfirst(str_replace('_', ' ', $entry->owner_type)) }}</span>
                  @endif
                  @if($entry->transfer_type && $entry->transfer_type !== 'unknown')
                    <span class="badge bg-secondary ms-1">{{ ucfirst(str_replace('_', ' ', $entry->transfer_type)) }}</span>
                  @endif
                </div>
                <small class="text-muted">
                  @if($entry->start_date && $entry->end_date)
                    {{ $entry->start_date }} &ndash; {{ $entry->end_date }}
                  @elseif($entry->start_date)
                    {{ $entry->start_date }} &ndash; present
                  @elseif($entry->end_date)
                    until {{ $entry->end_date }}
                  @endif
                </small>
              </div>
              @if($entry->owner_location)
                <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>{{ $entry->owner_location }}</small>
              @endif
              @if($entry->notes)
                <p class="small text-muted mb-0 mt-1">{{ $entry->notes }}</p>
              @endif
            </div>
          </div>
        @endforeach
        @auth
          <div class="mt-2">
            <a href="{{ route('io.provenance', $io->slug) }}" class="btn btn-sm atom-btn-white">
              <i class="fas fa-edit me-1"></i>{{ __('Edit provenance chain') }}
            </a>
          </div>
        @endauth
      </div>
    </section>
  @endif

  {{-- Digital object metadata moved to the bottom of the middle area
       (just above the Action bar). See further down in this @section. --}}

  {{-- ===== 10b. Digital object rights (matching AtoM digitalobject/_rights.php) ===== --}}
  @auth
    @if(isset($digitalObjectRights) && !empty($digitalObjectRights))
      @foreach($digitalObjectRights as $usageKey => $doRightsData)
        <section class="border-bottom digitalObjectRights">
          <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <a class="text-decoration-none text-white" href="#do-rights-{{ $usageKey }}-collapse">
              Digital object ({{ $doRightsData['usageName'] }}) rights area
            </a>
            @if(isset($digitalObjects) && $digitalObjects['master'])
              <a href="{{ route('io.digitalobject.show', $digitalObjects['master']->id) }}" class="float-end text-white opacity-75" style="font-size:.75rem;" title="{{ __('Edit digital object') }}">
                <i class="fas fa-pencil-alt"></i>
              </a>
            @endif
          </h2>
          <div id="do-rights-{{ $usageKey }}-collapse">
            @foreach($doRightsData['rights'] as $doRight)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ $doRight->basis ?? 'Right' }}</h3>
                <div class="col-9 p-2">
                  @if(isset($doRight->act)){{ $doRight->act }}@endif
                  @if(isset($doRight->start_date) || isset($doRight->end_date))
                    <br><small class="text-muted">{{ $doRight->start_date ?? '?' }} - {{ $doRight->end_date ?? '?' }}</small>
                  @endif
                  @if(isset($doRight->rights_note) && $doRight->rights_note)
                    <br>{!! nl2br(e($doRight->rights_note)) !!}
                  @endif
                </div>
              </div>
            @endforeach
          </div>
        </section>
      @endforeach
    @endif
  @endauth

  {{-- #98 Phase 1: extracted section, see partials/sections/accession-area.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.accession-area')
  {{-- #98 Phase 1: extracted section, see partials/sections/museum-metadata.blade.php --}}
  @include('ahg-information-object-manage::partials.sections.museum-metadata')

  </div>{{-- /data-tts-content --}}

  @endif {{-- end heratio/ric view mode --}}

  {{-- RiC Explorer Panel + RiC Context — only visible in RiC view mode --}}
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-panel', ['resourceId' => $io->id])

    @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
      @include('ahg-ric::_ric-entities-panel', ['record' => $io, 'recordType' => 'io'])
    @endif

    {{-- P5.e Find Similar Records (vector similarity via Qdrant) --}}
    @include('ahg-search::_find-similar', ['ioId' => $io->id])
  @endif

  {{-- ===== 10. Digital object metadata (relocated to the bottom of the
       middle area, just above the Action bar in @section('after-content')) ===== --}}
  @if(isset($digitalObjects) && $digitalObjects['master'])
    @php
      $doMaster = $digitalObjects['master'];
      $doReference = $digitalObjects['reference'];
      $doThumbnail = $digitalObjects['thumbnail'];
      $doMasterUrl = \AhgCore\Services\DigitalObjectService::getUrl($doMaster);
      $doRefUrl = $doReference ? \AhgCore\Services\DigitalObjectService::getUrl($doReference) : '';
      $doThumbUrl = $doThumbnail ? \AhgCore\Services\DigitalObjectService::getUrl($doThumbnail) : '';
      $doMediaTypeName = \AhgCore\Services\DigitalObjectService::getMediaType($doMaster);

      // Reroute Master + Reference download links through the redacted-asset
      // endpoint for non-admin viewers when there are redactions on file.
      // The endpoint generates the redacted file on-the-fly (cached after the
      // first call) and streams it. Admins always get the original.
      // Thumbnails stay on the original URL — they're typically too small to
      // hold redactable content, and re-rendering them is wasteful.
      $__doIsAdmin = auth()->check() && auth()->user()
          && (method_exists(auth()->user(), 'isAdministrator')
              ? auth()->user()->isAdministrator()
              : (bool) (auth()->user()->is_admin ?? false));
      $__doHasRedactions = false;
      try {
          if (\Illuminate\Support\Facades\Schema::hasTable('privacy_visual_redaction')) {
              $__doHasRedactions = \Illuminate\Support\Facades\DB::table('privacy_visual_redaction')
                  ->where('object_id', $io->id)
                  ->whereIn('status', ['applied', 'reviewed', 'pending'])
                  ->exists();
          }
      } catch (\Throwable $e) { /* table missing — leave flag false */ }
      if ($__doHasRedactions && !$__doIsAdmin) {
          $__redactedUrl = route('io.privacy.redacted-asset', $io->slug);
          $doMasterUrl = $__redactedUrl;
          $doRefUrl    = $__redactedUrl;
      }
    @endphp
    <section class="border-bottom">
      <h2 class="h6 mb-0 py-2 px-3" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <a class="text-decoration-none text-white" href="#digital-object-collapse">
          {{ __('Digital object metadata') }}
        </a>
      </h2>
      <div id="digital-object-collapse">

          {{-- Master file --}}
          <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">{{ __('Master file') }}</h4>
          <div class="field text-break row g-0">
            <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filename') }}</h3>
            <div class="col-9 p-2">
              @auth
                <a href="{{ $doMasterUrl }}" target="_blank">{{ $doMaster->name }}</a>
              @else
                {{ $doMaster->name }}
              @endauth
            </div>
          </div>
          @if($doMaster->media_type_id)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Media type') }}</h3>
              <div class="col-9 p-2">{{ ucfirst($doMediaTypeName) }}</div>
            </div>
          @endif
          @if($doMaster->mime_type)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('MIME type') }}</h3>
              <div class="col-9 p-2">{{ $doMaster->mime_type }}</div>
            </div>
          @endif
          @if($doMaster->byte_size)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filesize') }}</h3>
              <div class="col-9 p-2">
                @if($doMaster->byte_size > 1048576)
                  {{ number_format($doMaster->byte_size / 1048576, 1) }} MB
                @else
                  {{ number_format($doMaster->byte_size / 1024, 1) }} KB
                @endif
              </div>
            </div>
          @endif
          @if($doMaster->checksum)
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Checksum') }}</h3>
              <div class="col-9 p-2"><code class="small">{{ $doMaster->checksum }}</code></div>
            </div>
          @endif

          {{-- Reference copy --}}
          @if($doReference)
            <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">{{ __('Reference copy') }}</h4>
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filename') }}</h3>
              <div class="col-9 p-2">
                @auth
                  <a href="{{ $doRefUrl }}" target="_blank">{{ $doReference->name }}</a>
                @else
                  {{ $doReference->name }}
                @endauth
              </div>
            </div>
            @if($doReference->mime_type)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('MIME type') }}</h3>
                <div class="col-9 p-2">{{ $doReference->mime_type }}</div>
              </div>
            @endif
            @if($doReference->byte_size)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filesize') }}</h3>
                <div class="col-9 p-2">
                  @if($doReference->byte_size > 1048576)
                    {{ number_format($doReference->byte_size / 1048576, 1) }} MB
                  @else
                    {{ number_format($doReference->byte_size / 1024, 1) }} KB
                  @endif
                </div>
              </div>
            @endif
          @endif

          {{-- Thumbnail copy --}}
          @if($doThumbnail)
            <h4 class="h6 py-2 px-3 mb-0 border-bottom" style="background:#f5f5f5;">{{ __('Thumbnail copy') }}</h4>
            <div class="field text-break row g-0">
              <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filename') }}</h3>
              <div class="col-9 p-2">
                <a href="{{ $doThumbUrl }}" target="_blank">{{ $doThumbnail->name }}</a>
              </div>
            </div>
            @if($doThumbnail->mime_type)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('MIME type') }}</h3>
                <div class="col-9 p-2">{{ $doThumbnail->mime_type }}</div>
              </div>
            @endif
            @if($doThumbnail->byte_size)
              <div class="field text-break row g-0">
                <h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Filesize') }}</h3>
                <div class="col-9 p-2">
                  @if($doThumbnail->byte_size > 1048576)
                    {{ number_format($doThumbnail->byte_size / 1048576, 1) }} MB
                  @else
                    {{ number_format($doThumbnail->byte_size / 1024, 1) }} KB
                  @endif
                </div>
              </div>
            @endif
          @endif

      </div>
    </section>
  @endif

@endsection

{{-- ============================================================ --}}
{{-- RIGHT SIDEBAR                                                --}}
{{-- ============================================================ --}}
@section('right')
  @include('ahg-io-manage::partials._right-blocks', [
    'record' => $io,
    'slug'   => $io->slug,
    'type'   => 'informationObject',
  ])
@endsection

{{-- ============================================================ --}}
{{-- Describe Object/Image Modal --}}
@auth
<div class="modal fade" id="describeModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-eye me-2"></i>{{ __('Describe Object/Image') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">AI-powered visual description of <strong>{{ $io->title ?? 'this record' }}</strong>. Analyses the digital object and generates a detailed description.</p>

        <div class="text-center mb-3">
          <button type="button" class="btn btn-primary btn-lg" id="describeBtn">
            <i class="fas fa-eye me-2"></i>{{ __('Describe Object') }}
          </button>
        </div>

        <div id="describeResults" style="display:none">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
              <span><i class="fas fa-file-alt me-1"></i>{{ __('AI Description') }}</span>
              <button type="button" class="btn btn-sm btn-light" id="describeApproveBtn" style="display:none">
                <i class="fas fa-check me-1"></i>{{ __('Approve & Save') }}
              </button>
            </div>
            <div class="card-body" id="describeResultsBody"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var objectId = {{ $io->id }};
  var ioTitle = @json($io->title ?? 'Untitled');
  var ioContext = @json(($io->scope_and_content ?? '') . ' ' . ($io->extent_and_medium ?? ''));

  document.getElementById('describeBtn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Analysing with AI...';

    fetch('/ai/describe/' + objectId, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-eye me-2"></i>Re-Describe';

      var body = document.getElementById('describeResultsBody');
      document.getElementById('describeResults').style.display = '';

      if (!data.success) {
        body.innerHTML = '<div class="alert alert-danger">' + (data.error || 'AI description failed') + '</div>';
        return;
      }

      var desc = data.description || '';
      var time = data.processing_time_ms || 0;

      var method = data.method === 'vision' ? '<span class="badge bg-info me-1">Vision (llava)</span>' : '<span class="badge bg-secondary me-1">Text (LLM)</span>';
      body.innerHTML = '<p class="text-muted small mb-2">' + method + ' Generated in ' + time + 'ms</p>' +
        '<div class="border rounded p-3 bg-white">' + desc.replace(/\n/g, '<br>') + '</div>';
      document.getElementById('describeApproveBtn').style.display = '';

      // Store for approve
      window._aiDescription = desc;
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-eye me-2"></i>Describe Object';
      document.getElementById('describeResultsBody').innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
      document.getElementById('describeResults').style.display = '';
    });
  });

  document.getElementById('describeApproveBtn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    // Save to ahg_ai_suggestion
    fetch('/admin/ai/suggest', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify({
        title: ioTitle,
        context: 'SAVE_SUGGESTION:' + objectId + ':scope_and_content:' + (window._aiDescription || '')
      })
    })
    .then(function() {
      // Save directly to the record
      btn.innerHTML = '<i class="fas fa-check me-1"></i>Saved!';
      setTimeout(function() { location.reload(); }, 1000);
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-check me-1"></i>Approve & Save';
      alert('Failed to save');
    });
  });
})();
</script>
@endauth

{{-- NER Modal --}}
@auth
<div class="modal fade" id="nerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-brain me-2"></i>{{ __('Extract Entities (NER)') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Named Entity Recognition — extract persons, organizations, places, dates from <strong>{{ $io->title ?? 'this record' }}</strong></p>

        {{-- Extract button --}}
        <div class="text-center mb-3" id="nerExtractSection">
          <button type="button" class="btn btn-primary btn-lg" id="nerExtractBtn">
            <i class="fas fa-brain me-2"></i>{{ __('Extract Entities') }}
          </button>
        </div>

        {{-- Results (hidden until extraction) --}}
        <div id="nerResults" style="display:none">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small" id="nerResultsMeta"></span>
            <div class="d-flex gap-1" id="nerActionBtns" style="display:none">
              <a href="{{ route('io.ai.review') }}?object_id={{ $io->id }}" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list-check me-1"></i>{{ __('Review & Link') }}
              </a>
              <button type="button" class="btn btn-success btn-sm" id="nerApproveBtn">
                <i class="fas fa-check me-1"></i>{{ __('Approve All') }}
              </button>
            </div>
          </div>
          <div id="nerResultsBody"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('io.ai.review') }}" class="btn btn-outline-primary btn-sm" id="nerFooterReview" style="display:none">
          <i class="fas fa-list-check me-1"></i>{{ __('Review & Link') }}
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
(function() {
  var objectId = {{ $io->id }};
  var icons = { PERSON: 'fa-user', ORG: 'fa-building', GPE: 'fa-map-marker-alt', DATE: 'fa-calendar', LOC: 'fa-globe', NORP: 'fa-users', EVENT: 'fa-bolt', WORK_OF_ART: 'fa-palette', LANGUAGE: 'fa-language', FAC: 'fa-landmark' };
  var colors = { PERSON: 'primary', ORG: 'success', GPE: 'info', DATE: 'warning', LOC: 'info', NORP: 'secondary', EVENT: 'danger', WORK_OF_ART: 'dark', LANGUAGE: 'secondary', FAC: 'secondary' };

  document.getElementById('nerExtractBtn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Extracting...';

    fetch('/admin/ai/ner/extract/' + objectId, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        'Accept': 'application/json'
      }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-brain me-2"></i>Re-Extract';

      if (!data.success) {
        document.getElementById('nerResultsBody').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Extraction failed') + '</div>';
        document.getElementById('nerResults').style.display = '';
        return;
      }

      var entities = data.entities || {};
      var count = data.entity_count || 0;
      var time = data.processing_time_ms || 0;

      document.getElementById('nerResultsMeta').textContent = 'Found ' + count + ' entities in ' + time + 'ms';
      document.getElementById('nerResults').style.display = '';

      if (count === 0) {
        document.getElementById('nerResultsBody').innerHTML = '<p class="text-muted text-center">No entities found in this record.</p>';
        return;
      }

      var html = '';
      for (var type in entities) {
        var items = entities[type];
        if (!items || !items.length) continue;
        var icon = icons[type] || 'fa-tag';
        var color = colors[type] || 'secondary';
        html += '<div class="mb-3"><h6 class="mb-1"><i class="fas ' + icon + ' me-1"></i>' + type + ' <span class="badge bg-' + color + '">' + items.length + '</span></h6>';
        html += '<div class="d-flex flex-wrap gap-1">';
        items.forEach(function(item) {
          html += '<span class="badge bg-' + color + ' bg-opacity-75 fw-normal py-1 px-2">' + item + '</span>';
        });
        html += '</div></div>';
      }

      document.getElementById('nerResultsBody').innerHTML = html;
      document.getElementById('nerActionBtns').style.display = '';
      document.getElementById('nerFooterReview').style.display = '';
    })
    .catch(function(err) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-brain me-2"></i>Extract Entities';
      document.getElementById('nerResultsBody').innerHTML = '<div class="alert alert-danger">' + err.message + '</div>';
      document.getElementById('nerResults').style.display = '';
    });
  });

  document.getElementById('nerApproveBtn').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Approving...';
    window.location.href = '{{ route("io.ai.review") }}';
  });
})();
</script>
@endauth

{{-- AFTER CONTENT: Action buttons                                --}}
{{-- ============================================================ --}}
{{-- Summary Modal --}}
@auth
<div class="modal fade" id="summaryModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="modal-title"><i class="fas fa-file-alt me-2"></i>{{ __('Generate Summary') }}</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="summaryModalBody">
        <div class="text-center py-5">
          <div class="spinner-border text-success mb-3"></div>
          <p class="text-muted">Loading summary generator...</p>
        </div>
      </div>
      <div class="modal-footer">
        <a href="{{ route('io.ai.summarize', $io->id) }}" class="btn btn-sm atom-btn-white" target="_blank">
          <i class="fas fa-external-link-alt me-1"></i>{{ __('Open Full Page') }}
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>
<script>
document.getElementById('summaryModal').addEventListener('shown.bs.modal', function() {
  var body = document.getElementById('summaryModalBody');
  if (body.dataset.loaded) return;
  body.dataset.loaded = '1';
  fetch('{{ route("io.ai.summarize", $io->id) }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
  .then(function(r) { return r.text(); })
  .then(function(html) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var content = doc.querySelector('#content, [role="main"], .container');
    body.innerHTML = content ? content.innerHTML : html;
  })
  .catch(function(err) { body.innerHTML = '<div class="alert alert-danger">Failed to load: ' + err.message + '</div>'; });
});
</script>
@endauth

@section('after-content')
  @auth
  @php
    // Kept for legacy refs further down the file that still read these locals.
    // The actions bar itself reads ACL via the partial, so adding/removing
    // buttons there does not require touching this file.
    $canUpdate = \AhgCore\Services\AclService::check($io, 'update');
    $canDelete = \AhgCore\Services\AclService::check($io, 'delete');
    $canCreate = \AhgCore\Services\AclService::check($io, 'create');
    $isAdmin   = $canUpdate;
  @endphp
  @include('ahg-information-object-manage::_actions-bar')
  @endauth
  <script src="{{ asset('vendor/ahg-theme-b5/js/ahg-transcription.js') }}"></script>

  {{-- Translate Modal --}}
  @auth
  @php
    $targetLanguages = [
        'en'=>'English','af'=>'Afrikaans','zu'=>'isiZulu','xh'=>'isiXhosa','st'=>'Sesotho',
        'tn'=>'Setswana','nso'=>'Sepedi','ts'=>'Xitsonga','ss'=>'SiSwati','ve'=>'Tshivenda',
        'nr'=>'isiNdebele','nl'=>'Dutch','fr'=>'French','de'=>'German','es'=>'Spanish',
        'pt'=>'Portuguese','sw'=>'Swahili','ar'=>'Arabic',
    ];
    $allFields = [
        'title'=>'Title','alternate_title'=>'Alternate Title','scope_and_content'=>'Scope and Content',
        'archival_history'=>'Archival History','acquisition'=>'Acquisition','arrangement'=>'Arrangement',
        'access_conditions'=>'Access Conditions','reproduction_conditions'=>'Reproduction Conditions',
        'finding_aids'=>'Finding Aids','related_units_of_description'=>'Related Units',
        'appraisal'=>'Appraisal','accruals'=>'Accruals','physical_characteristics'=>'Physical Characteristics',
        'location_of_originals'=>'Location of Originals','location_of_copies'=>'Location of Copies',
        'extent_and_medium'=>'Extent and Medium','sources'=>'Sources','rules'=>'Rules',
        'revision_history'=>'Revision History',
    ];
  @endphp

  {{-- Side-by-side translator (manual) — separate from the multi-step MT wizard #translateModal below. --}}
  @if(\Illuminate\Support\Facades\Route::has('ahgtranslation.translate')
      && \AhgCore\Services\AclService::check($io, 'translate'))
    @include('ahg-translation::_translate-sbs', ['objectId' => $io->id])
    @include('ahg-translation::_translate-cco-values', ['objectId' => $io->id])
  @endif

  <div class="modal fade" id="translateModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header bg-secondary text-white">
          <h5 class="modal-title"><i class="fas fa-language me-2"></i>Translate Record <span class="badge bg-light text-dark ms-2 translate-step-badge">{{ __('Step 1: Select Fields') }}</span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" style="max-height:75vh;overflow-y:auto;">
          {{-- Step 1 --}}
          <div id="translate-step1">
            <div class="row mb-3">
              <div class="col-md-4">
                <label class="form-label fw-bold">{{ __('Source Language') }}</label>
                <select id="translateSource" class="form-select">
                  @foreach($targetLanguages as $code => $name)
                    <option value="{{ $code }}" @selected($code === ($io->source_culture ?? 'en'))>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">{{ __('Target Language') }}</label>
                <select id="translateTarget" class="form-select">
                  @foreach($targetLanguages as $code => $name)
                    <option value="{{ $code }}" @selected($code === 'af')>{{ $name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">{{ __('Options') }}</label>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="translateSaveCulture" checked><label class="form-check-label small" for="translateSaveCulture">{{ __('Save with culture code') }}</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="translateOverwrite"><label class="form-check-label small" for="translateOverwrite">{{ __('Overwrite existing') }}</label></div>
              </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-2">
              <span class="fw-bold">{{ __('Fields to Translate') }}</span>
              <div><button type="button" class="btn btn-sm btn-outline-secondary" id="translateSelectAll">{{ __('Select All') }}</button> <button type="button" class="btn btn-sm btn-outline-secondary" id="translateDeselectAll">{{ __('Deselect All') }}</button></div>
            </div>
            <div class="row">
              @php $i = 0; @endphp
              @foreach($allFields as $key => $label)
                @if($i % 10 === 0)<div class="col-md-6">@endif
                <div class="form-check">
                  <input class="form-check-input translate-field-cb" type="checkbox" value="{{ $key }}" data-label="{{ $label }}" id="tf-{{ $key }}" @checked(in_array($key, ['title','scope_and_content']))>
                  <label class="form-check-label" for="tf-{{ $key }}">{{ $label }}</label>
                </div>
                @if($i % 10 === 9 || $i === count($allFields) - 1)</div>@endif
                @php $i++; @endphp
              @endforeach
            </div>
            <div class="alert alert-info py-2 mt-3 mb-0"><i class="fas fa-info-circle me-1"></i>{{ __('Click "Translate" to preview translations before saving.') }}</div>
          </div>
          {{-- Step 2 --}}
          <div id="translate-step2" style="display:none;">
            <div class="alert alert-warning py-2 mb-3"><i class="fas fa-eye me-1"></i><strong>{{ __('Review Translations') }}</strong> — Edit if needed, then click "Approve & Save".</div>
            <div id="translatePreview"></div>
          </div>
          <div class="mt-3"><div class="alert py-2 mb-0" id="translateStatus" style="display:none;"></div></div>
        </div>
        <div class="modal-footer">
          <div id="translateStep1Btns">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>{{ __('Close') }}</button>
            <button type="button" class="btn btn-primary" id="translateRunBtn"><i class="fas fa-language me-1"></i>{{ __('Translate') }}</button>
          </div>
          <div id="translateStep2Btns" style="display:none;">
            <button type="button" class="btn btn-outline-secondary" id="translateBackBtn"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-success" id="translateApproveBtn"><i class="fas fa-check me-1"></i>{{ __('Approve & Save') }}</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
  (function(){
    var modalEl = document.getElementById('translateModal');
    if (!modalEl) return;
    var objectSlug = '{{ $io->slug }}';
    var results = [];

    document.getElementById('translateSelectAll').addEventListener('click', function(){ document.querySelectorAll('.translate-field-cb').forEach(function(cb){cb.checked=true;}); });
    document.getElementById('translateDeselectAll').addEventListener('click', function(){ document.querySelectorAll('.translate-field-cb').forEach(function(cb){cb.checked=false;}); });

    function showStep(n) {
      document.getElementById('translate-step1').style.display = n===1?'':'none';
      document.getElementById('translate-step2').style.display = n===2?'':'none';
      document.getElementById('translateStep1Btns').style.display = n===1?'':'none';
      document.getElementById('translateStep2Btns').style.display = n===2?'':'none';
      modalEl.querySelector('.translate-step-badge').textContent = n===1?'Step 1: Select Fields':'Step 2: Review & Approve';
    }

    function showStatus(msg, type) {
      var el = document.getElementById('translateStatus');
      el.style.display = 'block';
      el.className = 'alert py-2 mb-0 alert-' + (type||'secondary');
      el.textContent = msg;
    }

    function esc(t) { var d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

    document.getElementById('translateRunBtn').addEventListener('click', async function(){
      var source = document.getElementById('translateSource').value;
      var target = document.getElementById('translateTarget').value;
      var fields = Array.from(document.querySelectorAll('.translate-field-cb:checked')).map(function(cb){ return {field:cb.value, label:cb.dataset.label}; });
      if (!fields.length) { showStatus('Select at least one field.','danger'); return; }
      if (source===target) { showStatus('Source and target must differ.','danger'); return; }

      this.disabled = true;
      results = [];
      var csrfToken = (document.querySelector('meta[name="csrf-token"]')||{}).content||'';

      for (var i=0; i<fields.length; i++) {
        showStatus('Translating '+(i+1)+'/'+fields.length+': '+fields[i].label+'...','info');
        try {
          var body = new URLSearchParams({field:fields[i].field, targetField:fields[i].field, source:source, target:target, apply:'0', saveCulture:'0', overwrite:'0'});
          var res = await fetch('/admin/translation/translate/'+objectSlug, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':csrfToken}, body:body});
          var json = await res.json();
          results.push({field:fields[i].field, label:fields[i].label, ok:json.ok||json.success, translation:json.translation||'', sourceText:json.source_text||'', draft_id:json.draft_id, error:json.error});
        } catch(e) { results.push({field:fields[i].field, label:fields[i].label, ok:false, error:e.message}); }
      }
      this.disabled = false;
      document.getElementById('translateStatus').style.display = 'none';

      var html = '';
      results.forEach(function(r,idx){
        var badge = r.ok?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">Failed</span>';
        html += '<div class="card mb-2"><div class="card-header py-2">'+badge+' <strong class="ms-2">'+esc(r.label)+'</strong></div><div class="card-body">';
        if (r.ok) {
          html += '<div class="row"><div class="col-md-6"><label class="form-label small fw-bold text-muted">Source</label><div class="border rounded p-2 bg-light small" style="max-height:120px;overflow-y:auto;">'+esc(r.sourceText||'(empty)')+'</div></div>';
          html += '<div class="col-md-6"><label class="form-label small fw-bold text-success"><i class="fas fa-arrow-right me-1"></i>Translation</label><textarea class="form-control small translated-text" data-field="'+r.field+'" data-draft-id="'+(r.draft_id||'')+'" rows="3">'+esc(r.translation)+'</textarea></div></div>';
        } else { html += '<div class="alert alert-danger mb-0 small">'+esc(r.error||'Failed')+'</div>'; }
        html += '</div></div>';
      });
      document.getElementById('translatePreview').innerHTML = html;
      showStep(2);
    });

    document.getElementById('translateBackBtn').addEventListener('click', function(){ showStep(1); document.getElementById('translateStatus').style.display='none'; });

    document.getElementById('translateApproveBtn').addEventListener('click', async function(){
      this.disabled = true;
      var target = document.getElementById('translateTarget').value;
      var saveCulture = document.getElementById('translateSaveCulture').checked?'1':'0';
      var overwrite = document.getElementById('translateOverwrite').checked?'1':'0';
      var csrfToken = (document.querySelector('meta[name="csrf-token"]')||{}).content||'';
      var saved=0, failed=0;

      var textareas = document.querySelectorAll('.translated-text');
      for (var ta of textareas) {
        if (!ta.dataset.draftId) continue;
        showStatus('Saving '+(saved+failed+1)+'/'+textareas.length+'...','info');
        try {
          var body = new URLSearchParams({draftId:ta.dataset.draftId, overwrite:overwrite, saveCulture:saveCulture, targetCulture:target, editedText:ta.value});
          var res = await fetch('/admin/translation/apply', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':csrfToken}, body:body});
          var json = await res.json();
          if (json.ok||json.success) saved++; else failed++;
        } catch(e) { failed++; }
      }
      this.disabled = false;
      if (failed===0) { showStatus('Saved '+saved+' translation(s) with culture "'+target+'".','success'); setTimeout(function(){location.reload();},2000); }
      else { showStatus('Saved: '+saved+', Failed: '+failed,'warning'); }
    });

    modalEl.addEventListener('hidden.bs.modal', function(){ showStep(1); document.getElementById('translateStatus').style.display='none'; document.getElementById('translatePreview').innerHTML=''; results=[]; });
  })();
  </script>

  {{-- TripoSR preview modal — auto-opens when session has a staged preview for this IO --}}
  @php
    $triposrPreview = session('triposr_preview');
    $hasTriposrPreview = $triposrPreview && (int) ($triposrPreview['io_id'] ?? 0) === (int) $io->id;
  @endphp
  @if($hasTriposrPreview)
    <div class="modal fade" id="triposrPreviewModal" tabindex="-1" aria-labelledby="triposrPreviewModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-warning bg-opacity-25">
            <h5 class="modal-title" id="triposrPreviewModalLabel">
              <i class="fas fa-cube me-2 text-warning"></i> 3D preview &mdash; review before saving
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
          </div>
          <div class="modal-body">
            @if(!empty($triposrPreview['is_demo']))
              <div class="alert alert-info small mb-3">
                <i class="fas fa-flask me-1"></i>
                <strong>{{ __('Demo placeholder') }}</strong> &mdash;
                {{ __('the TripoSR backend is currently unavailable, so this is a bundled cube standing in for what a real generation would produce. Real GPU/AI generation is on its way.') }}
              </div>
            @else
              <div class="alert alert-warning small mb-3">
                <i class="fas fa-flask me-1"></i>
                {{ __('AI-generated reconstruction. Review the preview below — geometry is approximate. Click Save to attach it as a 3D model on this object, or Discard to throw it away.') }}
              </div>
            @endif
            <div style="height:420px;background:linear-gradient(135deg,#1a1a2e,#16213e);border-radius:8px;">
              <model-viewer
                src="{{ route('admin.3d-models.preview-file') }}"
                camera-controls
                touch-action="pan-y"
                auto-rotate
                shadow-intensity="1"
                exposure="1"
                style="width:100%;height:100%;background:transparent;border-radius:8px;">
                <div slot="poster" class="d-flex align-items-center justify-content-center h-100 text-white">
                  <div class="spinner-border me-2"></div>{{ __('Loading 3D preview…') }}
                </div>
              </model-viewer>
            </div>
            <div class="small text-muted mt-2">
              <strong>{{ __('Source image') }}:</strong> the IO's image &middot;
              <strong>{{ __('Generated') }}:</strong> {{ \Carbon\Carbon::parse($triposrPreview['created_at'])->diffForHumans() }} &middot;
              <strong>{{ __('Filename') }}:</strong> <code>{{ $triposrPreview['filename'] }}</code>
            </div>
          </div>
          <div class="modal-footer">
            <form method="POST" action="{{ route('admin.3d-models.preview-discard', ['ioId' => $io->id]) }}" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-outline-secondary">
                <i class="fas fa-times me-1"></i> {{ __('Close') }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('triposrPreviewModal');
        if (!el) return;
        var m = bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false });
        m.show();
        // Surface model-viewer load errors to the user instead of a permanent spinner
        var mv = el.querySelector('model-viewer');
        if (mv) {
          mv.addEventListener('error', function (ev) {
            console.error('model-viewer error', ev);
            var slot = mv.querySelector('[slot="poster"]');
            if (slot) {
              slot.innerHTML = '<div class="text-danger small text-center"><i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>'
                + 'Could not load the 3D preview.<br>Check the browser console for details.</div>';
            }
          });
        }
      });
    </script>
  @endif
  @endauth
@endsection
