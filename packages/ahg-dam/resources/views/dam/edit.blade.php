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
              <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $asset->title ?? '') }}">
            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text text-muted small">The title of the digital asset.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="identifier" class="form-label">
              Identifier / Reference code
              <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror"
                   value="{{ old('identifier', $asset->identifier ?? '') }}" placeholder="e.g., DAM-2024-001">
            @error('identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text text-muted small">A unique identifier for this DAM asset within the repository.</div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="parent_id" class="form-label">Parent Collection <span class="badge bg-secondary ms-1">Optional</span></label>
            <select class="form-select" id="parent_id" name="parent_id">
              <option value="1">-- Top level (no parent) --</option>
              @foreach($formChoices['parents'] ?? [] as $p)
                <option value="{{ $p->id }}" @selected(old('parent_id', $asset->parent_id ?? '') == $p->id)>{{ $p->title ?: $p->identifier }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="repository_id" class="form-label">Parent collection (Repository) <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="repository_id" id="repository_id" class="form-select">
              <option value="">-- Select repository --</option>
              @foreach($formChoices['repositories'] as $repo)
                <option value="{{ $repo->id }}" @selected(old('repository_id', $asset->repository_id ?? '') == $repo->id)>{{ $repo->name }}</option>
              @endforeach
            </select>
            <div class="form-text text-muted small">The repository that holds this asset.</div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="level_of_description_id" class="form-label">Level of description <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="level_of_description_id" id="level_of_description_id" class="form-select">
              <option value="">-- Select --</option>
              @foreach($formChoices['levels'] as $level)
                <option value="{{ $level->id }}" @selected(old('level_of_description_id', $asset->level_of_description_id ?? '') == $level->id)>{{ $level->name }}</option>
              @endforeach
            </select>
            <div class="form-text text-muted small">The level of arrangement of the unit of description.</div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="extent_and_medium" class="form-label">Extent and medium <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="extent_and_medium" id="extent_and_medium" class="form-control" rows="2">{{ old('extent_and_medium', $asset->extent_and_medium ?? '') }}</textarea>
            <div class="form-text text-muted small">File format, size, dimensions.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="scope_and_content" class="form-label">Scope and content <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="scope_and_content" id="scope_and_content" class="form-control" rows="2">{{ old('scope_and_content', $asset->scope_and_content ?? '') }}</textarea>
            <div class="form-text text-muted small">A description of the intellectual content and document types represented in this asset.</div>
          </div>
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
          <label for="asset_type" class="form-label">Asset type <span class="badge bg-secondary ms-1">Optional</span></label>
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
          <label for="genre" class="form-label">Genre <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="genre" id="genre" class="form-control"
                 value="{{ old('genre', $asset->genre ?? '') }}" placeholder="e.g., Documentary, Portrait">
          <div class="form-text text-muted small">The genre or category of the content.</div>
        </div>

        <div class="mb-3 field-video field-audio" style="display:none;">
          <label for="color_type" class="form-label">Color type <span class="badge bg-secondary ms-1">Optional</span></label>
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
          <div class="col-md-3 mb-3">
            <label for="duration_minutes" class="form-label">Running Time <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="input-group">
              <input type="number" name="duration_minutes" id="duration_minutes" class="form-control" min="1"
                     value="{{ old('duration_minutes', $asset->duration_minutes ?? '') }}">
              <span class="input-group-text">min</span>
            </div>
          </div>
          <div class="col-md-5 mb-3">
            <label for="production_company" class="form-label">Production company <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="production_company" id="production_company" class="form-control"
                   value="{{ old('production_company', $asset->production_company ?? '') }}" placeholder="e.g., African Film Productions">
          </div>
          <div class="col-md-4 mb-3">
            <label for="distributor" class="form-label">Distributor / Broadcaster <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="distributor" id="distributor" class="form-control"
                   value="{{ old('distributor', $asset->distributor ?? '') }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-3 mb-3">
            <label for="broadcast_date" class="form-label">Broadcast / Release Date <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="broadcast_date" id="broadcast_date" class="form-control"
                   value="{{ old('broadcast_date', $asset->broadcast_date ?? '') }}" placeholder="e.g., 1954">
          </div>
          <div class="col-md-3 mb-3">
            <label for="production_country" class="form-label">Production Country <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="production_country" id="production_country" class="form-control"
                   value="{{ old('production_country', $asset->production_country ?? '') }}" placeholder="e.g., South Africa">
          </div>
          <div class="col-md-2 mb-3">
            <label for="production_country_code" class="form-label">Country Code <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="production_country_code" id="production_country_code" class="form-control" maxlength="3"
                   value="{{ old('production_country_code', $asset->production_country_code ?? '') }}" placeholder="ZAF">
          </div>
          <div class="col-md-2 mb-3">
            <label for="season_number" class="form-label">Season <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="season_number" id="season_number" class="form-control"
                   value="{{ old('season_number', $asset->season_number ?? '') }}">
          </div>
          <div class="col-md-2 mb-3">
            <label for="episode_number" class="form-label">Episode <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="episode_number" id="episode_number" class="form-control"
                   value="{{ old('episode_number', $asset->episode_number ?? '') }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="series_title" class="form-label">Series title <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="series_title" id="series_title" class="form-control"
                   value="{{ old('series_title', $asset->series_title ?? '') }}">
          </div>
          <div class="col-md-6 mb-3">
            <label for="awards" class="form-label">Awards / Recognition <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="awards" id="awards_input" class="form-control"
                   value="{{ old('awards', $asset->awards ?? '') }}" placeholder="e.g., Nominated for Golden Calf Award">
          </div>
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
            <label for="audio_language" class="form-label">Audio language(s) <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="audio_language" id="audio_language" class="form-control"
                   value="{{ old('audio_language', $asset->audio_language ?? '') }}">
            <div class="form-text text-muted small">The language(s) of the audio track, comma-separated.</div>
          </div>
          <div class="col-md-6 mb-3">
            <label for="subtitle_language" class="form-label">Subtitle language(s) <span class="badge bg-secondary ms-1">Optional</span></label>
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
              <label for="iptc_creator" class="form-label">Creator / Photographer <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_creator" id="iptc_creator" class="form-control"
                     value="{{ old('creator', $asset->creator ?? '') }}">
              <div class="form-text text-muted small">The person or organisation that created the asset.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_creator_job_title" class="form-label">Job title <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_creator_job_title" id="iptc_creator_job_title" class="form-control"
                     value="{{ old('creator_job_title', $asset->creator_job_title ?? '') }}">
              <div class="form-text text-muted small">The job title of the creator.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_email" class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="email" name="iptc_creator_email" id="iptc_creator_email" class="form-control"
                     value="{{ old('creator_email', $asset->creator_email ?? '') }}">
              <div class="form-text text-muted small">Contact email address for the creator.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_phone" class="form-label">Phone <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_creator_phone" id="iptc_creator_phone" class="form-control"
                     value="{{ old('creator_phone', $asset->creator_phone ?? '') }}">
              <div class="form-text text-muted small">Contact phone number for the creator.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_website" class="form-label">Website <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_creator_website" id="iptc_creator_website" class="form-control"
                     value="{{ old('creator_website', $asset->creator_website ?? '') }}">
              <div class="form-text text-muted small">Website URL for the creator.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_creator_city" class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_creator_city" id="iptc_creator_city" class="form-control"
                     value="{{ old('creator_city', $asset->creator_city ?? '') }}">
              <div class="form-text text-muted small">The city where the creator is located.</div>
            </div>
            <div class="col-md-8 mb-3">
              <label for="iptc_creator_address" class="form-label">Address <span class="badge bg-secondary ms-1">Optional</span></label>
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
            <label for="iptc_headline" class="form-label">Headline <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="iptc_headline" id="iptc_headline" class="form-control"
                   value="{{ old('headline', $asset->headline ?? '') }}">
            <div class="form-text text-muted small">A brief synopsis or summary of the content.</div>
          </div>



          <div class="mb-3">
            <label for="iptc_caption" class="form-label">Caption / Description <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="iptc_caption" id="iptc_caption" class="form-control" rows="3">{{ old('caption', $asset->caption ?? '') }}</textarea>
            <div class="form-text text-muted small">A textual description of the content.</div>
          </div>

          <div class="mb-3">
            <label for="iptc_keywords" class="form-label">Keywords <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="iptc_keywords" id="iptc_keywords" class="form-control"
                   value="{{ old('keywords', $asset->keywords ?? '') }}" placeholder="Comma-separated">
            <div class="form-text text-muted small">Keywords or tags describing the content, comma-separated.</div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_subject_code" class="form-label">IPTC Subject Code <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_subject_code" id="iptc_subject_code" class="form-control"
                     value="{{ old('iptc_subject_code', $asset->iptc_subject_code ?? '') }}">
              <div class="form-text text-muted small">IPTC subject reference code for categorisation.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_intellectual_genre" class="form-label">Intellectual genre <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_intellectual_genre" id="iptc_intellectual_genre" class="form-control"
                     value="{{ old('intellectual_genre', $asset->intellectual_genre ?? '') }}">
              <div class="form-text text-muted small">The intellectual genre of the content (e.g., Feature, Actuality).</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="iptc_persons_shown" class="form-label">Persons shown <span class="badge bg-secondary ms-1">Optional</span></label>
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
            <label for="iptc_date_created" class="form-label">Date created <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" name="iptc_date_created" id="iptc_date_created" class="form-control"
                   value="{{ old('date_created', $asset->iptc_date_created ?? '') }}">
            <div class="form-text text-muted small">The date the intellectual content was created.</div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_city" class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_city" id="iptc_city" class="form-control"
                     value="{{ old('city', $asset->city ?? '') }}">
              <div class="form-text text-muted small">The city where the content was created or depicts.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_state_province" class="form-label">State / Province <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_state_province" id="iptc_state_province" class="form-control"
                     value="{{ old('state_province', $asset->state_province ?? '') }}">
              <div class="form-text text-muted small">The state or province of the location.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_sublocation" class="form-label">Sublocation <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_sublocation" id="iptc_sublocation" class="form-control"
                     value="{{ old('sublocation', $asset->sublocation ?? '') }}">
              <div class="form-text text-muted small">A more specific location within the city.</div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="iptc_country" class="form-label">Country <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_country" id="iptc_country" class="form-control"
                     value="{{ old('country', $asset->country ?? '') }}">
              <div class="form-text text-muted small">The country name of the location depicted.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_country_code" class="form-label">Country code <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_country_code" id="iptc_country_code" class="form-control" maxlength="10"
                     value="{{ old('country_code', $asset->country_code ?? '') }}" placeholder="ISO 3166-1 alpha-3">
              <div class="form-text text-muted small">ISO 3166-1 country code (e.g., ZAF, GBR, USA).</div>
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
              <label for="iptc_credit_line" class="form-label">Credit line <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="iptc_credit_line" id="iptc_credit_line" class="form-control"
                     value="{{ old('credit_line', $asset->credit_line ?? '') }}">
              <div class="form-text text-muted small">The credit line required when reproducing this asset.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_source" class="form-label">Source <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_source" id="iptc_source" class="form-control"
                     value="{{ old('source', $asset->source ?? '') }}">
              <div class="form-text text-muted small">The original owner or provider of the asset.</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="iptc_copyright_notice" class="form-label">Copyright notice <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="iptc_copyright_notice" id="iptc_copyright_notice" class="form-control"
                   value="{{ old('copyright_notice', $asset->copyright_notice ?? '') }}" placeholder="&copy; 2024 Photographer Name">
            <div class="form-text text-muted small">The copyright notice text (e.g., &copy; Year Name).</div>
          </div>

          <div class="mb-3">
            <label for="iptc_rights_usage_terms" class="form-label">Rights usage terms <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="iptc_rights_usage_terms" id="iptc_rights_usage_terms" class="form-control" rows="2">{{ old('rights_usage_terms', $asset->rights_usage_terms ?? '') }}</textarea>
            <div class="form-text text-muted small">Free-text instructions on how the asset can be used legally.</div>
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_license_type" class="form-label">License type <span class="badge bg-secondary ms-1">Optional</span></label>
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
              <label for="iptc_license_url" class="form-label">License URL <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_license_url" id="iptc_license_url" class="form-control"
                     value="{{ old('license_url', $asset->license_url ?? '') }}">
              <div class="form-text text-muted small">URL to the full license text.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_license_expiry" class="form-label">License expiry <span class="badge bg-secondary ms-1">Optional</span></label>
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
                <label for="iptc_model_release_status" class="form-label">Model release status <span class="badge bg-secondary ms-1">Optional</span></label>
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
                <label for="iptc_model_release_id" class="form-label">Model release ID <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="iptc_model_release_id" id="iptc_model_release_id" class="form-control"
                       value="{{ old('model_release_id', $asset->model_release_id ?? '') }}">
                <div class="form-text text-muted small">Identifier of the model release document.</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="iptc_property_release_status" class="form-label">Property release status <span class="badge bg-secondary ms-1">Optional</span></label>
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
                <label for="iptc_property_release_id" class="form-label">Property release ID <span class="badge bg-secondary ms-1">Optional</span></label>
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
              <label for="iptc_artwork_title" class="form-label">Artwork title <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_artwork_title" id="iptc_artwork_title" class="form-control"
                     value="{{ old('artwork_title', $asset->artwork_title ?? '') }}">
              <div class="form-text text-muted small">The title of the depicted artwork or object.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_artwork_creator" class="form-label">Artwork creator <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_artwork_creator" id="iptc_artwork_creator" class="form-control"
                     value="{{ old('artwork_creator', $asset->artwork_creator ?? '') }}">
              <div class="form-text text-muted small">The creator of the depicted artwork or object.</div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label for="iptc_artwork_date" class="form-label">Artwork date <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_artwork_date" id="iptc_artwork_date" class="form-control"
                     value="{{ old('artwork_date', $asset->artwork_date ?? '') }}" placeholder="e.g., 1889">
              <div class="form-text text-muted small">The date the artwork or object was created.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_artwork_source" class="form-label">Artwork source <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_artwork_source" id="iptc_artwork_source" class="form-control"
                     value="{{ old('artwork_source', $asset->artwork_source ?? '') }}">
              <div class="form-text text-muted small">The institution or collection holding the artwork.</div>
            </div>
            <div class="col-md-4 mb-3">
              <label for="iptc_artwork_copyright" class="form-label">Artwork copyright <span class="badge bg-secondary ms-1">Optional</span></label>
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
              <label for="iptc_title" class="form-label">Title (IPTC Object Name) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_title" id="iptc_title" class="form-control"
                     value="{{ old('iptc_title', $asset->iptc_title ?? '') }}">
              <div class="form-text text-muted small">The IPTC Object Name, a shorthand reference for the asset.</div>
            </div>
            <div class="col-md-6 mb-3">
              <label for="iptc_job_id" class="form-label">Job / Assignment ID <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="iptc_job_id" id="iptc_job_id" class="form-control"
                     value="{{ old('job_id', $asset->job_id ?? '') }}">
              <div class="form-text text-muted small">The job or assignment identifier for tracking purposes.</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="iptc_instructions" class="form-label">Special instructions <span class="badge bg-secondary ms-1">Optional</span></label>
            <textarea name="iptc_instructions" id="iptc_instructions" class="form-control" rows="2">{{ old('instructions', $asset->instructions ?? '') }}</textarea>
            <div class="form-text text-muted small">Any special instructions regarding the use of this asset.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== 13. Alternative Versions ===== --}}
    <div class="card mb-3">
      <div class="card-header" style="background:#17a2b8;color:#fff">
        <i class="fas fa-language me-1"></i> Alternative Versions
      </div>
      <div class="card-body">
        <p class="text-muted small">Other language versions, formats, or edits of this work</p>
        <div id="versions-container">
          @foreach($versionLinks as $v)
          <div class="version-row border rounded p-2 mb-2 bg-light">
            <input type="hidden" name="version_id[]" value="{{ $v->id }}">
            <div class="row mb-2">
              <div class="col-md-4">
                <label class="form-label small">Title <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="version_title[]" value="{{ $v->title }}" placeholder="e.g., Kuddes van die veld">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select form-select-sm" name="version_type[]">
                  <option value="language" @selected($v->version_type === 'language')>Language</option>
                  <option value="format" @selected($v->version_type === 'format')>Format</option>
                  <option value="restoration" @selected($v->version_type === 'restoration')>Restoration</option>
                  <option value="directors_cut" @selected($v->version_type === 'directors_cut')>Director's Cut</option>
                  <option value="censored" @selected($v->version_type === 'censored')>Censored</option>
                  <option value="other" @selected($v->version_type === 'other')>Other</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small">Language <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="version_language[]" value="{{ $v->language_name }}" placeholder="Afrikaans">
              </div>
              <div class="col-md-2">
                <label class="form-label small">ISO Code <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="version_language_code[]" value="{{ $v->language_code }}" maxlength="3" placeholder="afr">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Year <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="version_year[]" value="{{ $v->year }}" placeholder="1954">
              </div>
            </div>
            <div class="row">
              <div class="col-md-11">
                <label class="form-label small">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="version_notes[]" value="{{ $v->notes }}" placeholder="Additional information about this version">
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-version w-100"><i class="fas fa-times"></i></button>
              </div>
            </div>
          </div>
          @endforeach
        </div>
        <button type="button" class="btn btn-sm atom-btn-white" id="addVersionBtn">
          <i class="fas fa-plus"></i> Add Version
        </button>
      </div>
    </div>

    {{-- ===== 14. Format Holdings & Access ===== --}}
    <div class="card mb-3">
      <div class="card-header" style="background:#6c757d;color:#fff">
        <i class="fas fa-archive me-1"></i> Format Holdings &amp; Access
      </div>
      <div class="card-body">
        <p class="text-muted small">Physical formats held at institutions</p>
        <div id="holdings-container">
          @foreach($formatHoldings as $h)
          <div class="holding-row border rounded p-2 mb-2 bg-light">
            <input type="hidden" name="holding_id[]" value="{{ $h->id }}">
            <div class="row mb-2">
              <div class="col-md-2">
                <label class="form-label small">Format <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select form-select-sm" name="holding_format[]">
                  <optgroup label="Film">
                    <option value="35mm" @selected($h->format_type === '35mm')>35mm</option>
                    <option value="16mm" @selected($h->format_type === '16mm')>16mm</option>
                    <option value="8mm" @selected($h->format_type === '8mm')>8mm</option>
                    <option value="Super8" @selected($h->format_type === 'Super8')>Super 8</option>
                    <option value="Nitrate" @selected($h->format_type === 'Nitrate')>Nitrate</option>
                    <option value="Safety" @selected($h->format_type === 'Safety')>Safety</option>
                    <option value="Polyester" @selected($h->format_type === 'Polyester')>Polyester</option>
                  </optgroup>
                  <optgroup label="Video">
                    <option value="VHS" @selected($h->format_type === 'VHS')>VHS</option>
                    <option value="Betacam" @selected($h->format_type === 'Betacam')>Betacam</option>
                    <option value="U-matic" @selected($h->format_type === 'U-matic')>U-matic</option>
                    <option value="DV" @selected($h->format_type === 'DV')>DV</option>
                  </optgroup>
                  <optgroup label="Digital">
                    <option value="DVD" @selected($h->format_type === 'DVD')>DVD</option>
                    <option value="Blu-ray" @selected($h->format_type === 'Blu-ray')>Blu-ray</option>
                    <option value="LaserDisc" @selected($h->format_type === 'LaserDisc')>LaserDisc</option>
                    <option value="Digital_File" @selected($h->format_type === 'Digital_File')>Digital File</option>
                    <option value="DCP" @selected($h->format_type === 'DCP')>DCP</option>
                    <option value="ProRes" @selected($h->format_type === 'ProRes')>ProRes</option>
                  </optgroup>
                  <optgroup label="Audio">
                    <option value="Audio_Reel" @selected($h->format_type === 'Audio_Reel')>Audio Reel</option>
                    <option value="Audio_Cassette" @selected($h->format_type === 'Audio_Cassette')>Audio Cassette</option>
                    <option value="Vinyl" @selected($h->format_type === 'Vinyl')>Vinyl</option>
                    <option value="CD" @selected($h->format_type === 'CD')>CD</option>
                  </optgroup>
                  <option value="Other" @selected($h->format_type === 'Other')>Other</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small">Format Details <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="holding_format_details[]" value="{{ $h->format_details }}" placeholder="Color, sound, ratio">
              </div>
              <div class="col-md-3">
                <label class="form-label small">Institution <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="holding_institution[]" value="{{ $h->holding_institution }}" placeholder="e.g., NFVSA, WCPLS">
              </div>
              <div class="col-md-3">
                <label class="form-label small">Location <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="holding_location[]" value="{{ $h->holding_location }}" placeholder="Department/vault">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Accession # <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="holding_accession[]" value="{{ $h->accession_number }}" placeholder="Ref number">
              </div>
            </div>
            <div class="row mb-2">
              <div class="col-md-2">
                <label class="form-label small">Condition <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select form-select-sm" name="holding_condition[]">
                  <option value="unknown" @selected($h->condition_status === 'unknown')>Unknown</option>
                  <option value="excellent" @selected($h->condition_status === 'excellent')>Excellent</option>
                  <option value="good" @selected($h->condition_status === 'good')>Good</option>
                  <option value="fair" @selected($h->condition_status === 'fair')>Fair</option>
                  <option value="poor" @selected($h->condition_status === 'poor')>Poor</option>
                  <option value="deteriorating" @selected($h->condition_status === 'deteriorating')>Deteriorating</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label small">Access <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select form-select-sm" name="holding_access[]">
                  <option value="unknown" @selected($h->access_status === 'unknown')>Unknown</option>
                  <option value="available" @selected($h->access_status === 'available')>Available</option>
                  <option value="restricted" @selected($h->access_status === 'restricted')>Restricted</option>
                  <option value="preservation_only" @selected($h->access_status === 'preservation_only')>Preservation Only</option>
                  <option value="digitized_available" @selected($h->access_status === 'digitized_available')>Digitized</option>
                  <option value="on_request" @selected($h->access_status === 'on_request')>On Request</option>
                  <option value="staff_only" @selected($h->access_status === 'staff_only')>Staff Only</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label small">Access URL <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="url" class="form-control form-control-sm" name="holding_url[]" value="{{ $h->access_url }}" placeholder="Streaming/download URL">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Verified Date <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" class="form-control form-control-sm" name="holding_verified[]" value="{{ $h->verified_date }}">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Primary <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-check mt-1">
                  <input type="checkbox" class="form-check-input" name="holding_primary[]" value="{{ $h->id }}" @checked($h->is_primary)>
                  <label class="form-check-label small">Primary copy <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <label class="form-label small">Access Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="holding_access_notes[]" value="{{ $h->access_notes }}" placeholder="How to request, viewing conditions">
              </div>
              <div class="col-md-5">
                <label class="form-label small">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="holding_notes[]" value="{{ $h->notes }}" placeholder="Additional notes">
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-holding w-100"><i class="fas fa-times"></i></button>
              </div>
            </div>
          </div>
          @endforeach
        </div>
        <button type="button" class="btn btn-sm atom-btn-white" id="addHoldingBtn">
          <i class="fas fa-plus"></i> Add Holding
        </button>
      </div>
    </div>

    {{-- ===== 15. External References ===== --}}
    <div class="card mb-3">
      <div class="card-header" style="background:#28a745;color:#fff">
        <i class="fas fa-external-link-alt me-1"></i> External References
      </div>
      <div class="card-body">
        <p class="text-muted small">Links to ESAT, IMDb, Wikipedia, and other databases</p>
        <div id="links-container">
          @foreach($externalLinks as $l)
          <div class="link-row border rounded p-2 mb-2 bg-light">
            <input type="hidden" name="link_id[]" value="{{ $l->id }}">
            <div class="row mb-2">
              <div class="col-md-2">
                <label class="form-label small">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select form-select-sm" name="link_type[]">
                  <optgroup label="South African">
                    <option value="ESAT" @selected($l->link_type === 'ESAT')>ESAT</option>
                    <option value="SAFILM" @selected($l->link_type === 'SAFILM')>SA Film</option>
                    <option value="NFVSA" @selected($l->link_type === 'NFVSA')>NFVSA</option>
                  </optgroup>
                  <optgroup label="Film Databases">
                    <option value="IMDb" @selected($l->link_type === 'IMDb')>IMDb</option>
                    <option value="BFI" @selected($l->link_type === 'BFI')>BFI</option>
                    <option value="AFI" @selected($l->link_type === 'AFI')>AFI</option>
                    <option value="Letterboxd" @selected($l->link_type === 'Letterboxd')>Letterboxd</option>
                    <option value="MUBI" @selected($l->link_type === 'MUBI')>MUBI</option>
                    <option value="Filmography" @selected($l->link_type === 'Filmography')>Filmography</option>
                  </optgroup>
                  <optgroup label="Knowledge Bases">
                    <option value="Wikipedia" @selected($l->link_type === 'Wikipedia')>Wikipedia</option>
                    <option value="Wikidata" @selected($l->link_type === 'Wikidata')>Wikidata</option>
                    <option value="VIAF" @selected($l->link_type === 'VIAF')>VIAF</option>
                  </optgroup>
                  <optgroup label="Media Platforms">
                    <option value="YouTube" @selected($l->link_type === 'YouTube')>YouTube</option>
                    <option value="Vimeo" @selected($l->link_type === 'Vimeo')>Vimeo</option>
                    <option value="Archive_org" @selected($l->link_type === 'Archive_org')>Archive.org</option>
                  </optgroup>
                  <optgroup label="Other">
                    <option value="Review" @selected($l->link_type === 'Review')>Review</option>
                    <option value="Academic" @selected($l->link_type === 'Academic')>Academic</option>
                    <option value="Press" @selected($l->link_type === 'Press')>Press</option>
                    <option value="Other" @selected($l->link_type === 'Other')>Other</option>
                  </optgroup>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label small">URL <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="url" class="form-control form-control-sm" name="link_url[]" value="{{ $l->url }}" placeholder="https://...">
              </div>
              <div class="col-md-3">
                <label class="form-label small">Title <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="link_title[]" value="{{ $l->title }}" placeholder="Link display text">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Verified <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" class="form-control form-control-sm" name="link_verified[]" value="{{ $l->verified_date }}">
              </div>
              <div class="col-md-1">
                <label class="form-label small">Primary <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-check mt-1">
                  <input type="checkbox" class="form-check-input" name="link_primary[]" value="{{ $l->id }}" @checked($l->is_primary)>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-2">
                <label class="form-label small">Person <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="link_person[]" value="{{ $l->person_name }}" placeholder="e.g., Donald Swanson">
              </div>
              <div class="col-md-2">
                <label class="form-label small">Role <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="link_role[]" value="{{ $l->person_role }}" placeholder="Director, Actor">
              </div>
              <div class="col-md-7">
                <label class="form-label small">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" class="form-control form-control-sm" name="link_description[]" value="{{ $l->description }}" placeholder="What this link provides">
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-link w-100"><i class="fas fa-times"></i></button>
              </div>
            </div>
          </div>
          @endforeach
        </div>
        <button type="button" class="btn btn-sm atom-btn-white" id="addLinkBtn">
          <i class="fas fa-plus"></i> Add Link
        </button>
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

@push('js')
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
    if (e.target.closest('.btn-remove-version')) {
      e.target.closest('.version-row').remove();
    }
    if (e.target.closest('.btn-remove-holding')) {
      e.target.closest('.holding-row').remove();
    }
    if (e.target.closest('.btn-remove-link')) {
      e.target.closest('.link-row').remove();
    }
  });

  // Add Version row
  var addVersionBtn = document.getElementById('addVersionBtn');
  if (addVersionBtn) {
    addVersionBtn.addEventListener('click', function () {
      var container = document.getElementById('versions-container');
      var row = document.createElement('div');
      row.className = 'version-row border rounded p-2 mb-2 bg-light';
      row.innerHTML = '<input type="hidden" name="version_id[]" value="">' +
        '<div class="row mb-2">' +
        '<div class="col-md-4"><label class="form-label small">Title <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="version_title[]" placeholder="e.g., Kuddes van die veld"></div>' +
        '<div class="col-md-2"><label class="form-label small">Type <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select form-select-sm" name="version_type[]"><option value="language">Language</option><option value="format">Format</option><option value="restoration">Restoration</option><option value="directors_cut">Director\'s Cut</option><option value="censored">Censored</option><option value="other">Other</option></select></div>' +
        '<div class="col-md-2"><label class="form-label small">Language <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="version_language[]" placeholder="Afrikaans"></div>' +
        '<div class="col-md-2"><label class="form-label small">ISO Code <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="version_language_code[]" maxlength="3" placeholder="afr"></div>' +
        '<div class="col-md-2"><label class="form-label small">Year <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="version_year[]" placeholder="1954"></div>' +
        '</div>' +
        '<div class="row">' +
        '<div class="col-md-11"><label class="form-label small">Notes <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="version_notes[]" placeholder="Additional information"></div>' +
        '<div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-version w-100"><i class="fas fa-times"></i></button></div>' +
        '</div>';
      container.appendChild(row);
    });
  }

  // Add Holding row
  var addHoldingBtn = document.getElementById('addHoldingBtn');
  if (addHoldingBtn) {
    addHoldingBtn.addEventListener('click', function () {
      var container = document.getElementById('holdings-container');
      var row = document.createElement('div');
      row.className = 'holding-row border rounded p-2 mb-2 bg-light';
      row.innerHTML = '<input type="hidden" name="holding_id[]" value="">' +
        '<div class="row mb-2">' +
        '<div class="col-md-2"><label class="form-label small">Format <span class="badge bg-secondary ms-1">Optional</span></label>' +
        '<select class="form-select form-select-sm" name="holding_format[]">' +
        '<optgroup label="Film"><option value="35mm">35mm</option><option value="16mm">16mm</option><option value="8mm">8mm</option><option value="Super8">Super 8</option><option value="Nitrate">Nitrate</option><option value="Safety">Safety</option><option value="Polyester">Polyester</option></optgroup>' +
        '<optgroup label="Video"><option value="VHS">VHS</option><option value="Betacam">Betacam</option><option value="U-matic">U-matic</option><option value="DV">DV</option></optgroup>' +
        '<optgroup label="Digital"><option value="DVD">DVD</option><option value="Blu-ray">Blu-ray</option><option value="LaserDisc">LaserDisc</option><option value="Digital_File">Digital File</option><option value="DCP">DCP</option><option value="ProRes">ProRes</option></optgroup>' +
        '<optgroup label="Audio"><option value="Audio_Reel">Audio Reel</option><option value="Audio_Cassette">Audio Cassette</option><option value="Vinyl">Vinyl</option><option value="CD">CD</option></optgroup>' +
        '<option value="Other">Other</option></select></div>' +
        '<div class="col-md-2"><label class="form-label small">Format Details <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="holding_format_details[]" placeholder="Color, sound"></div>' +
        '<div class="col-md-3"><label class="form-label small">Institution <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="holding_institution[]" placeholder="e.g., NFVSA"></div>' +
        '<div class="col-md-3"><label class="form-label small">Location <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="holding_location[]" placeholder="Department/vault"></div>' +
        '<div class="col-md-2"><label class="form-label small">Accession # <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="holding_accession[]" placeholder="Ref number"></div>' +
        '</div>' +
        '<div class="row mb-2">' +
        '<div class="col-md-2"><label class="form-label small">Condition <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select form-select-sm" name="holding_condition[]"><option value="unknown">Unknown</option><option value="excellent">Excellent</option><option value="good">Good</option><option value="fair">Fair</option><option value="poor">Poor</option><option value="deteriorating">Deteriorating</option></select></div>' +
        '<div class="col-md-2"><label class="form-label small">Access <span class="badge bg-secondary ms-1">Optional</span></label><select class="form-select form-select-sm" name="holding_access[]"><option value="unknown">Unknown</option><option value="available">Available</option><option value="restricted">Restricted</option><option value="preservation_only">Preservation Only</option><option value="digitized_available">Digitized</option><option value="on_request">On Request</option><option value="staff_only">Staff Only</option></select></div>' +
        '<div class="col-md-3"><label class="form-label small">Access URL <span class="badge bg-secondary ms-1">Optional</span></label><input type="url" class="form-control form-control-sm" name="holding_url[]" placeholder="Streaming URL"></div>' +
        '<div class="col-md-2"><label class="form-label small">Verified Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control form-control-sm" name="holding_verified[]"></div>' +
        '<div class="col-md-2"><label class="form-label small">Primary <span class="badge bg-secondary ms-1">Optional</span></label><div class="form-check mt-1"><input type="checkbox" class="form-check-input" name="holding_primary[]" value="new"><label class="form-check-label small">Primary copy <span class="badge bg-secondary ms-1">Optional</span></label></div></div>' +
        '</div>' +
        '<div class="row">' +
        '<div class="col-md-6"><label class="form-label small">Access Notes <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="holding_access_notes[]" placeholder="How to request, viewing conditions"></div>' +
        '<div class="col-md-5"><label class="form-label small">Notes <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="holding_notes[]" placeholder="Additional notes"></div>' +
        '<div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-holding w-100"><i class="fas fa-times"></i></button></div>' +
        '</div>';
      container.appendChild(row);
    });
  }

  // Add External Link row
  var addLinkBtn = document.getElementById('addLinkBtn');
  if (addLinkBtn) {
    addLinkBtn.addEventListener('click', function () {
      var container = document.getElementById('links-container');
      var row = document.createElement('div');
      row.className = 'link-row border rounded p-2 mb-2 bg-light';
      row.innerHTML = '<input type="hidden" name="link_id[]" value="">' +
        '<div class="row mb-2">' +
        '<div class="col-md-2"><label class="form-label small">Type <span class="badge bg-secondary ms-1">Optional</span></label>' +
        '<select class="form-select form-select-sm" name="link_type[]">' +
        '<optgroup label="South African"><option value="ESAT">ESAT</option><option value="SAFILM">SA Film</option><option value="NFVSA">NFVSA</option></optgroup>' +
        '<optgroup label="Film Databases"><option value="IMDb">IMDb</option><option value="BFI">BFI</option><option value="AFI">AFI</option><option value="Letterboxd">Letterboxd</option><option value="MUBI">MUBI</option><option value="Filmography">Filmography</option></optgroup>' +
        '<optgroup label="Knowledge Bases"><option value="Wikipedia">Wikipedia</option><option value="Wikidata">Wikidata</option><option value="VIAF">VIAF</option></optgroup>' +
        '<optgroup label="Media Platforms"><option value="YouTube">YouTube</option><option value="Vimeo">Vimeo</option><option value="Archive_org">Archive.org</option></optgroup>' +
        '<optgroup label="Other"><option value="Review">Review</option><option value="Academic">Academic</option><option value="Press">Press</option><option value="Other">Other</option></optgroup>' +
        '</select></div>' +
        '<div class="col-md-4"><label class="form-label small">URL <span class="badge bg-secondary ms-1">Optional</span></label><input type="url" class="form-control form-control-sm" name="link_url[]" placeholder="https://..."></div>' +
        '<div class="col-md-3"><label class="form-label small">Title <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="link_title[]" placeholder="Link display text"></div>' +
        '<div class="col-md-2"><label class="form-label small">Verified <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control form-control-sm" name="link_verified[]"></div>' +
        '<div class="col-md-1"><label class="form-label small">Primary <span class="badge bg-secondary ms-1">Optional</span></label><div class="form-check mt-1"><input type="checkbox" class="form-check-input" name="link_primary[]" value="new"></div></div>' +
        '</div>' +
        '<div class="row">' +
        '<div class="col-md-2"><label class="form-label small">Person <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="link_person[]" placeholder="e.g., Donald Swanson"></div>' +
        '<div class="col-md-2"><label class="form-label small">Role <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="link_role[]" placeholder="Director, Actor"></div>' +
        '<div class="col-md-7"><label class="form-label small">Description <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control form-control-sm" name="link_description[]" placeholder="What this link provides"></div>' +
        '<div class="col-md-1 d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-link w-100"><i class="fas fa-times"></i></button></div>' +
        '</div>';
      container.appendChild(row);
    });
  }
});
</script>
@endpush
