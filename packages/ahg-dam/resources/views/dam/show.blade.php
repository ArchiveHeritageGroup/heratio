@extends('theme::layouts.3col')

@section('title', $asset->title ?? 'DAM asset')
@section('body-class', 'view dam')

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
  @include('ahg-io-manage::partials._treeview', ['io' => $asset])

  {{-- Quick search within this collection --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-search me-1"></i> {{ __('Search within') }}
    </div>
    <div class="card-body p-2">
      <form action="{{ route('informationobject.browse') }}" method="GET">
        <input type="hidden" name="collection" value="{{ $asset->id }}">
        <div class="input-group input-group-sm">
          <input type="text" name="subquery" class="form-control" placeholder="{{ __('Search...') }}">
          <button class="btn atom-btn-white" type="submit">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Related items (children) — DAM-specific, kept for legacy reasons --}}
  @if($relatedItems->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-sitemap me-1"></i> {{ __('Related items') }}
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedItems as $item)
          <li class="list-group-item small">
            <a href="{{ route('informationobject.show', $item->slug) }}">{{ $item->title ?: '[Untitled]' }}</a>
            @if($item->identifier)
              <br><small class="text-muted">{{ $item->identifier }}</small>
            @endif
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- DAM navigation --}}
  <div class="card mb-3">
    <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-folder-open me-1"></i> {{ __('DAM') }}
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('dam.dashboard') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-tachometer-alt me-1"></i> {{ __('Dashboard') }}
      </a>
      <a href="{{ route('dam.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-search me-1"></i> {{ __('Browse all assets') }}
      </a>
      @auth
        <a href="{{ route('dam.create') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-plus me-1"></i> {{ __('Create new asset') }}
        </a>
      @endauth
    </div>
  </div>

  @auth

    {{-- Actions moved to bottom bar in @section('after-content') (matches IO show page). --}}

    {{-- Collections Management --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ProvenanceController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-archive me-1"></i> {{ __('Collections Management') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.provenance', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-project-diagram me-1"></i> {{ __('Provenance') }}
        </a>
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgConditionPlugin'))
        <a href="{{ route('io.condition', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Condition assessment') }}
        </a>
        @endif
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgSpectrumPlugin'))
        <a href="{{ route('io.spectrum', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-chart-bar me-1"></i> {{ __('Spectrum data') }}
        </a>
        @endif
        @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgHeritageAccountingPlugin'))
        <a href="{{ route('io.heritage', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-landmark me-1"></i> {{ __('Heritage Assets') }}
        </a>
        @endif
        <a href="{{ route('io.research.citation', $asset->slug) }}" class="list-group-item list-group-item-action small">
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
        <a href="{{ route('io.preservation', $asset->slug) }}" class="list-group-item list-group-item-action small">
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
        <a href="#" class="list-group-item list-group-item-action small" data-bs-toggle="modal" data-bs-target="#ahgTranslateModal-{{ $asset->id }}">
          <i class="fas fa-language me-1"></i> {{ __('Translate') }}
        </a>
        <a href="{{ route('io.ai.review') }}?object_id={{ $asset->id }}" class="list-group-item list-group-item-action small">
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
        <a href="{{ route('io.privacy.scan', $asset->id) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-search me-1"></i> {{ __('Scan for PII') }}
        </a>
        @if(isset($digitalObjects) && !empty($digitalObjects['master']))
          <a href="{{ route('io.privacy.redaction', $asset->slug) }}" class="list-group-item list-group-item-action small">
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
          && \Illuminate\Support\Facades\DB::table('extended_rights')->where('object_id', $asset->id)->exists();
      $activeEmbargoSidebar = \Illuminate\Support\Facades\Schema::hasTable('embargo')
          ? \Illuminate\Support\Facades\DB::table('embargo')->where('object_id', $asset->id)->where('is_active', 1)->first()
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
          <a href="{{ route('io.rights.manage', $asset->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-copyright me-1"></i> {{ ($hasExtRights || $activeEmbargoSidebar) ? __('Edit rights') : __('Add rights') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.extended'))
          <a href="{{ route('io.rights.extended', $asset->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-shield-alt me-1"></i> {{ $hasExtRights ? __('Edit extended rights') : __('Add extended rights') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.embargo'))
          <a href="{{ route('io.rights.embargo', $asset->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-ban me-1"></i> {{ $activeEmbargoSidebar ? __('Manage embargo') : __('Add embargo') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('io.rights.export'))
          <a href="{{ route('io.rights.export', $asset->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-download me-1"></i> {{ __('Export rights (JSON-LD)') }}
          </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('ahgtranslation.translate')
            && \AhgCore\Services\AclService::check($asset, 'translate'))
          <a class="list-group-item list-group-item-action small" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateSbsModal-{{ $asset->id }}">
            <i class="fas fa-columns me-1"></i>{{ __('Translate (side-by-side)') }}
          </a>
          @if(\Illuminate\Support\Facades\Schema::hasTable('museum_metadata') && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $asset->id)->exists())
            <a class="list-group-item list-group-item-action small text-warning" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateCcoValuesModal-{{ $asset->id }}">
              <i class="fas fa-landmark me-1"></i>{{ __('Translate field data values (CCO)') }}
            </a>
          @endif
        @endif
      </div>
    </div>

    {{-- Side-by-side per-field translator (labels) + dedicated CCO values modal --}}
    @if(view()->exists('ahg-translation::_translate-sbs') && \AhgCore\Services\AclService::check($asset, 'translate'))
      @include('ahg-translation::_translate-sbs', ['objectId' => $asset->id])
      @include('ahg-translation::_translate-cco-values', ['objectId' => $asset->id])
    @endif

    {{-- Marketplace card removed from left sidebar per user request 2026-05-03.
         Marketplace functionality remains available via the cart / marketplace
         pages. To restore: copy the Gallery Marketplace card block. --}}

    {{-- Research Tools --}}
    @if(class_exists(\AhgInformationObjectManage\Controllers\ResearchController::class))
    <div class="card mb-3">
      <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-graduation-cap me-1"></i> {{ __('Research Tools') }}
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('io.research.assessment', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-clipboard-check me-1"></i> {{ __('Source Assessment') }}
        </a>
        <a href="{{ route('io.research.annotations', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-highlighter me-1"></i> {{ __('Annotation Studio') }}
        </a>
        <a href="{{ route('io.research.trust', $asset->slug) }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-star-half-alt me-1"></i> {{ __('Trust Score') }}
        </a>
        <a href="{{ route('io.research.dashboard') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-graduation-cap me-1"></i> {{ __('Research Dashboard') }}
        </a>
      </div>
    </div>
    @endif

  @endauth

  {{-- Access points (subject / place / name) moved to @section('right') per user
       request 2026-05-03. Group with Related-* cards on the right. --}}

@endsection

@section('right')
  {{-- Digital-object viewer relocated to top of @section('content') per user request 2026-05-03. --}}
  {{-- Top-right Print button removed; Print is in the bottom Actions bar (after-content). --}}

  @include('ahg-io-manage::partials._right-blocks', [
    'record'           => $asset,
    'slug'             => $asset->slug,
    'type'             => 'informationObject',
    'skipExport'       => false,
    'skipActiveLoans'  => true,
  ])

  @include('ahg-core::partials._record-sidebar-extras', ['objectId' => $asset->id, 'slug' => $asset->slug, 'title' => $asset->title, 'hideNer' => true, 'hideRights' => true, 'hideProvenance' => true, 'hideExport' => true])

  {{-- Place / Subject / Name access points — moved to bottom of right block per user request 2026-05-03 --}}
  @include('ahg-core::_place-access-points', ['resource' => $asset, 'sidebar' => true])
  @include('ahg-core::_subject-access-points', ['resource' => $asset, 'sidebar' => true])
  @include('ahg-core::_name-access-points', ['resource' => $asset, 'sidebar' => true])

  {{-- RiC Context panel relocated to @section('content') (sits just above the Actions bar). --}}

@endsection

@section('content')

  {{-- Full IIIF / Mirador / OpenSeadragon viewer — same partial that the IO
       show page uses. Auto-detects TIFF/JP2 (Cantaloupe IIIF), IIIF manifests
       (Mirador), audio/video, PDF, and plain raster. Partial expects $io —
       pass $asset under that name since DAM/Gallery/IO all share the IO row
       shape underneath. --}}
  @include('ahg-information-object-manage::partials._digital-object-viewer', ['io' => $asset, 'digitalObjects' => $digitalObjects])

  @include('ahg-ric::_view-switch', ['standard' => 'Dublin Core'])
  @if(session('ric_view_mode') === 'ric')
    @include('ahg-ric::_ric-view-dam', ['asset' => $asset])
    {{-- RiC Entities Panel — only renders when RiC view-mode toggle is on,
         matching the Archive (IO) show-page behaviour. --}}
    @if(class_exists(\AhgRic\Controllers\RicEntityController::class))
      @include('ahg-ric::_ric-entities-panel', ['record' => $asset, 'recordType' => 'instantiation'])
    @endif
  @else

  <h1>{{ $asset->title ?: $asset->identifier ?: '[Untitled]' }}
    {{-- ICIP cultural-sensitivity badge (issue #36 Phase 2b). --}}
    @include('ahg-translation::components.icip-sensitivity-badge', ['uri' => $asset->icip_sensitivity ?? null])
  </h1>

  {{-- Identification area --}}
  <section id="identificationArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Identification') }}</div></h2>
    @if($asset->identifier)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Identifier') }}</h3><div class="col-9 p-2">{{ $asset->identifier }}</div></div>
    @endif
    @if($asset->title)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Title') }}</h3><div class="col-9 p-2">{{ $asset->title }}</div></div>
    @endif
    @if($asset->scope_and_content)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Scope and content') }}</h3><div class="col-9 p-2">{!! nl2br(e($asset->scope_and_content)) !!}</div></div>
    @endif
  </section>

  {{-- Asset Classification area --}}
  @if($asset->asset_type || $asset->genre || $asset->color_type)
    <section id="classificationArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Asset Classification') }}</div></h2>
      @if($asset->asset_type)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Asset type') }}</h3><div class="col-9 p-2"><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $asset->asset_type)) }}</span></div></div>
      @endif
      @if($asset->genre)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Genre') }}</h3><div class="col-9 p-2">{{ $asset->genre }}</div></div>
      @endif
      @if($asset->color_type)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Color type') }}</h3><div class="col-9 p-2">{{ $asset->color_type }}</div></div>
      @endif
    </section>
  @endif

  {{-- Production area --}}
  @if($asset->production_company || $asset->distributor || $asset->broadcast_date || $asset->series_title || $asset->season_number || $asset->episode_number || $asset->awards || $asset->audio_language || $asset->subtitle_language)
    <section id="productionArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Production') }}</div></h2>
      @if($asset->production_company)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Production company') }}</h3><div class="col-9 p-2">{{ $asset->production_company }}</div></div>
      @endif
      @if($asset->distributor)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Distributor') }}</h3><div class="col-9 p-2">{{ $asset->distributor }}</div></div>
      @endif
      @if($asset->broadcast_date)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Broadcast date') }}</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->broadcast_date)->format('Y-m-d') }}</div></div>
      @endif
      @if($asset->series_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Series title') }}</h3><div class="col-9 p-2">{{ $asset->series_title }}</div></div>
      @endif
      @if($asset->season_number)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Season number') }}</h3><div class="col-9 p-2">{{ $asset->season_number }}</div></div>
      @endif
      @if($asset->episode_number)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Episode number') }}</h3><div class="col-9 p-2">{{ $asset->episode_number }}</div></div>
      @endif
      @if($asset->awards)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Awards') }}</h3><div class="col-9 p-2">{!! nl2br(e($asset->awards)) !!}</div></div>
      @endif
      @if($asset->audio_language)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Audio language(s)') }}</h3><div class="col-9 p-2">{{ $asset->audio_language }}</div></div>
      @endif
      @if($asset->subtitle_language)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Subtitle language(s)') }}</h3><div class="col-9 p-2">{{ $asset->subtitle_language }}</div></div>
      @endif
    </section>
  @endif

  {{-- Creator/Contact area --}}
  @if($asset->creator || $asset->creator_job_title || $asset->creator_email || $asset->creator_phone || $asset->creator_website || $asset->creator_city || $asset->creator_address)
    <section id="creatorArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Creator / Contact') }}</div></h2>
      @if($asset->creator)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Creator') }}</h3><div class="col-9 p-2">{{ $asset->creator }}</div></div>
      @endif
      @if($asset->creator_job_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Job title') }}</h3><div class="col-9 p-2">{{ $asset->creator_job_title }}</div></div>
      @endif
      @if($asset->creator_email)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Email') }}</h3><div class="col-9 p-2"><a href="mailto:{{ $asset->creator_email }}">{{ $asset->creator_email }}</a></div></div>
      @endif
      @if($asset->creator_phone)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Phone') }}</h3><div class="col-9 p-2">{{ $asset->creator_phone }}</div></div>
      @endif
      @if($asset->creator_website)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Website') }}</h3><div class="col-9 p-2"><a href="{{ $asset->creator_website }}" target="_blank" rel="noopener">{{ $asset->creator_website }}</a></div></div>
      @endif
      @if($asset->creator_city)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('City') }}</h3><div class="col-9 p-2">{{ $asset->creator_city }}</div></div>
      @endif
      @if($asset->creator_address)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Address') }}</h3><div class="col-9 p-2">{{ $asset->creator_address }}</div></div>
      @endif
    </section>
  @endif

  {{-- Content area --}}
  @if($asset->headline || $asset->duration_minutes || $asset->caption || $asset->keywords || $asset->iptc_subject_code || $asset->intellectual_genre || $asset->persons_shown || $asset->iptc_date_created)
    <section id="contentArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Content') }}</div></h2>
      @if($asset->headline)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Headline') }}</h3><div class="col-9 p-2">{{ $asset->headline }}</div></div>
      @endif
      @if($asset->duration_minutes)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Running time') }}</h3><div class="col-9 p-2">{{ $asset->duration_minutes }} min</div></div>
      @endif
      @if($asset->caption)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Caption') }}</h3><div class="col-9 p-2">{!! nl2br(e($asset->caption)) !!}</div></div>
      @endif
      @if($asset->keywords)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Keywords') }}</h3><div class="col-9 p-2">{{ $asset->keywords }}</div></div>
      @endif
      @if($asset->iptc_subject_code)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('IPTC Subject Code') }}</h3><div class="col-9 p-2">{{ $asset->iptc_subject_code }}</div></div>
      @endif
      @if($asset->intellectual_genre)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Intellectual genre') }}</h3><div class="col-9 p-2">{{ $asset->intellectual_genre }}</div></div>
      @endif
      @if($asset->persons_shown)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Persons shown') }}</h3><div class="col-9 p-2">{{ $asset->persons_shown }}</div></div>
      @endif
      @if($asset->iptc_date_created)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Date created') }}</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->iptc_date_created)->format('Y-m-d') }}</div></div>
      @endif
    </section>
  @endif

  {{-- Location area --}}
  @if($asset->city || $asset->state_province || $asset->sublocation || $asset->country || $asset->country_code || $asset->production_country || $asset->production_country_code)
    <section id="locationArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Location') }}</div></h2>
      @if($asset->city)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('City') }}</h3><div class="col-9 p-2">{{ $asset->city }}</div></div>
      @endif
      @if($asset->state_province)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('State / Province') }}</h3><div class="col-9 p-2">{{ $asset->state_province }}</div></div>
      @endif
      @if($asset->sublocation)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Sublocation') }}</h3><div class="col-9 p-2">{{ $asset->sublocation }}</div></div>
      @endif
      @if($asset->country)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Country') }}</h3><div class="col-9 p-2">{{ $asset->country }}</div></div>
      @endif
      @if($asset->country_code)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Country code') }}</h3><div class="col-9 p-2">{{ $asset->country_code }}</div></div>
      @endif
      @if($asset->production_country)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Production country') }}</h3><div class="col-9 p-2">{{ $asset->production_country }}</div></div>
      @endif
      @if($asset->production_country_code)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Production country code') }}</h3><div class="col-9 p-2">{{ $asset->production_country_code }}</div></div>
      @endif
    </section>
  @endif

  {{-- Rights area --}}
  @if($asset->credit_line || $asset->source || $asset->copyright_notice || $asset->rights_usage_terms || $asset->license_type || $asset->license_url || $asset->license_expiry || $asset->model_release_status || $asset->model_release_id || $asset->property_release_status || $asset->property_release_id)
    <section id="rightsArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Rights') }}</div></h2>
      @if($asset->credit_line)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Credit line') }}</h3><div class="col-9 p-2">{{ $asset->credit_line }}</div></div>
      @endif
      @if($asset->source)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Source') }}</h3><div class="col-9 p-2">{{ $asset->source }}</div></div>
      @endif
      @if($asset->copyright_notice)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Copyright notice') }}</h3><div class="col-9 p-2">{{ $asset->copyright_notice }}</div></div>
      @endif
      @if($asset->rights_usage_terms)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Rights usage terms') }}</h3><div class="col-9 p-2">{!! nl2br(e($asset->rights_usage_terms)) !!}</div></div>
      @endif
      @if($asset->license_type)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('License type') }}</h3><div class="col-9 p-2">{{ $asset->license_type }}</div></div>
      @endif
      @if($asset->license_url)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('License URL') }}</h3><div class="col-9 p-2"><a href="{{ $asset->license_url }}" target="_blank" rel="noopener">{{ $asset->license_url }}</a></div></div>
      @endif
      @if($asset->license_expiry)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('License expiry') }}</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->license_expiry)->format('Y-m-d') }}</div></div>
      @endif
      @if($asset->model_release_status)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Model release status') }}</h3><div class="col-9 p-2">{{ $asset->model_release_status }}</div></div>
      @endif
      @if($asset->model_release_id)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Model release ID') }}</h3><div class="col-9 p-2">{{ $asset->model_release_id }}</div></div>
      @endif
      @if($asset->property_release_status)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Property release status') }}</h3><div class="col-9 p-2">{{ $asset->property_release_status }}</div></div>
      @endif
      @if($asset->property_release_id)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Property release ID') }}</h3><div class="col-9 p-2">{{ $asset->property_release_id }}</div></div>
      @endif
    </section>
  @endif

  {{-- Artwork/Object area --}}
  @if($asset->artwork_title || $asset->artwork_creator || $asset->artwork_date || $asset->artwork_source || $asset->artwork_copyright)
    <section id="artworkArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Artwork / Object') }}</div></h2>
      @if($asset->artwork_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Artwork title') }}</h3><div class="col-9 p-2">{{ $asset->artwork_title }}</div></div>
      @endif
      @if($asset->artwork_creator)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Artwork creator') }}</h3><div class="col-9 p-2">{{ $asset->artwork_creator }}</div></div>
      @endif
      @if($asset->artwork_date)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Artwork date') }}</h3><div class="col-9 p-2">{{ $asset->artwork_date }}</div></div>
      @endif
      @if($asset->artwork_source)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Artwork source') }}</h3><div class="col-9 p-2">{{ $asset->artwork_source }}</div></div>
      @endif
      @if($asset->artwork_copyright)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Artwork copyright') }}</h3><div class="col-9 p-2">{{ $asset->artwork_copyright }}</div></div>
      @endif
    </section>
  @endif

  {{-- Technical/Administrative area --}}
  @if($asset->iptc_title || $asset->job_id || $asset->instructions)
    <section id="technicalArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Technical / Administrative') }}</div></h2>
      @if($asset->iptc_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Title (IPTC Object Name)') }}</h3><div class="col-9 p-2">{{ $asset->iptc_title }}</div></div>
      @endif
      @if($asset->job_id)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Job / Assignment ID') }}</h3><div class="col-9 p-2">{{ $asset->job_id }}</div></div>
      @endif
      @if($asset->instructions)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Special instructions') }}</h3><div class="col-9 p-2">{!! nl2br(e($asset->instructions)) !!}</div></div>
      @endif
    </section>
  @endif

  {{-- Administration area --}}
  <section id="adminArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">{{ __('Administration area') }}</div></h2>
    @if($asset->created_at)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Created') }}</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->created_at)->format('Y-m-d H:i') }}</div></div>
    @endif
    @if($asset->updated_at)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">{{ __('Updated') }}</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->updated_at)->format('Y-m-d H:i') }}</div></div>
    @endif
  </section>

  @endif {{-- end ric_view_mode toggle --}}
@endsection

@section('after-content')
  @auth
  @php
    $canUpdate = \AhgCore\Services\AclService::check($asset, 'update');
    $canDelete = \AhgCore\Services\AclService::check($asset, 'delete');
    $canCreate = \AhgCore\Services\AclService::check($asset, 'create');
    $canTranslate = \AhgCore\Services\AclService::check($asset, 'translate');
    $hasMm = \Illuminate\Support\Facades\Schema::hasTable('museum_metadata')
      && \Illuminate\Support\Facades\DB::table('museum_metadata')->where('object_id', $asset->id)->exists();
    $hasChildren = \Illuminate\Support\Facades\DB::table('information_object')
      ->where('parent_id', $asset->id)->exists();
    $auditLogEnabled = \AhgCore\Services\SettingHelper::isAuditLogEnabled();
  @endphp
  <ul class="actions mb-3 nav gap-2">
    @if($canUpdate)
    <li>
      <a href="{{ route('dam.edit', $asset->slug) }}" class="btn atom-btn-outline-light">{{ __('Edit') }}</a>
    </li>
    @endif
    @if($canDelete)
    <li>
      <form action="{{ route('dam.destroy', $asset->slug) }}" method="POST"
            onsubmit="return confirm('{{ __('Are you sure you want to delete this asset?') }}');">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger">{{ __('Delete') }}</button>
      </form>
    </li>
    @endif
    @if($canCreate)
    <li>
      <a href="{{ route('dam.create') }}" class="btn atom-btn-outline-light">{{ __('Add new') }}</a>
    </li>
    <li>
      <a href="{{ route('informationobject.create', ['parent_id' => $asset->id, 'copy_from' => $asset->id]) }}" class="btn atom-btn-outline-light">{{ __('Duplicate') }}</a>
    </li>
    @endif
    @if($canUpdate)
    <li>
      <a href="{{ url('/' . $asset->slug . '/default/move') }}" class="btn atom-btn-outline-light">{{ __('Move') }}</a>
    </li>
    <li>
      <div class="dropup">
        <button type="button" class="btn atom-btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          {{ __('More') }}
        </button>
        <ul class="dropdown-menu mb-2">
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.rename', $asset->slug) }}">
              <i class="fas fa-i-cursor me-2"></i>{{ __('Rename') }}
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('informationobject.edit', ['slug' => $asset->slug, 'storage' => 1]) }}">
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
            @if($canDelete)
              <li>
                <a class="dropdown-item text-danger" href="{{ url('/' . $asset->slug . '/digitalobject/delete') }}">
                  <i class="fas fa-times-circle me-2"></i>{{ __('Delete digital object') }}
                </a>
              </li>
            @endif
          @else
            <li>
              <a class="dropdown-item" href="{{ route('io.digitalobject.add', $asset->slug) }}">
                <i class="fas fa-link me-2"></i>{{ __('Link digital object') }}
              </a>
            </li>
          @endif
          <li>
            <a class="dropdown-item" href="{{ route('io.multiFileUpload', $asset->slug) }}">
              <i class="fas fa-file-import me-2"></i>{{ __('Import digital objects') }}
            </a>
          </li>
          <li>
            <a class="dropdown-item" href="{{ url('/' . $asset->slug . '/right/edit') }}">
              <i class="fas fa-balance-scale me-2"></i>{{ __('Create new rights') }}
            </a>
          </li>
          @if($hasChildren)
            <li>
              <a class="dropdown-item" href="{{ url('/' . $asset->slug . '/right/manage') }}">
                <i class="fas fa-sitemap me-2"></i>{{ __('Manage rights inheritance') }}
              </a>
            </li>
          @endif
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="{{ route('io.showUpdateStatus', $asset->slug) }}">
              <i class="fas fa-eye me-2"></i>{{ __('Update publication status') }}
            </a>
          </li>
          @if($auditLogEnabled)
          <li>
            <a class="dropdown-item" href="{{ route('audit.browse', ['type' => 'QubitInformationObject', 'id' => $asset->id]) }}">
              <i class="fas fa-history me-2"></i>{{ __('Modification history') }}
            </a>
          </li>
          @endif
          @if($canTranslate && \Illuminate\Support\Facades\Route::has('ahgtranslation.translate'))
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateSbsModal-{{ $asset->id }}">
                <i class="fas fa-language me-2"></i>{{ __('Translate (labels — side-by-side)') }}
              </a>
            </li>
            @if($hasMm)
              <li>
                <a class="dropdown-item text-warning" href="#" data-bs-toggle="modal" data-bs-target="#ahgTranslateCcoValuesModal-{{ $asset->id }}">
                  <i class="fas fa-landmark me-2"></i>{{ __('Translate field data values (CCO)') }}
                </a>
              </li>
            @endif
          @endif
        </ul>
      </div>
    </li>
    @endif
    <li>
      <a href="{{ route('informationobject.print', $asset->slug) }}" class="btn atom-btn-outline-light" target="_blank">
        <i class="fas fa-print me-1"></i>{{ __('Print') }}
      </a>
    </li>
  </ul>
  @endauth
  @include('ahg-core::partials._ner-modal', ['objectId' => $asset->id, 'objectTitle' => $asset->title])
@endsection
