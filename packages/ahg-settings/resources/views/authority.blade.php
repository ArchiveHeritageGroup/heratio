{{--
  Authority Records Settings — cloned from AtoM section.blade.php @case('authority')
  Copyright (C) 2026 Johan Pieterse — Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Authority Records')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-id-card me-2"></i>{{ __('Authority Records') }}</h1>
  <p class="text-muted small mb-0">External linking, completeness, NER pipeline, merge/dedup, occupations, functions</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="post" action="{{ route('settings.authority') }}">
    @csrf

    {{-- Card 1: External Authority Sources --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-globe me-2"></i>{{ __('External Authority Sources') }}</div>
      <div class="card-body">
        <p class="text-muted mb-3">Enable external authority file linking for reconciliation and enrichment.</p>
        <div class="row">
          @foreach([
            'authority_wikidata_enabled' => ['Wikidata', 'Enable Wikidata entity linking and reconciliation.', 'false'],
            'authority_viaf_enabled' => ['VIAF', 'Virtual International Authority File linking.', 'false'],
            'authority_ulan_enabled' => ['Getty ULAN', 'Union List of Artist Names linking.', 'false'],
          ] as $key => [$label, $help, $default])
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="{{ $key }}"
                     name="settings[{{ $key }}]" value="true"
                     {{ ($settings[$key] ?? $default) === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}"><strong>{{ $label }}</strong></label>
            </div>
            <div class="form-text">{{ $help }}</div>
          </div>
          @endforeach
        </div>
        <div class="row mt-3">
          @foreach([
            'authority_lcnaf_enabled' => ['LCNAF', 'Library of Congress Name Authority File.', 'false'],
            'authority_isni_enabled' => ['ISNI', 'International Standard Name Identifier linking.', 'false'],
            'authority_auto_verify_wikidata' => ['Auto-Verify Wikidata', 'Automatically mark Wikidata identifiers as verified when added via reconciliation.', 'false'],
          ] as $key => [$label, $help, $default])
          <div class="col-md-4">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="{{ $key }}"
                     name="settings[{{ $key }}]" value="true"
                     {{ ($settings[$key] ?? $default) === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}"><strong>{{ $label }}</strong></label>
            </div>
            <div class="form-text">{{ $help }}</div>
          </div>
          @endforeach
        </div>
      </div>
    </div>

    {{-- Card 2: Completeness & Quality --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-chart-bar me-2"></i>{{ __('Completeness &amp; Quality') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="authority_completeness_auto_recalc"
                     name="settings[authority_completeness_auto_recalc]" value="true"
                     {{ ($settings['authority_completeness_auto_recalc'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="authority_completeness_auto_recalc"><strong>{{ __('Auto-Recalculate Completeness') }}</strong></label>
            </div>
            <div class="form-text">Automatically recalculate completeness scores when the CLI scan runs.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="authority_hide_stubs_from_public"
                     name="settings[authority_hide_stubs_from_public]" value="true"
                     {{ ($settings['authority_hide_stubs_from_public'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="authority_hide_stubs_from_public"><strong>{{ __('Hide Stubs from Public') }}</strong></label>
            </div>
            <div class="form-text">Hide stub-level authority records from public browse and search results.</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 3: NER Pipeline --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-robot me-2"></i>{{ __('NER Pipeline') }}</div>
      <div class="card-body">
        <p class="text-muted mb-3">Configure how Named Entity Recognition creates authority record stubs.</p>
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="authority_ner_auto_stub_enabled"
                     name="settings[authority_ner_auto_stub_enabled]" value="true"
                     {{ ($settings['authority_ner_auto_stub_enabled'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="authority_ner_auto_stub_enabled"><strong>{{ __('Auto-Create Stubs') }}</strong></label>
            </div>
            <div class="form-text">Automatically create authority record stubs from NER entities above the confidence threshold.</div>
          </div>
          <div class="col-md-6">
            <label for="authority_ner_auto_stub_threshold" class="form-label"><strong>{{ __('Confidence Threshold') }}</strong></label>
            <input type="number" class="form-control" id="authority_ner_auto_stub_threshold"
                   name="settings[authority_ner_auto_stub_threshold]"
                   value="{{ $settings['authority_ner_auto_stub_threshold'] ?? '0.85' }}"
                   min="0" max="1" step="0.05">
            <div class="form-text">Minimum confidence score (0.0–1.0) for auto-creating stubs. Default: 0.85</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 4: Merge & Deduplication --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-code-branch me-2"></i>{{ __('Merge &amp; Deduplication') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="authority_merge_require_approval"
                     name="settings[authority_merge_require_approval]" value="true"
                     {{ ($settings['authority_merge_require_approval'] ?? 'false') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="authority_merge_require_approval"><strong>{{ __('Require Approval for Merge') }}</strong></label>
            </div>
            <div class="form-text">Require workflow approval before merging authority records. Requires ahgWorkflowPlugin.</div>
          </div>
          <div class="col-md-6">
            <label for="authority_dedup_threshold" class="form-label"><strong>{{ __('Dedup Similarity Threshold') }}</strong></label>
            <input type="number" class="form-control" id="authority_dedup_threshold"
                   name="settings[authority_dedup_threshold]"
                   value="{{ $settings['authority_dedup_threshold'] ?? '0.80' }}"
                   min="0" max="1" step="0.05">
            <div class="form-text">Minimum similarity score (0.0–1.0) for flagging potential duplicates. Default: 0.80</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 5: ISDF Functions --}}
    <div class="card mb-4">
      <div class="card-header"><i class="fas fa-project-diagram me-2"></i>{{ __('ISDF Functions') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="authority_function_linking_enabled"
                     name="settings[authority_function_linking_enabled]" value="true"
                     {{ ($settings['authority_function_linking_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="authority_function_linking_enabled"><strong>{{ __('Function Linking') }}</strong></label>
            </div>
            <div class="form-text">Enable structured actor-to-function linking (ISDF). Requires ahgFunctionManagePlugin.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-between">
      <a href="{{ route('settings.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save Settings') }}</button>
    </div>
  </form>
@endsection
