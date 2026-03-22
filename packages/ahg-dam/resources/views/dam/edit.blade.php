@extends('theme::layouts.1col')

@section('title', $asset ? 'Edit DAM Asset' : 'Create DAM Asset')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-{{ $asset ? 'edit' : 'plus-circle' }} fa-2x text-success me-3"></i>
    <div>
      <h1 class="mb-0">{{ $asset ? 'Edit DAM Asset' : 'Create DAM Asset' }}</h1>
      <span class="small text-muted">{{ $asset ? 'Update digital asset metadata' : 'Add a new digital asset with IPTC/XMP metadata' }}</span>
    </div>
    @if($asset)
      <span class="small" id="heading-label">{{ $asset->title ?: $asset->identifier }}</span>
    @endif
  </div>

  <form method="POST"
        action="{{ $asset ? route('dam.update', $asset->slug) : route('dam.store') }}"
        id="editForm">
    @csrf
    @if($asset)
      @method('PUT')
    @endif
    @if(request('parent_id'))
      <input type="hidden" name="parent_id" value="{{ request('parent_id') }}">
    @endif

    {{-- ===== 1. Identification ===== --}}
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-id-card me-1"></i> Identification
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="title" class="form-label">
              Title
              <span class="form-required" title="This is a mandatory element.">*</span>
            </label>
            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $asset->title ?? '') }}">
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text text-muted small">The title of the digital asset.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="identifier" class="form-label">
              Identifier / Reference code
              <span class="form-required" title="This is a mandatory element.">*</span>
            </label>
            <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror"
                   value="{{ old('identifier', $asset->identifier ?? '') }}" placeholder="e.g., DAM-2024-001">
            @error('identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text text-muted small">A unique identifier for this DAM asset within the repository.</div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="parent_id" class="form-label">Parent Collection</label>
            <select class="form-select" id="parent_id" name="parent_id">
              <option value="1">-- Top level (no parent) --</option>
              @foreach($formChoices['parents'] ?? [] as $p)
                <option value="{{ $p->id }}" @selected(old('parent_id', $asset->parent_id ?? '') == $p->id)>{{ $p->title ?: $p->identifier }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="repository_id" class="form-label">Parent collection (Repository)</label>
            <select name="repository_id" id="repository_id" class="form-select">
              <option value="">-- Select repository --</option>
              @foreach($formChoices['repositories'] as $repo)
                <option value="{{ $repo->id }}" @selected(old('repository_id', $asset->repository_id ?? '') == $repo->id)>{{ $repo->name }}</option>
              @endforeach
            </select>
            <div class="form-text text-muted small">The repository that holds this asset.</div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="level_of_description_id" class="form-label">Level of description</label>
            <select name="level_of_description_id" id="level_of_description_id" class="form-select">
              <option value="">-- Select --</option>
              @foreach($formChoices['levels'] as $level)
                <option value="{{ $level->id }}" @selected(old('level_of_description_id', $asset->level_of_description_id ?? '') == $level->id)>{{ $level->name }}</option>
              @endforeach
            </select>
            <div class="form-text text-muted small">The level of arrangement of the unit of description.</div>
          </div>
        </div>

        <div class="mb-3">
          <label for="scope_content" class="form-label">Scope and content</label>
          <textarea name="scope_content" id="scope_content" class="form-control" rows="3">{{ old('scope_and_content', $asset->scope_and_content ?? '') }}</textarea>
          <div class="form-text text-muted small">A description of the intellectual content and document types represented in this asset.</div>
        </div>
      </div>
    </div>

    {{-- ===== 2. Asset Type & Classification ===== --}}
    <div class="card mb-3">
      <div class="card-header border-primary" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-tag me-1"></i> Asset Type &amp; Classification
      </div>
      <div class="card-body">
        <div class="mb-3">
          <label for="asset_type" class="form-label">Asset type</label>
          <select name="asset_type" id="asset_type" class="form-select">
            <option value="">-- Select asset type --</option>
            <optgroup label="Image">
              <option value="photo" @selected(old('asset_type', $asset->asset_type ?? '') === 'photo')>Photo / Image</option>
              <option value="artwork" @selected(old('asset_type', $asset->asset_type ?? '') === 'artwork')>Artwork / Painting</option>
              <option value="scan" @selected(old('asset_type', $asset->asset_type ?? '') === 'scan')>Scan / Digitized</option>
            </optgroup>
            <optgroup label="Video / Film">
              <option value="documentary" @selected(old('asset_type', $asset->asset_type ?? '') === 'documentary')>Documentary</option>
              <option value="feature" @selected(old('asset_type', $asset->asset_type ?? '') === 'feature')>Feature Film</option>
              <option value="short" @selected(old('asset_type', $asset->asset_type ?? '') === 'short')>Short Film</option>
              <option value="news" @selected(old('asset_type', $asset->asset_type ?? '') === 'news')>News / Footage</option>
              <option value="interview" @selected(old('asset_type', $asset->asset_type ?? '') === 'interview')>Interview</option>
              <option value="home_movie" @selected(old('asset_type', $asset->asset_type ?? '') === 'home_movie')>Home Movie</option>
            </optgroup>
            <optgroup label="Audio">
              <option value="oral_history" @selected(old('asset_type', $asset->asset_type ?? '') === 'oral_history')>Oral History</option>
              <option value="music" @selected(old('asset_type', $asset->asset_type ?? '') === 'music')>Music Recording</option>
              <option value="podcast" @selected(old('asset_type', $asset->asset_type ?? '') === 'podcast')>Podcast / Radio</option>
              <option value="speech" @selected(old('asset_type', $asset->asset_type ?? '') === 'speech')>Speech / Lecture</option>
            </optgroup>
            <optgroup label="Document">
              <option value="document" @selected(old('asset_type', $asset->asset_type ?? '') === 'document')>Document / PDF</option>
              <option value="manuscript" @selected(old('asset_type', $asset->asset_type ?? '') === 'manuscript')>Manuscript</option>
            </optgroup>
          </select>
          <div class="form-text text-muted small">The type classification of this digital asset.</div>
        </div>

        <div class="mb-3">
          <label for="genre" class="form-label">Genre</label>
          <input type="text" name="genre" id="genre" class="form-control"
                 value="{{ old('genre', $asset->genre ?? '') }}" placeholder="e.g., Documentary, Portrait">
          <div class="form-text text-muted small">The genre or category of the content.</div>
        </div>

        <div class="mb-3 field-video field-audio" style="display:none;">
          <label for="color_type" class="form-label">Color type</label>
          <select name="color_type" id="color_type" class="form-select">
            <option value="">-- Select --</option>
            <option value="Color" @selected(old('color_type', $asset->color_type ?? '') === 'Color')>Color</option>
            <option value="Black & White" @selected(old('color_type', $asset->color_type ?? '') === 'Black & White')>Black & White</option>
            <option value="Sepia" @selected(old('color_type', $asset->color_type ?? '') === 'Sepia')>Sepia</option>
            <option value="Mixed" @selected(old('color_type', $asset->color_type ?? '') === 'Mixed')>Mixed</option>
          </select>
          <div class="form-text text-muted small">The color mode of the asset.</div>
        </div>
      </div>
    </div>

    {{-- ===== 3. Production Details ===== --}}
    <div class="card mb-3 field-video" style="display:none;">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-film me-1"></i> Production Details
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="production_company" class="form-label">Production company</label>
            <input type="text" name="production_company" id="production_company" class="form-control"
                   value="{{ old('production_company', $asset->production_company ?? '') }}">
            <div class="form-text text-muted small">The company or organisation that produced the content.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="distributor" class="form-label">Distributor / Broadcaster</label>
            <input type="text" name="distributor" id="distributor" class="form-control"
                   value="{{ old('distributor', $asset->distributor ?? '') }}">
            <div class="form-text text-muted small">The entity responsible for distributing or broadcasting the content.</div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="broadcast_date" class="form-label">Broadcast date</label>
            <input type="date" name="broadcast_date" id="broadcast_date" class="form-control"
                   value="{{ old('broadcast_date', $asset->broadcast_date ?? '') }}">
            <div class="form-text text-muted small">The original broadcast or release date.</div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="series_title" class="form-label">Series title</label>
            <input type="text" name="series_title" id="series_title" class="form-control"
                   value="{{ old('series_title', $asset->series_title ?? '') }}">
            <div class="form-text text-muted small">The title of the series this asset belongs to.</div>
          </div>
          <div class="col-md-2 mb-3">
            <label for="season_number" class="form-label">Season number</label>
            <input type="text" name="season_number" id="season_number" class="form-control"
                   value="{{ old('season_number', $asset->season_number ?? '') }}">
            <div class="form-text text-muted small">Season number within the series.</div>
          </div>
          <div class="col-md-2 mb-3">
            <label for="episode_number" class="form-label">Episode number</label>
            <input type="text" name="episode_number" id="episode_number" class="form-control"
                   value="{{ old('episode_number', $asset->episode_number ?? '') }}">
            <div class="form-text text-muted small">Episode number within the season.</div>
          </div>
        </div>

        <div class="mb-3">
          <label for="awards" class="form-label">Awards</label>
          <textarea name="awards" id="awards" class="form-control" rows="2">{{ old('awards', $asset->awards ?? '') }}</textarea>
          <div class="form-text text-muted small">Awards or recognitions received by this production.</div>
        </div>
      </div>
    </div>

    {{-- ===== 4. Production Credits ===== --}}
    <div class="card mb-3 field-video field-audio" style="display:none;">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-users me-1"></i> Production Credits
      </div>
      <div class="card-body">
        <div id="credits-container">
          @if($asset && !empty($asset->credits))
            @foreach($asset->credits as $i => $credit)
              <div class="row credit-row mb-2">
                <div class="col-md-4">
                  <input type="text" name="credit_role[]" class="form-control" placeholder="Role (e.g., Director)" value="{{ $credit['role'] ?? '' }}">
                </div>
                <div class="col-md-6">
                  <input type="text" name="credit_name[]" class="form-control" placeholder="Name" value="{{ $credit['name'] ?? '' }}">
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn atom-btn-outline-danger btn-sm remove-credit"><i class="fas fa-times"></i></button>
                </div>
              </div>
            @endforeach
          @else
            <div class="row credit-row mb-2">
              <div class="col-md-4">
                <input type="text" name="credit_role[]" class="form-control" placeholder="Role (e.g., Director)">
              </div>
              <div class="col-md-6">
                <input type="text" name="credit_name[]" class="form-control" placeholder="Name">
              </div>
              <div class="col-md-2">
                <button type="button" class="btn atom-btn-outline-danger btn-sm remove-credit"><i class="fas fa-times"></i></button>
              </div>
            </div>
          @endif
        </div>
        <button type="button" class="btn atom-btn-white btn-sm mt-2" id="add-credit">
          <i class="fas fa-plus"></i> Add Credit
        </button>
      </div>
    </div>

    {{-- ===== 5. Language ===== --}}
    <div class="card mb-3 field-video field-audio" style="display:none;">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-language me-1"></i> Language
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="audio_language" class="form-label">Audio language(s)</label>
            <input type="text" name="audio_language" id="audio_language" class="form-control"
                   value="{{ old('audio_language', $asset->audio_language ?? '') }}">
            <div class="form-text text-muted small">The language(s) of the audio track, comma-separated.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="subtitle_language" class="form-label">Subtitle language(s)</label>
            <input type="text" name="subtitle_language" id="subtitle_language" class="form-control"
                   value="{{ old('subtitle_language', $asset->subtitle_language ?? '') }}">
            <div class="form-text text-muted small">The language(s) of available subtitles, comma-separated.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 6. IPTC - Creator / Photographer ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcCreatorCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-user me-1"></i> IPTC - Creator / Photographer
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcCreatorCollapse">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_creator" class="form-label">Creator / Photographer</label>
              <input type="text" name="iptc_creator" id="iptc_creator" class="form-control"
                     value="{{ old('creator', $asset->creator ?? '') }}">
              <div class="form-text text-muted small">The person or organisation that created the asset.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_creator_job_title" class="form-label">Job title</label>
              <input type="text" name="iptc_creator_job_title" id="iptc_creator_job_title" class="form-control"
                     value="{{ old('creator_job_title', $asset->creator_job_title ?? '') }}">
              <div class="form-text text-muted small">The job title of the creator.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_email" class="form-label">Email</label>
              <input type="email" name="iptc_creator_email" id="iptc_creator_email" class="form-control"
                     value="{{ old('creator_email', $asset->creator_email ?? '') }}">
              <div class="form-text text-muted small">Contact email address for the creator.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_phone" class="form-label">Phone</label>
              <input type="text" name="iptc_creator_phone" id="iptc_creator_phone" class="form-control"
                     value="{{ old('creator_phone', $asset->creator_phone ?? '') }}">
              <div class="form-text text-muted small">Contact phone number for the creator.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_website" class="form-label">Website</label>
              <input type="text" name="iptc_creator_website" id="iptc_creator_website" class="form-control"
                     value="{{ old('creator_website', $asset->creator_website ?? '') }}">
              <div class="form-text text-muted small">Website URL for the creator.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_city" class="form-label">City</label>
              <input type="text" name="iptc_creator_city" id="iptc_creator_city" class="form-control"
                     value="{{ old('creator_city', $asset->creator_city ?? '') }}">
              <div class="form-text text-muted small">The city where the creator is located.</div>
            </div>
            <div class="col-md-8 mb-3">
              <label for="iptc_creator_address" class="form-label">Address</label>
              <input type="text" name="iptc_creator_address" id="iptc_creator_address" class="form-control"
                     value="{{ old('creator_address', $asset->creator_address ?? '') }}">
              <div class="form-text text-muted small">Street address of the creator.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 7. IPTC - Content Description ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcContentCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-file-alt me-1"></i> IPTC - Content Description
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcContentCollapse">
        <div class="card-body">
          <div class="mb-3">
            <label for="iptc_headline" class="form-label">Headline</label>
            <input type="text" name="iptc_headline" id="iptc_headline" class="form-control"
                   value="{{ old('headline', $asset->headline ?? '') }}">
            <div class="form-text text-muted small">A brief synopsis or summary of the content.</div>
          </div>

          <div class="mb-3 field-video field-audio" style="display:none;">
            <label for="iptc_duration_minutes" class="form-label">Running time</label>
            <div class="input-group" style="max-width: 200px;">
              <input type="number" name="iptc_duration_minutes" id="iptc_duration_minutes" class="form-control"
                     value="{{ old('duration_minutes', $asset->duration_minutes ?? '') }}" min="0">
              <span class="input-group-text">min</span>
            </div>
            <div class="form-text text-muted small">Running time in minutes (round to nearest minute).</div>
          </div>

          <div class="mb-3">
            <label for="iptc_caption" class="form-label">Caption / Description</label>
            <textarea name="iptc_caption" id="iptc_caption" class="form-control" rows="3">{{ old('caption', $asset->caption ?? '') }}</textarea>
            <div class="form-text text-muted small">A textual description of the content.</div>
          </div>

          <div class="mb-3">
            <label for="iptc_keywords" class="form-label">Keywords</label>
            <input type="text" name="iptc_keywords" id="iptc_keywords" class="form-control"
                   value="{{ old('keywords', $asset->keywords ?? '') }}" placeholder="Comma-separated">
            <div class="form-text text-muted small">Keywords or tags describing the content, comma-separated.</div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_subject_code" class="form-label">IPTC Subject Code</label>
              <input type="text" name="iptc_subject_code" id="iptc_subject_code" class="form-control"
                     value="{{ old('iptc_subject_code', $asset->iptc_subject_code ?? '') }}">
              <div class="form-text text-muted small">IPTC subject reference code for categorisation.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_intellectual_genre" class="form-label">Intellectual genre</label>
              <input type="text" name="iptc_intellectual_genre" id="iptc_intellectual_genre" class="form-control"
                     value="{{ old('intellectual_genre', $asset->intellectual_genre ?? '') }}">
              <div class="form-text text-muted small">The intellectual genre of the content (e.g., Feature, Actuality).</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="iptc_persons_shown" class="form-label">Persons shown</label>
            <input type="text" name="iptc_persons_shown" id="iptc_persons_shown" class="form-control"
                   value="{{ old('persons_shown', $asset->persons_shown ?? '') }}">
            <div class="form-text text-muted small">Names of persons depicted in the content.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 8. IPTC - Location ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcLocationCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-map-marker-alt me-1"></i> IPTC - Location
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcLocationCollapse">
        <div class="card-body">
          <div class="mb-3">
            <label for="iptc_date_created" class="form-label">Date created</label>
            <input type="date" name="iptc_date_created" id="iptc_date_created" class="form-control"
                   value="{{ old('date_created', $asset->iptc_date_created ?? '') }}">
            <div class="form-text text-muted small">The date the intellectual content was created.</div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_city" class="form-label">City</label>
              <input type="text" name="iptc_city" id="iptc_city" class="form-control"
                     value="{{ old('city', $asset->city ?? '') }}">
              <div class="form-text text-muted small">The city where the content was created or depicts.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_state_province" class="form-label">State / Province</label>
              <input type="text" name="iptc_state_province" id="iptc_state_province" class="form-control"
                     value="{{ old('state_province', $asset->state_province ?? '') }}">
              <div class="form-text text-muted small">The state or province of the location.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_sublocation" class="form-label">Sublocation</label>
              <input type="text" name="iptc_sublocation" id="iptc_sublocation" class="form-control"
                     value="{{ old('sublocation', $asset->sublocation ?? '') }}">
              <div class="form-text text-muted small">A more specific location within the city.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_country" class="form-label">Country</label>
              <input type="text" name="iptc_country" id="iptc_country" class="form-control"
                     value="{{ old('country', $asset->country ?? '') }}">
              <div class="form-text text-muted small">The country name of the location depicted.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_country_code" class="form-label">Country code</label>
              <input type="text" name="iptc_country_code" id="iptc_country_code" class="form-control" maxlength="10"
                     value="{{ old('country_code', $asset->country_code ?? '') }}" placeholder="ISO 3166-1 alpha-3">
              <div class="form-text text-muted small">ISO 3166-1 country code (e.g., ZAF, GBR, USA).</div>
            </div>
          </div>

          <div class="row field-video" style="display:none;">
            <div class="col-md-6 mb-3">
              <label for="iptc_production_country" class="form-label">Production country</label>
              <input type="text" name="iptc_production_country" id="iptc_production_country" class="form-control"
                     value="{{ old('production_country', $asset->production_country ?? '') }}">
              <div class="form-text text-muted small">Country where the content was produced (may differ from filming location).</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_production_country_code" class="form-label">Production country code</label>
              <input type="text" name="iptc_production_country_code" id="iptc_production_country_code" class="form-control" maxlength="10"
                     value="{{ old('production_country_code', $asset->production_country_code ?? '') }}" placeholder="e.g., NLD, ZAF">
              <div class="form-text text-muted small">ISO 3166-1 country code for the production country.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 9. IPTC - Copyright & Rights ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcRightsCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-copyright me-1"></i> IPTC - Copyright &amp; Rights
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcRightsCollapse">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_credit_line" class="form-label">Credit line</label>
              <input type="text" name="iptc_credit_line" id="iptc_credit_line" class="form-control"
                     value="{{ old('credit_line', $asset->credit_line ?? '') }}">
              <div class="form-text text-muted small">The credit line required when reproducing this asset.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_source" class="form-label">Source</label>
              <input type="text" name="iptc_source" id="iptc_source" class="form-control"
                     value="{{ old('source', $asset->source ?? '') }}">
              <div class="form-text text-muted small">The original owner or provider of the asset.</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="iptc_copyright_notice" class="form-label">Copyright notice</label>
            <input type="text" name="iptc_copyright_notice" id="iptc_copyright_notice" class="form-control"
                   value="{{ old('copyright_notice', $asset->copyright_notice ?? '') }}" placeholder="&copy; 2024 Photographer Name">
            <div class="form-text text-muted small">The copyright notice text (e.g., &copy; Year Name).</div>
          </div>

          <div class="mb-3">
            <label for="iptc_rights_usage_terms" class="form-label">Rights usage terms</label>
            <textarea name="iptc_rights_usage_terms" id="iptc_rights_usage_terms" class="form-control" rows="2">{{ old('rights_usage_terms', $asset->rights_usage_terms ?? '') }}</textarea>
            <div class="form-text text-muted small">Free-text instructions on how the asset can be used legally.</div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_license_type" class="form-label">License type</label>
              <select name="iptc_license_type" id="iptc_license_type" class="form-select">
                <option value="">-- Select --</option>
                @foreach([
                  'All Rights Reserved' => 'All Rights Reserved',
                  'Creative Commons BY' => 'Creative Commons BY',
                  'CC BY-SA' => 'CC BY-SA',
                  'CC BY-NC' => 'CC BY-NC',
                  'CC BY-NC-SA' => 'CC BY-NC-SA',
                  'CC BY-ND' => 'CC BY-ND',
                  'CC BY-NC-ND' => 'CC BY-NC-ND',
                  'Public Domain' => 'Public Domain',
                  'Editorial Use Only' => 'Editorial Use Only',
                  'Rights Managed' => 'Rights Managed',
                  'Royalty Free' => 'Royalty Free',
                ] as $val => $label)
                  <option value="{{ $val }}" @selected(old('license_type', $asset->license_type ?? '') === $val)>{{ $label }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The type of license governing use of this asset.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_license_url" class="form-label">License URL</label>
              <input type="text" name="iptc_license_url" id="iptc_license_url" class="form-control"
                     value="{{ old('license_url', $asset->license_url ?? '') }}">
              <div class="form-text text-muted small">URL to the full license text.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_license_expiry" class="form-label">License expiry</label>
              <input type="date" name="iptc_license_expiry" id="iptc_license_expiry" class="form-control"
                     value="{{ old('license_expiry', $asset->license_expiry ?? '') }}">
              <div class="form-text text-muted small">The date when the current license expires.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 10. IPTC - Model & Property Releases ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcReleasesCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-file-signature me-1"></i> IPTC - Model &amp; Property Releases
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcReleasesCollapse">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="iptc_model_release_status" class="form-label">Model release status</label>
                <select name="iptc_model_release_status" id="iptc_model_release_status" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach([
                    'None' => 'None',
                    'Not Applicable' => 'Not Applicable',
                    'Unlimited Model Release' => 'Unlimited Model Release',
                    'Limited Model Release' => 'Limited Model Release',
                  ] as $val => $label)
                    <option value="{{ $val }}" @selected(old('model_release_status', $asset->model_release_status ?? '') === $val)>{{ $label }}</option>
                  @endforeach
                </select>
                <div class="form-text text-muted small">The status of model releases for persons depicted.</div>
              </div>
              <div class="mb-3">
                <label for="iptc_model_release_id" class="form-label">Model release ID</label>
                <input type="text" name="iptc_model_release_id" id="iptc_model_release_id" class="form-control"
                       value="{{ old('model_release_id', $asset->model_release_id ?? '') }}">
                <div class="form-text text-muted small">Identifier of the model release document.</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="iptc_property_release_status" class="form-label">Property release status</label>
                <select name="iptc_property_release_status" id="iptc_property_release_status" class="form-select">
                  <option value="">-- Select --</option>
                  @foreach([
                    'None' => 'None',
                    'Not Applicable' => 'Not Applicable',
                    'Unlimited Property Release' => 'Unlimited Property Release',
                    'Limited Property Release' => 'Limited Property Release',
                  ] as $val => $label)
                    <option value="{{ $val }}" @selected(old('property_release_status', $asset->property_release_status ?? '') === $val)>{{ $label }}</option>
                  @endforeach
                </select>
                <div class="form-text text-muted small">The status of property releases for depicted properties.</div>
              </div>
              <div class="mb-3">
                <label for="iptc_property_release_id" class="form-label">Property release ID</label>
                <input type="text" name="iptc_property_release_id" id="iptc_property_release_id" class="form-control"
                       value="{{ old('property_release_id', $asset->property_release_id ?? '') }}">
                <div class="form-text text-muted small">Identifier of the property release document.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 11. IPTC - Artwork / Object in Image ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcArtworkCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-palette me-1"></i> IPTC - Artwork / Object in Image
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcArtworkCollapse">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_artwork_title" class="form-label">Artwork title</label>
              <input type="text" name="iptc_artwork_title" id="iptc_artwork_title" class="form-control"
                     value="{{ old('artwork_title', $asset->artwork_title ?? '') }}">
              <div class="form-text text-muted small">The title of the depicted artwork or object.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_artwork_creator" class="form-label">Artwork creator</label>
              <input type="text" name="iptc_artwork_creator" id="iptc_artwork_creator" class="form-control"
                     value="{{ old('artwork_creator', $asset->artwork_creator ?? '') }}">
              <div class="form-text text-muted small">The creator of the depicted artwork or object.</div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_artwork_date" class="form-label">Artwork date</label>
              <input type="text" name="iptc_artwork_date" id="iptc_artwork_date" class="form-control"
                     value="{{ old('artwork_date', $asset->artwork_date ?? '') }}" placeholder="e.g., 1889">
              <div class="form-text text-muted small">The date the artwork or object was created.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_artwork_source" class="form-label">Artwork source</label>
              <input type="text" name="iptc_artwork_source" id="iptc_artwork_source" class="form-control"
                     value="{{ old('artwork_source', $asset->artwork_source ?? '') }}">
              <div class="form-text text-muted small">The institution or collection holding the artwork.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_artwork_copyright" class="form-label">Artwork copyright</label>
              <input type="text" name="iptc_artwork_copyright" id="iptc_artwork_copyright" class="form-control"
                     value="{{ old('artwork_copyright', $asset->artwork_copyright ?? '') }}">
              <div class="form-text text-muted small">Copyright notice for the depicted artwork or object.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 12. IPTC - Administrative ===== --}}
    <div class="card mb-3">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#iptcAdminCollapse" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-cogs me-1"></i> IPTC - Administrative
        <i class="fas fa-chevron-down float-end"></i>
      </div>
      <div class="collapse" id="iptcAdminCollapse">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_title" class="form-label">Title (IPTC Object Name)</label>
              <input type="text" name="iptc_title" id="iptc_title" class="form-control"
                     value="{{ old('iptc_title', $asset->iptc_title ?? '') }}">
              <div class="form-text text-muted small">The IPTC Object Name, a shorthand reference for the asset.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_job_id" class="form-label">Job / Assignment ID</label>
              <input type="text" name="iptc_job_id" id="iptc_job_id" class="form-control"
                     value="{{ old('job_id', $asset->job_id ?? '') }}">
              <div class="form-text text-muted small">The job or assignment identifier for tracking purposes.</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="iptc_instructions" class="form-label">Special instructions</label>
            <textarea name="iptc_instructions" id="iptc_instructions" class="form-control" rows="2">{{ old('instructions', $asset->instructions ?? '') }}</textarea>
            <div class="form-text text-muted small">Any special instructions regarding the use of this asset.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-4">
      <button type="submit" class="btn atom-btn-outline-success btn-lg"><i class="fas fa-save"></i> {{ $asset ? 'Save Asset' : 'Create Asset' }}</button>
      <a href="{{ $asset ? route('dam.show', $asset->slug) : route('dam.dashboard') }}" class="btn atom-btn-white btn-lg">Cancel</a>
    </div>

    <div class="alert alert-info mt-4">
      <i class="fas fa-info-circle"></i> After creating the asset, you can attach digital files through the standard interface.
    </div>
  </form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const assetTypeSelect = document.getElementById('asset_type');
  const videoTypes = ['documentary', 'feature', 'short', 'news', 'interview', 'home_movie'];
  const audioTypes = ['oral_history', 'music', 'podcast', 'speech', 'interview'];
  const photoTypes = ['photo', 'artwork', 'scan'];
  const artworkTypes = ['artwork'];

  function toggleFields() {
    const val = assetTypeSelect.value;
    const isVideo = videoTypes.includes(val);
    const isAudio = audioTypes.includes(val);
    const isPhoto = photoTypes.includes(val);
    const isArtwork = artworkTypes.includes(val);

    document.querySelectorAll('.field-video').forEach(function (el) {
      el.style.display = isVideo ? '' : 'none';
    });
    document.querySelectorAll('.field-audio').forEach(function (el) {
      el.style.display = isAudio ? '' : 'none';
    });
    document.querySelectorAll('.field-photo').forEach(function (el) {
      el.style.display = isPhoto ? '' : 'none';
    });
    document.querySelectorAll('.field-artwork').forEach(function (el) {
      el.style.display = isArtwork ? '' : 'none';
    });

    // Elements with multiple classes (e.g., field-video field-audio) show if ANY matches
    document.querySelectorAll('.field-video.field-audio').forEach(function (el) {
      el.style.display = (isVideo || isAudio) ? '' : 'none';
    });
  }

  assetTypeSelect.addEventListener('change', toggleFields);
  toggleFields();

  // Credit management
  const creditsContainer = document.getElementById('credits-container');
  const addCreditBtn = document.getElementById('add-credit');

  if (addCreditBtn) {
    addCreditBtn.addEventListener('click', function () {
      const row = document.createElement('div');
      row.className = 'row credit-row mb-2';
      row.innerHTML = '<div class="col-md-4">' +
        '<input type="text" name="credit_role[]" class="form-control" placeholder="Role (e.g., Director)">' +
        '</div>' +
        '<div class="col-md-6">' +
        '<input type="text" name="credit_name[]" class="form-control" placeholder="Name">' +
        '</div>' +
        '<div class="col-md-2">' +
        '<button type="button" class="btn atom-btn-outline-danger btn-sm remove-credit"><i class="fas fa-times"></i></button>' +
        '</div>';
      creditsContainer.appendChild(row);
    });
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-credit')) {
      const row = e.target.closest('.credit-row');
      if (creditsContainer.querySelectorAll('.credit-row').length > 1) {
        row.remove();
      } else {
        row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
      }
    }
  });
});
</script>
@endpush
