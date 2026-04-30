{{--
  Data Ingest — AI processing defaults, output defaults, and service availability
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('ingest')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Data Ingest')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-file-import me-2"></i>Data Ingest</h1>
<p class="text-muted">Data ingest pipeline and processing options</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.ingest') }}">
    @csrf

    {{-- AI & Processing Defaults --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI &amp; Processing Defaults</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">These defaults are pre-selected when creating a new ingest session. Users can override per session.</p>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_virus_scan"
                     name="settings[ingest_virus_scan]" value="true"
                     {{ ($settings['ingest_virus_scan'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_virus_scan">
                <strong><i class="fas fa-shield-virus me-1 text-danger"></i>Virus Scan (ClamAV)</strong>
              </label>
            </div>
            <div class="form-text">Scan all uploaded files for malware before commit. Infected files are quarantined.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_ocr"
                     name="settings[ingest_ocr]" value="true"
                     {{ ($settings['ingest_ocr'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_ocr">
                <strong><i class="fas fa-file-alt me-1 text-primary"></i>OCR (Tesseract)</strong>
              </label>
            </div>
            <div class="form-text">Extract text from images and PDFs using Tesseract / pdftotext.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_ner"
                     name="settings[ingest_ner]" value="true"
                     {{ ($settings['ingest_ner'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_ner">
                <strong><i class="fas fa-tags me-1 text-success"></i>NER (Named Entity Recognition)</strong>
              </label>
            </div>
            <div class="form-text">Extract persons, organizations, places and dates from text fields. Creates access points automatically.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_summarize"
                     name="settings[ingest_summarize]" value="true"
                     {{ ($settings['ingest_summarize'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_summarize">
                <strong><i class="fas fa-compress-alt me-1 text-warning"></i>Auto-Summarize</strong>
              </label>
            </div>
            <div class="form-text">Generate scope and content summaries for records with extensive text.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_spellcheck"
                     name="settings[ingest_spellcheck]" value="true"
                     {{ ($settings['ingest_spellcheck'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_spellcheck">
                <strong><i class="fas fa-spell-check me-1 text-info"></i>Spell Check (aspell)</strong>
              </label>
            </div>
            <div class="form-text">Check spelling and grammar on title, scope and content, and archival history fields.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_format_id"
                     name="settings[ingest_format_id]" value="true"
                     {{ ($settings['ingest_format_id'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_format_id">
                <strong><i class="fas fa-fingerprint me-1 text-secondary"></i>Format Identification (Siegfried/PRONOM)</strong>
              </label>
            </div>
            <div class="form-text">Identify file formats using PRONOM registry via Siegfried. Records PUID, MIME type, and confidence.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_face_detect"
                     name="settings[ingest_face_detect]" value="true"
                     {{ ($settings['ingest_face_detect'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_face_detect">
                <strong><i class="fas fa-user-circle me-1 text-dark"></i>Face Detection</strong>
              </label>
            </div>
            <div class="form-text">Detect and match faces in images to authority records.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_translate"
                     name="settings[ingest_translate]" value="true"
                     {{ ($settings['ingest_translate'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_translate">
                <strong><i class="fas fa-language me-1 text-primary"></i>Auto-Translate (Argos)</strong>
              </label>
            </div>
            <div class="form-text">Translate metadata fields using offline Argos Translate engine.</div>
          </div>
        </div>

        {{-- Translation/Spellcheck language --}}
        <div class="row g-3 mt-2">
          <div class="col-md-4">
            <label for="ingest_translate_from" class="form-label">{{ __('Translate from') }}</label>
            <select class="form-select" id="ingest_translate_from" name="settings[ingest_translate_from]">
              @foreach (['en' => 'English', 'af' => 'Afrikaans', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Sotho', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'es' => 'Spanish', 'nl' => 'Dutch'] as $code => $name)
                <option value="{{ $code }}" {{ ($settings['ingest_translate_from'] ?? 'en') === $code ? 'selected' : '' }}>{{ $name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label for="ingest_translate_to" class="form-label">{{ __('Translate to') }}</label>
            <select class="form-select" id="ingest_translate_to" name="settings[ingest_translate_to]">
              @foreach (['af' => 'Afrikaans', 'en' => 'English', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Sotho', 'fr' => 'French', 'de' => 'German', 'pt' => 'Portuguese', 'es' => 'Spanish', 'nl' => 'Dutch'] as $code => $name)
                <option value="{{ $code }}" {{ ($settings['ingest_translate_to'] ?? 'af') === $code ? 'selected' : '' }}>{{ $name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label for="ingest_spellcheck_lang" class="form-label">{{ __('Spellcheck language') }}</label>
            <select class="form-select" id="ingest_spellcheck_lang" name="settings[ingest_spellcheck_lang]">
              @foreach (['en_ZA' => 'English (ZA)', 'en_US' => 'English (US)', 'en_GB' => 'English (UK)', 'af' => 'Afrikaans'] as $code => $name)
                <option value="{{ $code }}" {{ ($settings['ingest_spellcheck_lang'] ?? 'en_ZA') === $code ? 'selected' : '' }}>{{ $name }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Output Defaults --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Output Defaults</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_create_records"
                     name="settings[ingest_create_records]" value="true"
                     {{ ($settings['ingest_create_records'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_create_records">{{ __('Create records') }}</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_generate_sip"
                     name="settings[ingest_generate_sip]" value="true"
                     {{ ($settings['ingest_generate_sip'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_generate_sip">{{ __('Generate SIP package') }}</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_generate_aip"
                     name="settings[ingest_generate_aip]" value="true"
                     {{ ($settings['ingest_generate_aip'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_generate_aip">{{ __('Generate AIP package') }}</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_generate_dip"
                     name="settings[ingest_generate_dip]" value="true"
                     {{ ($settings['ingest_generate_dip'] ?? '') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_generate_dip">{{ __('Generate DIP package') }}</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_thumbnails"
                     name="settings[ingest_thumbnails]" value="true"
                     {{ ($settings['ingest_thumbnails'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_thumbnails">{{ __('Generate thumbnails') }}</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="ingest_reference"
                     name="settings[ingest_reference]" value="true"
                     {{ ($settings['ingest_reference'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="ingest_reference">{{ __('Generate reference images') }}</label>
            </div>
          </div>
        </div>

        <div class="row g-3 mt-2">
          <div class="col-md-6">
            <label for="ingest_sip_path" class="form-label">{{ __('Default SIP output path') }}</label>
            <input type="text" class="form-control" id="ingest_sip_path" name="settings[ingest_sip_path]"
                   value="{{ e($settings['ingest_sip_path'] ?? '') }}" placeholder="{{ __('/uploads/sip') }}">
          </div>
          <div class="col-md-6">
            <label for="ingest_aip_path" class="form-label">{{ __('Default AIP output path') }}</label>
            <input type="text" class="form-control" id="ingest_aip_path" name="settings[ingest_aip_path]"
                   value="{{ e($settings['ingest_aip_path'] ?? '') }}" placeholder="{{ __('/uploads/aip') }}">
          </div>
          <div class="col-md-6">
            <label for="ingest_dip_path" class="form-label">{{ __('Default DIP output path') }}</label>
            <input type="text" class="form-control" id="ingest_dip_path" name="settings[ingest_dip_path]"
                   value="{{ e($settings['ingest_dip_path'] ?? '') }}" placeholder="{{ __('/uploads/dip') }}">
          </div>
          <div class="col-md-6">
            <label for="ingest_default_sector" class="form-label">{{ __('Default sector') }}</label>
            <select class="form-select" id="ingest_default_sector" name="settings[ingest_default_sector]">
              @foreach (['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'] as $val => $label)
                <option value="{{ $val }}" {{ ($settings['ingest_default_sector'] ?? 'archive') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label for="ingest_default_standard" class="form-label">{{ __('Default descriptive standard') }}</label>
            <select class="form-select" id="ingest_default_standard" name="settings[ingest_default_standard]">
              @foreach (['isadg' => 'ISAD(G)', 'dc' => 'Dublin Core', 'rad' => 'RAD', 'dacs' => 'DACS', 'spectrum' => 'SPECTRUM', 'cco' => 'CCO'] as $val => $label)
                <option value="{{ $val }}" {{ ($settings['ingest_default_standard'] ?? 'isadg') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Service Availability --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Service Availability</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Processing options require the corresponding services to be installed and running.</p>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>{{ __('Service') }}</th><th>{{ __('Required Tool') }}</th><th>{{ __('Status') }}</th></tr>
            </thead>
            <tbody>
              @php
              $services = [
                  ['Virus Scan', 'ClamAV daemon', @shell_exec('clamdscan --version 2>/dev/null') ? true : false],
                  ['OCR', 'tesseract + pdftotext', @shell_exec('tesseract --version 2>&1') ? true : false],
                  ['NER', 'AI Services + Python API', class_exists(\AhgAiServices\AhgAiServicesServiceProvider::class)],
                  ['Summarize', 'AI Services + Python API', class_exists(\AhgAiServices\AhgAiServicesServiceProvider::class)],
                  ['Spell Check', 'aspell', @shell_exec('aspell --version 2>&1') ? true : false],
                  ['Translation', 'AI Services + Argos Translate', class_exists(\AhgAiServices\AhgAiServicesServiceProvider::class)],
                  ['Format ID', 'Siegfried (sf)', @shell_exec('sf -version 2>&1') ? true : false],
                  ['Face Detection', 'AI Services', class_exists(\AhgAiServices\AhgAiServicesServiceProvider::class)],
              ];
              @endphp
              @foreach ($services as $svc)
              <tr>
                <td><strong>{{ $svc[0] }}</strong></td>
                <td><code>{{ $svc[1] }}</code></td>
                <td>
                  @if ($svc[2])
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Available') }}</span>
                  @else
                    <span class="badge bg-secondary"><i class="fas fa-times me-1"></i>{{ __('Not installed') }}</span>
                  @endif
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
