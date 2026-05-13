@extends('theme::layouts.2col')
@section('title', 'Global Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Global settings') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.global') }}">
      @csrf
      <div class="accordion mb-3" id="globalAccordion">
        {{-- Version --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#version-collapse">{{ __('Version') }}</button></h2>
          <div id="version-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              @php
                $heratioVersion = (string) (json_decode((string) @file_get_contents(base_path('version.json')), true)['version'] ?? '');
              @endphp
              <div class="mb-3">
                <label class="form-label">{{ __('Heratio version') }}</label>
                <input type="text" class="form-control" value="{{ $heratioVersion }}" readonly>
                <small class="text-muted">{{ __('Read from version.json; bumped by bin/release on every deploy.') }}</small>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[check_for_updates]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[check_for_updates]" value="1" id="check_for_updates" {{ ($settings['check_for_updates'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="check_for_updates">Check for updates <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
            </div>
          </div>
        </div>

        {{-- Search and browse --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#search-collapse">{{ __('Search and browse') }}</button></h2>
          <div id="search-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Hits per page <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="number" name="settings[hits_per_page]" class="form-control" value="{{ $settings['hits_per_page'] ?? '10' }}" min="5" max="100">
              </div>
              <div class="mb-3">
                <label class="form-label">Sort browser (authenticated users) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[sort_browser_user]" class="form-select">
                  @foreach(['lastUpdated' => 'Most recent', 'alphabetic' => 'Alphabetic', 'identifier' => 'Identifier'] as $val => $label)
                    <option value="{{ $val }}" {{ ($settings['sort_browser_user'] ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Sort browser (anonymous users) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[sort_browser_anonymous]" class="form-select">
                  @foreach(['lastUpdated' => 'Most recent', 'alphabetic' => 'Alphabetic', 'identifier' => 'Identifier'] as $val => $label)
                    <option value="{{ $val }}" {{ ($settings['sort_browser_anonymous'] ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Default archival description browse view <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[default_archival_description_browse_view]" class="form-select">
                  @foreach(['table' => 'Table', 'card' => 'Card'] as $val => $label)
                    <option value="{{ $val }}" {{ ($settings['default_archival_description_browse_view'] ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Default repository browse view <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[default_repository_browse_view]" class="form-select">
                  @foreach(['table' => 'Table', 'card' => 'Card'] as $val => $label)
                    <option value="{{ $val }}" {{ ($settings['default_repository_browse_view'] ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[escape_queries]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[escape_queries]" value="1" id="escape_queries" {{ ($settings['escape_queries'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="escape_queries">Escape special characters in search queries <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
            </div>
          </div>
        </div>

        {{-- Presentation --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#presentation-collapse">{{ __('Presentation') }}</button></h2>
          <div id="presentation-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check mb-3">
                <input type="hidden" name="settings[show_tooltips]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[show_tooltips]" value="1" id="show_tooltips" {{ ($settings['show_tooltips'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="show_tooltips">Show tooltips <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[draft_notification_enabled]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[draft_notification_enabled]" value="1" id="draft_notification_enabled" {{ ($settings['draft_notification_enabled'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="draft_notification_enabled">Show draft record notification <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
            </div>
          </div>
        </div>

        {{-- Multi-repository --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#multirepo-collapse">{{ __('Multi-repository') }}</button></h2>
          <div id="multirepo-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check mb-3">
                <input type="hidden" name="settings[multi_repository]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[multi_repository]" value="1" id="multi_repository" {{ ($settings['multi_repository'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="multi_repository">Enable multi-repository <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[enable_institutional_scoping]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[enable_institutional_scoping]" value="1" id="enable_institutional_scoping" {{ ($settings['enable_institutional_scoping'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="enable_institutional_scoping">Enable institutional scoping <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
            </div>
          </div>
        </div>

        {{-- Permalinks --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#permalinks-collapse">{{ __('Permalinks') }}</button></h2>
          <div id="permalinks-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Slug basis (information object) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[slug_basis_informationobject]" class="form-select">
                  @foreach(['0' => 'Title', '1' => 'Identifier', '2' => 'Reference code'] as $val => $label)
                    <option value="{{ $val }}" {{ ($settings['slug_basis_informationobject'] ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[permissive_slug_creation]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[permissive_slug_creation]" value="1" id="permissive_slug_creation" {{ ($settings['permissive_slug_creation'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="permissive_slug_creation">Permissive slug creation (allow non-ASCII characters) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
            </div>
          </div>
        </div>

        {{-- System --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#system-collapse">{{ __('System') }}</button></h2>
          <div id="system-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="form-check mb-3">
                <input type="hidden" name="settings[audit_log_enabled]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[audit_log_enabled]" value="1" id="audit_log_enabled" {{ ($settings['audit_log_enabled'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="audit_log_enabled">Enable audit logging <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[generate_reports_as_pub_user]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[generate_reports_as_pub_user]" value="1" id="generate_reports_as_pub_user" {{ ($settings['generate_reports_as_pub_user'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="generate_reports_as_pub_user">Generate reports as public user <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
              <div class="form-check mb-3">
                <input type="hidden" name="settings[cache_xml_on_save]" value="0">
                <input class="form-check-input" type="checkbox" name="settings[cache_xml_on_save]" value="1" id="cache_xml_on_save" {{ ($settings['cache_xml_on_save'] ?? '') == '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="cache_xml_on_save">Cache XML on save <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              </div>
              <div class="mb-3">
                <label class="form-label">Default publication status <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <select name="settings[defaultPubStatus]" class="form-select">
                  <option value="159" {{ ($settings['defaultPubStatus'] ?? '') == '159' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                  <option value="160" {{ ($settings['defaultPubStatus'] ?? '') == '160' ? 'selected' : '' }}>{{ __('Published') }}</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {{-- Integrations --}}
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#integrations-collapse">{{ __('Integrations') }}</button></h2>
          <div id="integrations-collapse" class="accordion-collapse collapse">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Google Maps API key <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="settings[google_maps_api_key]" class="form-control" value="{{ $settings['google_maps_api_key'] ?? '' }}">
              </div>
              <div class="mb-3">
                <label class="form-label">SWORD deposit directory <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="settings[sword_deposit_dir]" class="form-control" value="{{ $settings['sword_deposit_dir'] ?? '' }}">
              </div>
            </div>
          </div>
        </div>

      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
@endsection
