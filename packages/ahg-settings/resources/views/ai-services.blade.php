@extends('theme::layouts.2col')
@section('title', 'AI Services Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('content')
@php
  // Decode JSON arrays for checkboxes
  $selectedEntityTypes = json_decode($settings['ner_entity_types'] ?? '[]', true) ?: [];
  $selectedSpellcheckFields = json_decode($settings['spellcheck_fields'] ?? '[]', true) ?: [];
  $selectedTranslationFields = json_decode($settings['translation_fields'] ?? '["title","scope_and_content"]', true) ?: [];
  $fieldMappings = json_decode($settings['translation_field_mappings'] ?? '{}', true) ?: [];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-brain text-primary"></i> {{ __('AI Services Settings') }}</h1>
  <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i>{{ __('Back to AHG Settings') }}
  </a>
</div>

<form method="post" action="{{ route('settings.ai-services') }}">
  @csrf

  {{-- ── General Settings ──────────────────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-cog me-2"></i>{{ __('General Settings') }}</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Processing Mode') }}</label>
        <div class="col-sm-9">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="processing_mode" id="mode_hybrid" value="hybrid"
              {{ ($settings['processing_mode'] ?? 'job') === 'hybrid' ? 'checked' : '' }}>
            <label class="form-check-label" for="mode_hybrid">
              <strong>{{ __('Hybrid') }}</strong> - Interactive for small docs, background for large
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="processing_mode" id="mode_job" value="job"
              {{ ($settings['processing_mode'] ?? 'job') === 'job' ? 'checked' : '' }}>
            <label class="form-check-label" for="mode_job">
              <strong>{{ __('Background Job') }}</strong> - Always queue (recommended for production)
            </label>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('API Endpoint') }}</label>
        <div class="col-sm-6">
          <input type="text" class="form-control" name="api_url"
            value="{{ $settings['api_url'] ?? '' }}">
          <small class="text-muted">{{ __('URL of the AI service (e.g., http://localhost:5004/ai/v1)') }}</small>
        </div>
        <div class="col-sm-3">
          <button type="button" class="btn btn-outline-info" id="btn-test-connection">
            <i class="fas fa-plug me-1"></i>{{ __('Test Connection') }}
          </button>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('API Key') }}</label>
        <div class="col-sm-6">
          <input type="password" class="form-control" name="api_key" id="api_key"
            value="{{ $settings['api_key'] ?? '' }}">
        </div>
        <div class="col-sm-3">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-toggle-api-key">
            <i class="fas fa-eye"></i> {{ __('Show') }}
          </button>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Timeout (seconds)') }}</label>
        <div class="col-sm-3">
          <input type="number" class="form-control" name="api_timeout" min="10" max="300"
            value="{{ $settings['api_timeout'] ?? '60' }}">
        </div>
      </div>
    </div>
  </div>

  {{-- ── NER Settings ──────────────────────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="fas fa-user-tag me-2"></i>{{ __('Named Entity Recognition (NER)') }}</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="ner_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="ner_enabled" id="ner_enabled" value="1"
              {{ ($settings['ner_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="ner_enabled">{{ __('Enable NER') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">{{ __('Extract people, organizations, places, and dates from records') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="auto_extract_on_upload" value="0">
            <input class="form-check-input" type="checkbox" name="auto_extract_on_upload" id="auto_extract" value="1"
              {{ ($settings['auto_extract_on_upload'] ?? '0') === '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="auto_extract">{{ __('Auto-extract on upload') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">{{ __('Automatically queue NER job when documents are uploaded') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Entity Types') }}</label>
        <div class="col-sm-9">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="entity_PERSON" id="entity_person" value="1"
              {{ in_array('PERSON', $selectedEntityTypes) ? 'checked' : '' }}>
            <label class="form-check-label" for="entity_person"><i class="fas fa-user me-1"></i>People</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="entity_ORG" id="entity_org" value="1"
              {{ in_array('ORG', $selectedEntityTypes) ? 'checked' : '' }}>
            <label class="form-check-label" for="entity_org"><i class="fas fa-building me-1"></i>Organizations</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="entity_GPE" id="entity_gpe" value="1"
              {{ in_array('GPE', $selectedEntityTypes) ? 'checked' : '' }}>
            <label class="form-check-label" for="entity_gpe"><i class="fas fa-map-marker-alt me-1"></i>Places</label>
          </div>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="entity_DATE" id="entity_date" value="1"
              {{ in_array('DATE', $selectedEntityTypes) ? 'checked' : '' }}>
            <label class="form-check-label" for="entity_date"><i class="fas fa-calendar me-1"></i>Dates</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Summarization Settings ────────────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Summarization') }}</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="summarizer_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="summarizer_enabled" id="summarizer_enabled" value="1"
              {{ ($settings['summarizer_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="summarizer_enabled">{{ __('Enable Summarization') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">{{ __('Generate summaries from document content') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Save Summary To') }}</label>
        <div class="col-sm-6">
          <select class="form-select" name="summary_field">
            @foreach($summaryFields as $value => $label)
              <option value="{{ $value }}" {{ ($settings['summary_field'] ?? 'scopeAndContent') === $value ? 'selected' : '' }}>
                {{ $label }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">{{ __('Which ISAD(G) field to populate with the generated summary') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Summary Length') }}</label>
        <div class="col-sm-3">
          <div class="input-group">
            <span class="input-group-text">{{ __('Min') }}</span>
            <input type="number" class="form-control" name="summarizer_min_length" min="50" max="500"
              value="{{ $settings['summarizer_min_length'] ?? '100' }}">
          </div>
        </div>
        <div class="col-sm-3">
          <div class="input-group">
            <span class="input-group-text">{{ __('Max') }}</span>
            <input type="number" class="form-control" name="summarizer_max_length" min="100" max="2000"
              value="{{ $settings['summarizer_max_length'] ?? '500' }}">
          </div>
        </div>
        <div class="col-sm-3">
          <small class="text-muted">{{ __('Characters') }}</small>
        </div>
      </div>
    </div>
  </div>

  {{-- ── Spell Check Settings ──────────────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-header bg-warning text-dark">
      <h5 class="mb-0"><i class="fas fa-spell-check me-2"></i>{{ __('Spell Check') }}</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="spellcheck_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="spellcheck_enabled" id="spellcheck_enabled" value="1"
              {{ ($settings['spellcheck_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="spellcheck_enabled">{{ __('Enable Spell Check') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">{{ __('Check spelling in metadata fields') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Language') }}</label>
        <div class="col-sm-6">
          <select class="form-select" name="spellcheck_language">
            @foreach($spellcheckLanguages as $code => $label)
              <option value="{{ $code }}" {{ ($settings['spellcheck_language'] ?? 'en_ZA') === $code ? 'selected' : '' }}>
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Check Fields') }}</label>
        <div class="col-sm-9">
          @foreach($spellcheckFields as $field => $label)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="spellcheck_field_{{ $field }}"
                id="spellcheck_{{ $field }}" value="1"
                {{ in_array($field, $selectedSpellcheckFields) ? 'checked' : '' }}>
              <label class="form-check-label" for="spellcheck_{{ $field }}">{{ $label }}</label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- ── Machine Translation Settings ─────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0"><i class="fas fa-language me-2"></i>{{ __('Machine Translation (OPUS-MT)') }}</h5>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="translation_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="translation_enabled" id="translation_enabled" value="1"
              {{ ($settings['translation_enabled'] ?? '1') === '1' ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="translation_enabled">{{ __('Enable Translation') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">{{ __('Translate record metadata using OPUS-MT (offline, on-premise)') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('OPUS-MT Endpoint') }}</label>
        <div class="col-sm-6">
          <input type="text" class="form-control" name="mt_endpoint"
            value="{{ $settings['mt_endpoint'] ?? 'http://127.0.0.1:5100/translate' }}">
          <small class="text-muted">{{ __('OPUS-MT translation server URL') }}</small>
        </div>
        <div class="col-sm-3">
          <button type="button" class="btn btn-outline-info" id="btn-test-translation">
            <i class="fas fa-plug me-1"></i>{{ __('Test') }}
          </button>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Default Source Language') }}</label>
        <div class="col-sm-4">
          <select class="form-select" name="translation_source_lang">
            @foreach($translationLanguages as $code => $langData)
              <option value="{{ $code }}" {{ ($settings['translation_source_lang'] ?? 'en') === $code ? 'selected' : '' }}>
                {{ $langData['name'] }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Default Target Language') }}</label>
        <div class="col-sm-4">
          <select class="form-select" name="translation_target_lang" id="translation_target_lang">
            @foreach($translationLanguages as $code => $langData)
              <option value="{{ $code }}" data-culture="{{ $langData['culture'] }}"
                {{ ($settings['translation_target_lang'] ?? 'af') === $code ? 'selected' : '' }}>
                {{ $langData['name'] }} ({{ $langData['culture'] }})
              </option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="translation_save_culture" value="0">
            <input class="form-check-input" type="checkbox" name="translation_save_culture" id="translation_save_culture" value="1"
              {{ ($settings['translation_save_culture'] ?? '1') === '1' ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="translation_save_culture">{{ __('Save with culture code') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">
            When enabled, translations will be saved in the <code>information_object_i18n</code> table
            with the language culture code (e.g., <code>af</code>, <code>zu</code>, <code>xh</code>).
            This allows multi-language support where users can switch between languages.
          </small>
        </div>
      </div>

      <hr class="my-4">

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Institution Sector') }}</label>
        <div class="col-sm-4">
          <select class="form-select" name="translation_sector" id="translation_sector">
            <option value="archives" {{ ($settings['translation_sector'] ?? 'archives') === 'archives' ? 'selected' : '' }}>
              Archives (ISAD(G))
            </option>
            <option value="library" {{ ($settings['translation_sector'] ?? 'archives') === 'library' ? 'selected' : '' }}>
              Library (MARC/Dublin Core)
            </option>
            <option value="museum" {{ ($settings['translation_sector'] ?? 'archives') === 'museum' ? 'selected' : '' }}>
              Museum (SPECTRUM)
            </option>
            <option value="gallery" {{ ($settings['translation_sector'] ?? 'archives') === 'gallery' ? 'selected' : '' }}>
              Gallery (Art Collection)
            </option>
            <option value="dam" {{ ($settings['translation_sector'] ?? 'archives') === 'dam' ? 'selected' : '' }}>
              DAM (Digital Asset Management)
            </option>
          </select>
          <small class="text-muted">{{ __('Select your institution type to see relevant fields') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Fields to Translate') }}</label>
        <div class="col-sm-9">
          <p class="text-muted small mb-2">Select source fields and choose where to save the translation in the target language.</p>

          @foreach($translationFieldsBySector as $sector => $sectorFields)
            <div id="sector-fields-{{ $sector }}" class="sector-fields" @if($sector !== ($settings['translation_sector'] ?? 'archives')) style="display:none;" @endif>
              <table class="table table-sm table-hover">
                <thead class="table-light">
                  <tr>
                    <th style="width:40px;"></th>
                    <th>{{ __('Source Field') }}</th>
                    <th><i class="fas fa-arrow-right text-muted"></i> Save To (Target)</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($sectorFields as $field => $label)
                    <tr>
                      <td>
                        <input class="form-check-input translate-field" type="checkbox" name="translate_field_{{ $field }}"
                          id="translate_{{ $sector }}_{{ $field }}" value="1"
                          {{ in_array($field, $selectedTranslationFields) ? 'checked' : '' }}>
                      </td>
                      <td><label class="form-check-label mb-0" for="translate_{{ $sector }}_{{ $field }}">{{ $label }}</label></td>
                      <td>
                        <select class="form-select form-select-sm" name="translate_target_{{ $field }}">
                          @foreach($targetFields as $targetField => $targetLabel)
                            <option value="{{ $targetField }}" {{ ($fieldMappings[$field] ?? $field) === $targetField ? 'selected' : '' }}>
                              {{ $targetLabel }}
                            </option>
                          @endforeach
                        </select>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endforeach

          <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-select-all-fields">{{ __('Select All') }}</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-deselect-all-fields">{{ __('Deselect All') }}</button>
            <button type="button" class="btn btn-sm btn-outline-info" id="btn-reset-target-fields">{{ __('Reset Targets to Same Field') }}</button>
          </div>
        </div>
      </div>

      <hr class="my-4">

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Translation Mode') }}</label>
        <div class="col-sm-9">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="translation_mode" id="mode_review" value="review"
              {{ ($settings['translation_mode'] ?? 'review') === 'review' ? 'checked' : '' }}>
            <label class="form-check-label" for="mode_review">
              <strong>{{ __('Review First') }}</strong> - Save as draft for review before applying
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="translation_mode" id="mode_auto" value="auto"
              {{ ($settings['translation_mode'] ?? 'review') === 'auto' ? 'checked' : '' }}>
            <label class="form-check-label" for="mode_auto">
              <strong>{{ __('Auto Apply') }}</strong> - Immediately save translations to target language
            </label>
          </div>
        </div>
      </div>

      <div class="row mb-3">
        <div class="col-sm-3">
          <div class="form-check form-switch">
            <input type="hidden" name="translation_overwrite" value="0">
            <input class="form-check-input" type="checkbox" name="translation_overwrite" id="translation_overwrite" value="1"
              {{ ($settings['translation_overwrite'] ?? '0') === '1' ? 'checked' : '' }}>
            <label class="form-check-label" for="translation_overwrite">{{ __('Overwrite existing') }}</label>
          </div>
        </div>
        <div class="col-sm-9">
          <small class="text-muted">{{ __('If target language field already has text, overwrite it with translation') }}</small>
        </div>
      </div>

      <div class="row mb-3">
        <label class="col-sm-3 col-form-label">{{ __('Timeout (seconds)') }}</label>
        <div class="col-sm-3">
          <input type="number" class="form-control" name="mt_timeout" min="10" max="120"
            value="{{ $settings['mt_timeout'] ?? '30' }}">
        </div>
      </div>

      <div class="alert alert-info mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <strong>{{ __('Supported Languages:') }}</strong> All 11 South African official languages (Afrikaans, Zulu, Xhosa, Sotho, Tswana, Swati, Venda, Tsonga, Ndebele),
        plus Swahili, Yoruba, Igbo, Hausa, Amharic, Dutch, French, German, Spanish, Portuguese, Arabic, and more.
        <br><small>{{ __('OPUS-MT runs locally - no data leaves your server. Models download automatically on first use (~300-500MB each).') }}</small>
      </div>
    </div>
  </div>

  {{-- ── Qdrant Vector Search Settings ─────────────────────────────── --}}
  <div class="card mb-4">
    <div class="card-header bg-dark text-white">
      <h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>{{ __('Qdrant Vector Search (Discovery Plugin)') }}</h5>
    </div>
    <div class="card-body">
      <div class="mb-4">
        <h6 class="fw-bold mb-3"><i class="fas fa-heartbeat me-1"></i>{{ __('Service Status') }}</h6>
        @if($qdrantStatus['service'])
          <div class="alert alert-success py-2">
            <i class="fas fa-check-circle me-1"></i>
            <strong>{{ __('Qdrant is running') }}</strong>
            @if($qdrantStatus['version'])
              &mdash; v{{ $qdrantStatus['version'] }}
            @endif
          </div>
          @if(!empty($qdrantStatus['collections']))
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr><th>{{ __('Collection') }}</th><th>{{ __('Vectors') }}</th><th>{{ __('Status') }}</th></tr>
              </thead>
              <tbody>
                @foreach($qdrantStatus['collections'] as $col)
                  <tr>
                    <td><code>{{ $col['name'] }}</code></td>
                    <td>{{ number_format($col['points']) }}</td>
                    <td>
                      @if($col['status'] === 'green')
                        <span class="badge bg-success">{{ $col['status'] }}</span>
                      @else
                        <span class="badge bg-warning">{{ $col['status'] }}</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <div class="text-muted">No collections found.</div>
          @endif
        @else
          <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <strong>{{ __('Qdrant is not running.') }}</strong>
            Start it with: <code>docker start qdrant</code>
          </div>
        @endif
      </div>
      <hr>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">{{ __('Qdrant URL') }}</label>
          <input type="text" class="form-control" name="qdrant_url" value="{{ $settings['qdrant_url'] ?? 'http://localhost:6333' }}">
          <div class="form-text">Qdrant REST endpoint</div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">{{ __('Collection Name') }}</label>
          <input type="text" class="form-control" name="qdrant_collection" value="{{ $settings['qdrant_collection'] ?? '' }}" placeholder="{{ __('Auto-detected from database name') }}">
          <div class="form-text">Leave empty for auto-detection (dbname_records)</div>
        </div>
      </div>
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label fw-bold">{{ __('Embedding Model') }}</label>
          <select class="form-select" name="qdrant_model">
            <option value="all-MiniLM-L6-v2" {{ ($settings['qdrant_model'] ?? '') === 'all-MiniLM-L6-v2' ? 'selected' : '' }}>all-MiniLM-L6-v2 (384d, fast)</option>
            <option value="all-mpnet-base-v2" {{ ($settings['qdrant_model'] ?? '') === 'all-mpnet-base-v2' ? 'selected' : '' }}>all-mpnet-base-v2 (768d, higher quality)</option>
            <option value="multi-qa-MiniLM-L6-cos-v1" {{ ($settings['qdrant_model'] ?? '') === 'multi-qa-MiniLM-L6-cos-v1' ? 'selected' : '' }}>multi-qa-MiniLM-L6-cos-v1 (384d, QA optimized)</option>
          </select>
          <div class="form-text">Sentence-transformers model for embeddings</div>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-bold">{{ __('Minimum Similarity Score') }}</label>
          <input type="number" class="form-control" name="qdrant_min_score" value="{{ $settings['qdrant_min_score'] ?? '0.25' }}" min="0" max="1" step="0.05">
          <div class="form-text">Cosine similarity threshold (0-1). Lower = more results, higher = stricter match.</div>
        </div>
      </div>
      <div class="alert alert-light border mt-3">
        <i class="fas fa-info-circle me-1 text-primary"></i>
        <strong>{{ __('Indexing:') }}</strong> Run the Qdrant indexer from the CLI or cron. See
        @if(\Route::has('settings.cron-jobs'))
          <a href="{{ route('settings.cron-jobs') }}">Cron Jobs</a>
        @else
          Cron Jobs
        @endif
        for the scheduled command.
      </div>
    </div>
  </div>

  {{-- ── Save Button ───────────────────────────────────────────────── --}}
  <div class="d-flex justify-content-end gap-2 mb-4">
    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
      <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
    </a>
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fas fa-save me-1"></i>{{ __('Save Settings') }}
    </button>
  </div>

</form>

{{-- Test Connection Result --}}
<div id="connectionResult" class="alert d-none mb-4"></div>

<script>
// Heratio runs a strict CSP (script-src 'self' + nonce, no 'unsafe-inline').
// Inline onclick="..." attributes are silently blocked by the browser, so all
// handlers below are bound via addEventListener inside this nonced <script>.

document.addEventListener('DOMContentLoaded', function() {
    updateFieldsForSector();

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            document.querySelectorAll('.sector-fields').forEach(function(div) {
                if (div.style.display === 'none') {
                    div.querySelectorAll('input, select').forEach(function(input) {
                        input.disabled = true;
                    });
                }
            });
        });
    }

    const sectorSelect = document.getElementById('translation_sector');
    if (sectorSelect) sectorSelect.addEventListener('change', updateFieldsForSector);

    bind('btn-select-all-fields',    'click',  selectAllFields);
    bind('btn-deselect-all-fields',  'click',  deselectAllFields);
    bind('btn-reset-target-fields',  'click',  resetTargetFields);
    bind('btn-toggle-api-key',       'click',  togglePassword);
    bind('btn-test-connection',      'click',  testConnection);
    bind('btn-test-translation',     'click',  testTranslation);
});

function bind(id, event, handler) {
    const el = document.getElementById(id);
    if (el) el.addEventListener(event, handler);
}

function updateFieldsForSector() {
    const sector = document.getElementById('translation_sector').value;
    document.querySelectorAll('.sector-fields').forEach(function(el) {
        el.style.display = 'none';
        el.querySelectorAll('input, select').forEach(function(input) { input.disabled = false; });
    });
    const sectorDiv = document.getElementById('sector-fields-' + sector);
    if (sectorDiv) sectorDiv.style.display = 'block';
}

function selectAllFields() {
    const sector = document.getElementById('translation_sector').value;
    const sectorDiv = document.getElementById('sector-fields-' + sector);
    if (sectorDiv) sectorDiv.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.checked = true; });
}

function deselectAllFields() {
    const sector = document.getElementById('translation_sector').value;
    const sectorDiv = document.getElementById('sector-fields-' + sector);
    if (sectorDiv) sectorDiv.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
}

function resetTargetFields() {
    const sector = document.getElementById('translation_sector').value;
    const sectorDiv = document.getElementById('sector-fields-' + sector);
    if (sectorDiv) {
        sectorDiv.querySelectorAll('select').forEach(function(select) {
            select.value = select.name.replace('translate_target_', '');
        });
    }
}

function togglePassword(e) {
    const field = document.getElementById('api_key');
    const btn = e.currentTarget;
    if (field.type === 'password') {
        field.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> {{ __('Hide') }}';
    } else {
        field.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i> {{ __('Show') }}';
    }
}

function showResult(html, level) {
    const resultDiv = document.getElementById('connectionResult');
    resultDiv.className = 'alert alert-' + level;
    resultDiv.innerHTML = html;
    resultDiv.classList.remove('d-none');
    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function testConnection() {
    const url    = document.querySelector('input[name="api_url"]').value;
    const apiKey = document.querySelector('input[name="api_key"]').value;
    showResult('<i class="fas fa-spinner fa-spin me-2"></i>Testing connection...', 'info');

    // Route via same-origin server proxy so CORS / mixed-content / browser-
    // localhost mismatches can't kill the test. The PHP side uses the same
    // HTTP client that the live NER / summarisation services use.
    fetch('{{ route('settings.ai-services.test') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({ url: url, api_key: apiKey })
    })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, body: d }; }); })
        .then(function(res) {
            if (res.ok && res.body.success) {
                const d = res.body.data || {};
                showResult(
                    '<i class="fas fa-check-circle me-2"></i><strong>Connection successful!</strong><br>' +
                    'NER Model: ' + (d.ner_model || 'N/A') + '<br>' +
                    'Summarizer: ' + (d.summarizer_model || 'N/A'),
                    'success'
                );
            } else {
                showResult(
                    '<i class="fas fa-exclamation-triangle me-2"></i><strong>Connection failed!</strong><br>' +
                    'Error: ' + (res.body.error || 'unknown') + '<br>' +
                    'URL: ' + url,
                    'danger'
                );
            }
        })
        .catch(function(error) {
            showResult(
                '<i class="fas fa-exclamation-triangle me-2"></i><strong>Connection failed!</strong><br>' +
                'Error: ' + error.message,
                'danger'
            );
        });
}

function testTranslation() {
    const endpoint = document.querySelector('input[name="mt_endpoint"]').value;
    const apiKey   = document.querySelector('input[name="api_key"]').value;
    showResult('<i class="fas fa-spinner fa-spin me-2"></i>Testing OPUS-MT connection...', 'info');

    fetch('{{ route('settings.ai-services.test-mt') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        body: JSON.stringify({ endpoint: endpoint, api_key: apiKey })
    })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, body: d }; }); })
        .then(function(res) {
            if (res.ok && res.body.success) {
                const d = res.body.data || {};
                showResult(
                    '<i class="fas fa-check-circle me-2"></i><strong>OPUS-MT Connection successful!</strong><br>' +
                    'Status: ' + (d.status || 'ok') + '<br>' +
                    'Translator: ' + (d.translator || 'N/A') + '<br>' +
                    '<strong>Test:</strong> "Hello, how are you?" &rarr; "' + (d.translated || 'N/A') + '"',
                    'success'
                );
            } else {
                showResult(
                    '<i class="fas fa-exclamation-triangle me-2"></i><strong>OPUS-MT Connection failed!</strong><br>' +
                    'Error: ' + (res.body.error || 'unknown') + '<br>' +
                    'Endpoint: ' + endpoint,
                    'danger'
                );
            }
        })
        .catch(function(error) {
            showResult(
                '<i class="fas fa-exclamation-triangle me-2"></i><strong>OPUS-MT Connection failed!</strong><br>' +
                'Error: ' + error.message,
                'danger'
            );
        });
}
</script>
@endsection
