@extends('theme::layouts.1col')

@section('title', 'AI Configuration')
@section('body-class', 'admin ai-config')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-cog"></i> AI Configuration</h1>
    <a href="{{ route('admin.ai.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>
  <p class="text-muted mb-4">Configure LLM providers, API keys, models, and AI feature settings</p>

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  {{-- LLM Provider Configurations --}}
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <strong><i class="fas fa-server"></i> LLM Provider Configurations</strong>
      <button type="button" class="btn atom-btn-outline-light btn-sm" data-bs-toggle="collapse" data-bs-target="#newConfigForm">
        <i class="fas fa-plus"></i> Add Provider
      </button>
    </div>
    <div class="card-body">

      {{-- New Config Form (collapsed) --}}
      <div class="collapse mb-4" id="newConfigForm">
        <div class="card card-body bg-light">
          <h6 class="mb-3">Add New LLM Configuration</h6>
          <form method="POST" action="{{ route('admin.ai.config') }}">
            @csrf
            <input type="hidden" name="_action" value="create_config">

            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Provider <span class="badge bg-danger ms-1">Required</span></label>
                <select name="provider" class="form-select" required id="newProvider" onchange="updateNewConfigDefaults()">
                  <option value="ollama">Ollama (Local)</option>
                  <option value="openai">OpenAI</option>
                  <option value="anthropic">Anthropic</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Name <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="name" class="form-control" required placeholder="e.g., Local Ollama" id="newConfigName">
              </div>
              <div class="col-md-4">
                <label class="form-label">Model <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" name="model" class="form-control" required id="newConfigModel" placeholder="e.g., llama3.1:8b">
              </div>
              <div class="col-md-6">
                <label class="form-label">Endpoint URL <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="url" name="endpoint_url" class="form-control" id="newConfigEndpoint" placeholder="http://localhost:11434">
              </div>
              <div class="col-md-6">
                <label class="form-label">API Key <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="password" name="api_key" class="form-control" placeholder="Leave blank for Ollama" id="newConfigApiKey">
              </div>
              <div class="col-md-3">
                <label class="form-label">Max Tokens <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="max_tokens" class="form-control" value="2000" min="100" max="100000">
              </div>
              <div class="col-md-3">
                <label class="form-label">Temperature <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="range" name="temperature" class="form-range mt-2" min="0" max="2" step="0.05" value="0.70" id="newTempSlider" oninput="document.getElementById('newTempVal').textContent=this.value">
                <small class="text-muted">Value: <span id="newTempVal">0.70</span></small>
              </div>
              <div class="col-md-3">
                <label class="form-label">Timeout (seconds) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="timeout_seconds" class="form-control" value="120" min="10" max="600">
              </div>
              <div class="col-md-3">
                <label class="form-label">Options <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-check mt-2">
                  <input type="hidden" name="is_active" value="0">
                  <input type="checkbox" name="is_active" value="1" class="form-check-input" id="newIsActive" checked>
                  <label class="form-check-label" for="newIsActive">Active <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
                <div class="form-check">
                  <input type="hidden" name="is_default" value="0">
                  <input type="checkbox" name="is_default" value="1" class="form-check-input" id="newIsDefault">
                  <label class="form-check-label" for="newIsDefault">Default <span class="badge bg-secondary ms-1">Optional</span></label>
                </div>
              </div>
            </div>

            <div class="mt-3">
              <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save"></i> Create Configuration</button>
            </div>
          </form>
        </div>
      </div>

      {{-- Existing Configs --}}
      @forelse($configs as $cfg)
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center py-2" style="background:var(--ahg-primary);color:#fff">
          <div>
            <strong>{{ $cfg->name }}</strong>
            <span class="badge bg-secondary ms-2">{{ ucfirst($cfg->provider) }}</span>
            @if($cfg->is_active)
              <span class="badge bg-success ms-1">Active</span>
            @else
              <span class="badge bg-warning ms-1">Inactive</span>
            @endif
            @if($cfg->is_default)
              <span class="badge bg-primary ms-1">Default</span>
            @endif
          </div>
          <div>
            <button type="button" class="btn btn-sm atom-btn-white" data-bs-toggle="collapse" data-bs-target="#editConfig{{ $cfg->id }}">
              <i class="fas fa-edit"></i> Edit
            </button>
            <button type="button" class="btn btn-sm atom-btn-white" onclick="testProvider({{ $cfg->id }}, this)">
              <i class="fas fa-plug"></i> Test
            </button>
          </div>
        </div>
        <div class="collapse" id="editConfig{{ $cfg->id }}">
          <div class="card-body">
            <form method="POST" action="{{ route('admin.ai.config') }}">
              @csrf
              <input type="hidden" name="_action" value="update_config">
              <input type="hidden" name="config_id" value="{{ $cfg->id }}">

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Provider <span class="badge bg-danger ms-1">Required</span></label>
                  <input type="text" class="form-control" value="{{ ucfirst($cfg->provider) }}" disabled>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Name <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="name" class="form-control" value="{{ $cfg->name }}" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Model <span class="badge bg-danger ms-1">Required</span></label>
                  <input type="text" name="model" class="form-control" value="{{ $cfg->model }}" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Endpoint URL <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="url" name="endpoint_url" class="form-control" value="{{ $cfg->endpoint_url }}">
                </div>
                <div class="col-md-6">
                  <label class="form-label">API Key <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="password" name="api_key" class="form-control" placeholder="{{ $cfg->api_key_encrypted ? '(encrypted - leave blank to keep)' : 'Enter API key' }}">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Max Tokens <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" name="max_tokens" class="form-control" value="{{ $cfg->max_tokens }}" min="100" max="100000">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Temperature <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="range" name="temperature" class="form-range mt-2" min="0" max="2" step="0.05" value="{{ $cfg->temperature }}"
                         oninput="this.nextElementSibling.querySelector('span').textContent=this.value">
                  <small class="text-muted">Value: <span>{{ $cfg->temperature }}</span></small>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Timeout (seconds) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" name="timeout_seconds" class="form-control" value="{{ $cfg->timeout_seconds }}" min="10" max="600">
                </div>
                <div class="col-md-3">
                  <label class="form-label">Options <span class="badge bg-secondary ms-1">Optional</span></label>
                  <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $cfg->is_active ? 'checked' : '' }}>
                    <label class="form-check-label">Active <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" class="form-check-input" {{ $cfg->is_default ? 'checked' : '' }}>
                    <label class="form-check-label">Default <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
              </div>

              <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save"></i> Update</button>
                <button type="submit" name="_action" value="delete_config" class="btn atom-btn-outline-danger"
                        onclick="return confirm('Delete this configuration?')">
                  <i class="fas fa-trash"></i> Delete
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
      @empty
      <div class="text-muted text-center py-3">No LLM configurations found. Add one above.</div>
      @endforelse
    </div>
  </div>

  {{-- Feature Settings --}}
  <form method="POST" action="{{ route('admin.ai.config') }}">
    @csrf
    <input type="hidden" name="_action" value="save_settings">

    {{-- General AI Settings --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong><i class="fas fa-sliders-h"></i> General AI Settings</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">AI API URL <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="url" name="settings_general[api_url]" class="form-control"
                   value="{{ $generalSettings->get('api_url')->setting_value ?? '' }}" placeholder="http://192.168.0.112:5004/ai/v1">
          </div>
          <div class="col-md-3">
            <label class="form-label">API Key <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="password" name="settings_general[api_key]" class="form-control"
                   value="{{ $generalSettings->get('api_key')->setting_value ?? '' }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">API Timeout (seconds) <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings_general[api_timeout]" class="form-control"
                   value="{{ $generalSettings->get('api_timeout')->setting_value ?? '60' }}" min="10" max="600">
          </div>
          <div class="col-md-4">
            <label class="form-label">Processing Backend <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_general[backend]" class="form-select">
              <option value="local" {{ ($generalSettings->get('backend')->setting_value ?? '') === 'local' ? 'selected' : '' }}>Local</option>
              <option value="api" {{ ($generalSettings->get('backend')->setting_value ?? '') === 'api' ? 'selected' : '' }}>API</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Processing Mode <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_general[processing_mode]" class="form-select">
              <option value="sync" {{ ($generalSettings->get('processing_mode')->setting_value ?? '') === 'sync' ? 'selected' : '' }}>Synchronous</option>
              <option value="job" {{ ($generalSettings->get('processing_mode')->setting_value ?? '') === 'job' ? 'selected' : '' }}>Job Queue</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Require Review <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_general[require_review]" class="form-select">
              <option value="1" {{ ($generalSettings->get('require_review')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($generalSettings->get('require_review')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- NER Settings --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong><i class="fas fa-diagram-project"></i> NER Settings</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">NER Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_ner[enabled]" class="form-select">
              <option value="1" {{ ($nerSettings->get('enabled')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($nerSettings->get('enabled')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Auto-link Exact Matches <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_ner[auto_link_exact]" class="form-select">
              <option value="0" {{ ($nerSettings->get('auto_link_exact')->setting_value ?? '0') === '0' ? 'selected' : '' }}>No</option>
              <option value="1" {{ ($nerSettings->get('auto_link_exact')->setting_value ?? '0') === '1' ? 'selected' : '' }}>Yes</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Confidence Threshold <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings_ner[confidence_threshold]" class="form-control"
                   value="{{ $nerSettings->get('confidence_threshold')->setting_value ?? '0.85' }}" min="0" max="1" step="0.01">
          </div>
          <div class="col-md-3">
            <label class="form-label">Extract from PDF <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_ner[extract_from_pdf]" class="form-select">
              <option value="1" {{ ($nerSettings->get('extract_from_pdf')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($nerSettings->get('extract_from_pdf')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Summarize Settings --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong><i class="fas fa-compress-alt"></i> Summarization Settings</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_summarize[enabled]" class="form-select">
              <option value="1" {{ ($summarizeSettings->get('enabled')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($summarizeSettings->get('enabled')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Length (words) <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings_summarize[max_length]" class="form-control"
                   value="{{ $summarizeSettings->get('max_length')->setting_value ?? '1000' }}" min="50" max="10000">
          </div>
          <div class="col-md-3">
            <label class="form-label">Min Length (words) <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings_summarize[min_length]" class="form-control"
                   value="{{ $summarizeSettings->get('min_length')->setting_value ?? '100' }}" min="10" max="5000">
          </div>
          <div class="col-md-3">
            <label class="form-label">Target Field <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings_summarize[target_field]" class="form-control"
                   value="{{ $summarizeSettings->get('target_field')->setting_value ?? 'scope_and_content' }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Translation Settings --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong><i class="fas fa-language"></i> Translation Settings</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_translate[enabled]" class="form-select">
              <option value="1" {{ ($translateSettings->get('enabled')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($translateSettings->get('enabled')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Engine <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_translate[engine]" class="form-select">
              <option value="argos" {{ ($translateSettings->get('engine')->setting_value ?? 'argos') === 'argos' ? 'selected' : '' }}>Argos</option>
              <option value="llm" {{ ($translateSettings->get('engine')->setting_value ?? '') === 'llm' ? 'selected' : '' }}>LLM</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Source Language <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings_translate[translation_source_lang]" class="form-control"
                   value="{{ $translateSettings->get('translation_source_lang')->setting_value ?? 'en' }}" maxlength="5">
          </div>
          <div class="col-md-3">
            <label class="form-label">Target Language <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings_translate[translation_target_lang]" class="form-control"
                   value="{{ $translateSettings->get('translation_target_lang')->setting_value ?? 'af' }}" maxlength="5">
          </div>
        </div>
      </div>
    </div>

    {{-- Spellcheck Settings --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong><i class="fas fa-spell-check"></i> Spellcheck Settings</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_spellcheck[enabled]" class="form-select">
              <option value="1" {{ ($spellcheckSettings->get('enabled')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($spellcheckSettings->get('enabled')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Language <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="text" name="settings_spellcheck[language]" class="form-control"
                   value="{{ $spellcheckSettings->get('language')->setting_value ?? 'en' }}" maxlength="10">
          </div>
          <div class="col-md-4">
            <label class="form-label">Ignore Capitalized Words <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_spellcheck[ignore_capitalized]" class="form-select">
              <option value="1" {{ ($spellcheckSettings->get('ignore_capitalized')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($spellcheckSettings->get('ignore_capitalized')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    {{-- Suggest Settings --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <strong><i class="fas fa-lightbulb"></i> Description Suggestion Settings</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Enabled <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_suggest[enabled]" class="form-select">
              <option value="1" {{ ($suggestSettings->get('enabled')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($suggestSettings->get('enabled')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Require Review <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="settings_suggest[require_review]" class="form-select">
              <option value="1" {{ ($suggestSettings->get('require_review')->setting_value ?? '1') === '1' ? 'selected' : '' }}>Yes</option>
              <option value="0" {{ ($suggestSettings->get('require_review')->setting_value ?? '1') === '0' ? 'selected' : '' }}>No</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Auto Expire (days) <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings_suggest[auto_expire_days]" class="form-control"
                   value="{{ $suggestSettings->get('auto_expire_days')->setting_value ?? '30' }}" min="0" max="365">
          </div>
          <div class="col-md-3">
            <label class="form-label">Max Pending Per Object <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="number" name="settings_suggest[max_pending_per_object]" class="form-control"
                   value="{{ $suggestSettings->get('max_pending_per_object')->setting_value ?? '3' }}" min="1" max="10">
          </div>
        </div>
      </div>
    </div>

    <div class="mb-4">
      <button type="submit" class="btn atom-btn-outline-success btn-lg">
        <i class="fas fa-save"></i> Save All Settings
      </button>
    </div>
  </form>

@endsection

@push('js')
<script>
function updateNewConfigDefaults() {
    const provider = document.getElementById('newProvider').value;
    const nameEl = document.getElementById('newConfigName');
    const modelEl = document.getElementById('newConfigModel');
    const endpointEl = document.getElementById('newConfigEndpoint');
    const apiKeyEl = document.getElementById('newConfigApiKey');

    switch (provider) {
        case 'ollama':
            nameEl.placeholder = 'e.g., Local Ollama';
            modelEl.value = 'llama3.1:8b';
            endpointEl.value = 'http://localhost:11434';
            apiKeyEl.placeholder = 'Not required for Ollama';
            break;
        case 'openai':
            nameEl.placeholder = 'e.g., OpenAI GPT-4o';
            modelEl.value = 'gpt-4o-mini';
            endpointEl.value = 'https://api.openai.com/v1';
            apiKeyEl.placeholder = 'sk-...';
            break;
        case 'anthropic':
            nameEl.placeholder = 'e.g., Anthropic Claude';
            modelEl.value = 'claude-3-haiku-20240307';
            endpointEl.value = 'https://api.anthropic.com/v1';
            apiKeyEl.placeholder = 'sk-ant-...';
            break;
    }
}

function testProvider(configId, btn) {
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('{{ route("admin.ai.test-connection") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ config_id: configId })
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;

        if (data.status === 'ok' || data.status === 'configured') {
            btn.classList.remove('atom-btn-white', 'atom-btn-outline-danger');
            btn.classList.add('atom-btn-outline-success');
            btn.innerHTML = '<i class="fas fa-check"></i> Connected';
            setTimeout(() => {
                btn.classList.remove('atom-btn-outline-success');
                btn.classList.add('atom-btn-white');
                btn.innerHTML = originalHtml;
            }, 3000);
        } else {
            btn.classList.remove('atom-btn-white');
            btn.classList.add('atom-btn-outline-danger');
            btn.innerHTML = '<i class="fas fa-times"></i> ' + (data.error || 'Failed');
            setTimeout(() => {
                btn.classList.remove('atom-btn-outline-danger');
                btn.classList.add('atom-btn-white');
                btn.innerHTML = originalHtml;
            }, 5000);
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert('Connection test failed: ' + err.message);
    });
}
</script>
@endpush
