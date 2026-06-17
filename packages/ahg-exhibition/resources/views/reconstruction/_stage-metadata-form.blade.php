{{--
  heratio#1206 - optional AI "evidence layer" annotator: per-stage metadata form.

  A "Suggest with AI" button asks the gateway (via the annotate route) to propose
  structured provenance metadata (date estimate, evidence type, confidence, source
  credibility, rationale) from the stage's caption / body. The suggestion populates
  the editable fields; the curator reviews / edits and Saves. Saving posts the
  confirmed values to the metadata route, which persists them as JSON on the stage.

  Purely additive: a stage with no metadata simply shows empty fields. The AI call
  fails soft - on any gateway error the button shows a friendly inline message.

  Expects: $r (reconstruction row), $s (presented stage, $s->metadata may be null),
           $metadataOptions (fixed picklists from ReconstructionMetadataService).

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@php
  $meta = $s->metadata ?? [];
  $mDate = $meta['date_estimate'] ?? '';
  $mType = $meta['evidence_type'] ?? '';
  $mConf = $meta['confidence'] ?? '';
  $mCred = $meta['source_credibility'] ?? '';
  $mWhy  = $meta['rationale'] ?? '';
@endphp

<form method="POST"
      action="{{ route('exhibition-space.reconstructions.stages.metadata', ['id' => $r->id, 'stageId' => $s->id]) }}"
      class="recon-meta-form border-top pt-2 mt-2"
      data-annotate-url="{{ route('exhibition-space.reconstructions.stages.annotate', ['id' => $r->id, 'stageId' => $s->id]) }}">
  @csrf
  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <span class="small fw-semibold text-muted">
      <i class="fas fa-flask me-1"></i>{{ __('Evidence layer (optional)') }}
    </span>
    <button type="button" class="btn btn-sm btn-outline-info recon-meta-suggest">
      <i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Suggest with AI') }}
    </button>
    <span class="small text-danger recon-meta-msg d-none" role="alert"></span>
  </div>

  <div class="row g-2">
    <div class="col-md-3">
      <label class="form-label small mb-1">{{ __('Date estimate') }}</label>
      <input type="text" name="date_estimate" value="{{ $mDate }}" maxlength="120"
             class="form-control form-control-sm recon-meta-date" placeholder="{{ __('e.g. c. 1905') }}">
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">{{ __('Evidence type') }}</label>
      <select name="evidence_type" class="form-select form-select-sm recon-meta-type">
        <option value="">{{ __('-') }}</option>
        @foreach($metadataOptions['evidence_type'] as $opt)
          <option value="{{ $opt['code'] }}" @selected($mType === $opt['code'])>{{ __($opt['label']) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">{{ __('Confidence') }}</label>
      <select name="confidence" class="form-select form-select-sm recon-meta-conf">
        <option value="">{{ __('-') }}</option>
        @foreach($metadataOptions['confidence'] as $opt)
          <option value="{{ $opt['code'] }}" @selected($mConf === $opt['code'])>{{ __($opt['label']) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label small mb-1">{{ __('Source credibility') }}</label>
      <select name="source_credibility" class="form-select form-select-sm recon-meta-cred">
        <option value="">{{ __('-') }}</option>
        @foreach($metadataOptions['source_credibility'] as $opt)
          <option value="{{ $opt['code'] }}" @selected($mCred === $opt['code'])>{{ __($opt['label']) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-12">
      <label class="form-label small mb-1">{{ __('Rationale') }}</label>
      <input type="text" name="rationale" value="{{ $mWhy }}" maxlength="1000"
             class="form-control form-control-sm recon-meta-why"
             placeholder="{{ __('Why this assessment (the AI suggestion or your own note)') }}">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save evidence metadata') }}
      </button>
      <span class="form-text ms-2">{{ __('The AI only suggests - you review, edit and save. Clearing every field removes the metadata.') }}</span>
    </div>
  </div>
</form>
