@extends('theme::layouts.3col')

@section('title', $asset->title ?? 'DAM asset')
@section('body-class', 'view dam')

@section('sidebar')
  {{-- Related items (children) --}}
  @if($relatedItems->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Related items</h5>
      </div>
      <ul class="list-group list-group-flush">
        @foreach($relatedItems as $item)
          <li class="list-group-item">
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
    <div class="card-header">
      <h5 class="mb-0">DAM</h5>
    </div>
    <div class="list-group list-group-flush">
      <a href="{{ route('dam.dashboard') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-tachometer-alt me-1"></i> Dashboard
      </a>
      <a href="{{ route('dam.browse') }}" class="list-group-item list-group-item-action small">
        <i class="fas fa-search me-1"></i> Browse all assets
      </a>
      @auth
        <a href="{{ route('dam.create') }}" class="list-group-item list-group-item-action small">
          <i class="fas fa-plus me-1"></i> Create new asset
        </a>
      @endauth
    </div>
  </div>
@endsection

@section('right')
  @include('ahg-core::components.digital-object', ['digitalObjects' => $digitalObjects])

  <div class="d-flex gap-1 mb-3">
    <button class="btn btn-sm atom-btn-white" onclick="window.print()" title="Print">
      <i class="fas fa-print"></i>
    </button>
    <button class="btn btn-sm atom-btn-white active-primary clipboard"
            data-clipboard-slug="{{ $asset->slug ?? '' }}" data-clipboard-type="dam"
            data-title="Add" data-alt-title="Remove" title="Add to clipboard">
      <i class="fas fa-paperclip"></i>
    </button>
  </div>
@endsection

@section('content')

  <h1>{{ $asset->title ?: $asset->identifier ?: '[Untitled]' }}</h1>

  {{-- Identification area --}}
  <section id="identificationArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Identification (ISAD)</div></h2>
    @if($asset->identifier)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Identifier</h3><div class="col-9 p-2">{{ $asset->identifier }}</div></div>
    @endif
    @if($asset->title)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Title</h3><div class="col-9 p-2">{{ $asset->title }}</div></div>
    @endif
    @if($asset->scope_and_content)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Scope and content</h3><div class="col-9 p-2">{!! nl2br(e($asset->scope_and_content)) !!}</div></div>
    @endif
  </section>

  {{-- Asset Classification area --}}
  @if($asset->asset_type || $asset->genre || $asset->color_type)
    <section id="classificationArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Asset Classification</div></h2>
      @if($asset->asset_type)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Asset type</h3><div class="col-9 p-2"><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $asset->asset_type)) }}</span></div></div>
      @endif
      @if($asset->genre)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Genre</h3><div class="col-9 p-2">{{ $asset->genre }}</div></div>
      @endif
      @if($asset->color_type)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Color type</h3><div class="col-9 p-2">{{ $asset->color_type }}</div></div>
      @endif
    </section>
  @endif

  {{-- Production area --}}
  @if($asset->production_company || $asset->distributor || $asset->broadcast_date || $asset->series_title || $asset->season_number || $asset->episode_number || $asset->awards || $asset->audio_language || $asset->subtitle_language)
    <section id="productionArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Production</div></h2>
      @if($asset->production_company)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Production company</h3><div class="col-9 p-2">{{ $asset->production_company }}</div></div>
      @endif
      @if($asset->distributor)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Distributor</h3><div class="col-9 p-2">{{ $asset->distributor }}</div></div>
      @endif
      @if($asset->broadcast_date)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Broadcast date</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->broadcast_date)->format('Y-m-d') }}</div></div>
      @endif
      @if($asset->series_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Series title</h3><div class="col-9 p-2">{{ $asset->series_title }}</div></div>
      @endif
      @if($asset->season_number)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Season number</h3><div class="col-9 p-2">{{ $asset->season_number }}</div></div>
      @endif
      @if($asset->episode_number)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Episode number</h3><div class="col-9 p-2">{{ $asset->episode_number }}</div></div>
      @endif
      @if($asset->awards)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Awards</h3><div class="col-9 p-2">{!! nl2br(e($asset->awards)) !!}</div></div>
      @endif
      @if($asset->audio_language)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Audio language(s)</h3><div class="col-9 p-2">{{ $asset->audio_language }}</div></div>
      @endif
      @if($asset->subtitle_language)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Subtitle language(s)</h3><div class="col-9 p-2">{{ $asset->subtitle_language }}</div></div>
      @endif
    </section>
  @endif

  {{-- Creator/Contact area --}}
  @if($asset->creator || $asset->creator_job_title || $asset->creator_email || $asset->creator_phone || $asset->creator_website || $asset->creator_city || $asset->creator_address)
    <section id="creatorArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Creator / Contact</div></h2>
      @if($asset->creator)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Creator</h3><div class="col-9 p-2">{{ $asset->creator }}</div></div>
      @endif
      @if($asset->creator_job_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Job title</h3><div class="col-9 p-2">{{ $asset->creator_job_title }}</div></div>
      @endif
      @if($asset->creator_email)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Email</h3><div class="col-9 p-2"><a href="mailto:{{ $asset->creator_email }}">{{ $asset->creator_email }}</a></div></div>
      @endif
      @if($asset->creator_phone)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Phone</h3><div class="col-9 p-2">{{ $asset->creator_phone }}</div></div>
      @endif
      @if($asset->creator_website)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Website</h3><div class="col-9 p-2"><a href="{{ $asset->creator_website }}" target="_blank" rel="noopener">{{ $asset->creator_website }}</a></div></div>
      @endif
      @if($asset->creator_city)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">City</h3><div class="col-9 p-2">{{ $asset->creator_city }}</div></div>
      @endif
      @if($asset->creator_address)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Address</h3><div class="col-9 p-2">{{ $asset->creator_address }}</div></div>
      @endif
    </section>
  @endif

  {{-- Content area --}}
  @if($asset->headline || $asset->duration_minutes || $asset->caption || $asset->keywords || $asset->iptc_subject_code || $asset->intellectual_genre || $asset->persons_shown || $asset->iptc_date_created)
    <section id="contentArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Content</div></h2>
      @if($asset->headline)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Headline</h3><div class="col-9 p-2">{{ $asset->headline }}</div></div>
      @endif
      @if($asset->duration_minutes)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Running time</h3><div class="col-9 p-2">{{ $asset->duration_minutes }} min</div></div>
      @endif
      @if($asset->caption)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Caption</h3><div class="col-9 p-2">{!! nl2br(e($asset->caption)) !!}</div></div>
      @endif
      @if($asset->keywords)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Keywords</h3><div class="col-9 p-2">{{ $asset->keywords }}</div></div>
      @endif
      @if($asset->iptc_subject_code)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">IPTC Subject Code</h3><div class="col-9 p-2">{{ $asset->iptc_subject_code }}</div></div>
      @endif
      @if($asset->intellectual_genre)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Intellectual genre</h3><div class="col-9 p-2">{{ $asset->intellectual_genre }}</div></div>
      @endif
      @if($asset->persons_shown)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Persons shown</h3><div class="col-9 p-2">{{ $asset->persons_shown }}</div></div>
      @endif
      @if($asset->iptc_date_created)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Date created</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->iptc_date_created)->format('Y-m-d') }}</div></div>
      @endif
    </section>
  @endif

  {{-- Location area --}}
  @if($asset->city || $asset->state_province || $asset->sublocation || $asset->country || $asset->country_code || $asset->production_country || $asset->production_country_code)
    <section id="locationArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Location</div></h2>
      @if($asset->city)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">City</h3><div class="col-9 p-2">{{ $asset->city }}</div></div>
      @endif
      @if($asset->state_province)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">State / Province</h3><div class="col-9 p-2">{{ $asset->state_province }}</div></div>
      @endif
      @if($asset->sublocation)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Sublocation</h3><div class="col-9 p-2">{{ $asset->sublocation }}</div></div>
      @endif
      @if($asset->country)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Country</h3><div class="col-9 p-2">{{ $asset->country }}</div></div>
      @endif
      @if($asset->country_code)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Country code</h3><div class="col-9 p-2">{{ $asset->country_code }}</div></div>
      @endif
      @if($asset->production_country)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Production country</h3><div class="col-9 p-2">{{ $asset->production_country }}</div></div>
      @endif
      @if($asset->production_country_code)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Production country code</h3><div class="col-9 p-2">{{ $asset->production_country_code }}</div></div>
      @endif
    </section>
  @endif

  {{-- Rights area --}}
  @if($asset->credit_line || $asset->source || $asset->copyright_notice || $asset->rights_usage_terms || $asset->license_type || $asset->license_url || $asset->license_expiry || $asset->model_release_status || $asset->model_release_id || $asset->property_release_status || $asset->property_release_id)
    <section id="rightsArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Rights</div></h2>
      @if($asset->credit_line)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Credit line</h3><div class="col-9 p-2">{{ $asset->credit_line }}</div></div>
      @endif
      @if($asset->source)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Source</h3><div class="col-9 p-2">{{ $asset->source }}</div></div>
      @endif
      @if($asset->copyright_notice)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Copyright notice</h3><div class="col-9 p-2">{{ $asset->copyright_notice }}</div></div>
      @endif
      @if($asset->rights_usage_terms)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Rights usage terms</h3><div class="col-9 p-2">{!! nl2br(e($asset->rights_usage_terms)) !!}</div></div>
      @endif
      @if($asset->license_type)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">License type</h3><div class="col-9 p-2">{{ $asset->license_type }}</div></div>
      @endif
      @if($asset->license_url)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">License URL</h3><div class="col-9 p-2"><a href="{{ $asset->license_url }}" target="_blank" rel="noopener">{{ $asset->license_url }}</a></div></div>
      @endif
      @if($asset->license_expiry)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">License expiry</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->license_expiry)->format('Y-m-d') }}</div></div>
      @endif
      @if($asset->model_release_status)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Model release status</h3><div class="col-9 p-2">{{ $asset->model_release_status }}</div></div>
      @endif
      @if($asset->model_release_id)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Model release ID</h3><div class="col-9 p-2">{{ $asset->model_release_id }}</div></div>
      @endif
      @if($asset->property_release_status)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Property release status</h3><div class="col-9 p-2">{{ $asset->property_release_status }}</div></div>
      @endif
      @if($asset->property_release_id)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Property release ID</h3><div class="col-9 p-2">{{ $asset->property_release_id }}</div></div>
      @endif
    </section>
  @endif

  {{-- Artwork/Object area --}}
  @if($asset->artwork_title || $asset->artwork_creator || $asset->artwork_date || $asset->artwork_source || $asset->artwork_copyright)
    <section id="artworkArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Artwork / Object</div></h2>
      @if($asset->artwork_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Artwork title</h3><div class="col-9 p-2">{{ $asset->artwork_title }}</div></div>
      @endif
      @if($asset->artwork_creator)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Artwork creator</h3><div class="col-9 p-2">{{ $asset->artwork_creator }}</div></div>
      @endif
      @if($asset->artwork_date)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Artwork date</h3><div class="col-9 p-2">{{ $asset->artwork_date }}</div></div>
      @endif
      @if($asset->artwork_source)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Artwork source</h3><div class="col-9 p-2">{{ $asset->artwork_source }}</div></div>
      @endif
      @if($asset->artwork_copyright)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Artwork copyright</h3><div class="col-9 p-2">{{ $asset->artwork_copyright }}</div></div>
      @endif
    </section>
  @endif

  {{-- Technical/Administrative area --}}
  @if($asset->iptc_title || $asset->job_id || $asset->instructions)
    <section id="technicalArea" class="border-bottom">
      <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Technical / Administrative</div></h2>
      @if($asset->iptc_title)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Title (IPTC Object Name)</h3><div class="col-9 p-2">{{ $asset->iptc_title }}</div></div>
      @endif
      @if($asset->job_id)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Job / Assignment ID</h3><div class="col-9 p-2">{{ $asset->job_id }}</div></div>
      @endif
      @if($asset->instructions)
        <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Special instructions</h3><div class="col-9 p-2">{!! nl2br(e($asset->instructions)) !!}</div></div>
      @endif
    </section>
  @endif

  {{-- Administration area --}}
  <section id="adminArea" class="border-bottom">
    <h2 class="h5 mb-0 atom-section-header"><div class="d-flex p-3 border-bottom text-primary">Administration area</div></h2>
    @if($asset->created_at)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Created</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->created_at)->format('Y-m-d H:i') }}</div></div>
    @endif
    @if($asset->updated_at)
      <div class="field text-break row g-0"><h3 class="h6 lh-base m-0 text-muted col-3 border-end text-end p-2">Updated</h3><div class="col-9 p-2">{{ \Carbon\Carbon::parse($asset->updated_at)->format('Y-m-d H:i') }}</div></div>
    @endif
  </section>

  {{-- Actions bar --}}
  @auth
    <ul class="actions mb-3 nav gap-2">
      <li>
        <a class="btn atom-btn-outline-light" href="{{ route('dam.edit', $asset->slug) }}">
          <i class="fas fa-pencil-alt me-1"></i>Edit
        </a>
      </li>
      <li>
        <form method="POST" action="{{ route('dam.destroy', $asset->slug) }}" class="d-inline"
              onsubmit="return confirm('Are you sure you want to delete this DAM asset?');">
          @csrf
          <button type="submit" class="btn atom-btn-outline-light">
            <i class="fas fa-trash me-1"></i>Delete
          </button>
        </form>
      </li>
      <li>
        <a class="btn atom-btn-outline-success" href="{{ route('dam.create') }}">
          <i class="fas fa-plus me-1"></i>Add new
        </a>
      </li>
    </ul>
  @endauth
@endsection
