@extends('theme::layouts.1col')

@section('title', 'AI Services')
@section('body-class', 'admin ai-services')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-brain"></i> AI Services</h1>
    <a href="{{ route('admin.ai.config') }}" class="btn atom-btn-white">
      <i class="fas fa-cog"></i> Configuration
    </a>
  </div>
  <p class="text-muted mb-4">LLM integration, NER, summarization, translation, and spellcheck services</p>

  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  {{-- Provider Status Cards --}}
  <div class="row mb-4">
    @forelse($providerHealth as $name => $health)
    <div class="col-lg-4 col-md-6 mb-3">
      <div class="card h-100 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <strong>{{ $name }}</strong>
          @if(($health['status'] ?? '') === 'ok')
            <span class="badge bg-success"><i class="fas fa-check-circle"></i> Online</span>
          @elseif(($health['status'] ?? '') === 'configured')
            <span class="badge bg-info"><i class="fas fa-key"></i> Configured</span>
          @else
            <span class="badge bg-danger"><i class="fas fa-times-circle"></i> Error</span>
          @endif
        </div>
        <div class="card-body">
          <dl class="row mb-0 small">
            <dt class="col-5">Provider</dt>
            <dd class="col-7">{{ ucfirst($health['provider'] ?? 'unknown') }}</dd>

            @if(!empty($health['default_model']))
            <dt class="col-5">Model</dt>
            <dd class="col-7"><code>{{ $health['default_model'] }}</code></dd>
            @endif

            @if(!empty($health['version']))
            <dt class="col-5">Version</dt>
            <dd class="col-7">{{ $health['version'] }}</dd>
            @endif

            @if(!empty($health['endpoint']))
            <dt class="col-5">Endpoint</dt>
            <dd class="col-7 text-truncate" title="{{ $health['endpoint'] }}">{{ $health['endpoint'] }}</dd>
            @endif

            @if(!empty($health['models']) && is_array($health['models']))
            <dt class="col-5">Models</dt>
            <dd class="col-7">{{ count($health['models']) }} available</dd>
            @endif

            @if(!empty($health['error']))
            <dt class="col-5 text-danger">Error</dt>
            <dd class="col-7 text-danger small">{{ $health['error'] }}</dd>
            @endif
          </dl>
        </div>
      </div>
    </div>
    @empty
    <div class="col-12">
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        No active LLM configurations found.
        <a href="{{ route('admin.ai.config') }}">Configure now</a>.
      </div>
    </div>
    @endforelse
  </div>

  {{-- AI API Status --}}
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <strong><i class="fas fa-server"></i> AI API</strong>
          @if(($apiHealth['status'] ?? '') === 'ok' || ($apiHealth['status'] ?? '') === 'healthy')
            <span class="badge bg-success float-end">Online</span>
          @else
            <span class="badge bg-warning float-end">Offline</span>
          @endif
        </div>
        <div class="card-body small">
          @php $apiUrl = $generalSettings->firstWhere('setting_key', 'api_url'); @endphp
          <p class="mb-1"><strong>URL:</strong> {{ $apiUrl->setting_value ?? 'Not configured' }}</p>
          @if(!empty($apiHealth['services']))
            @foreach($apiHealth['services'] as $svc => $status)
              <span class="badge {{ $status ? 'bg-success' : 'bg-secondary' }} me-1">{{ $svc }}</span>
            @endforeach
          @endif
        </div>
      </div>
    </div>

    {{-- NER Stats --}}
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong><i class="fas fa-diagram-project"></i> NER Entities</strong></div>
        <div class="card-body">
          <div class="row text-center small">
            <div class="col">
              <div class="fs-4 fw-bold text-primary">{{ $nerStats['total'] ?? 0 }}</div>
              <div class="text-muted">Total</div>
            </div>
            <div class="col">
              <div class="fs-4 fw-bold text-warning">{{ $nerStats['pending'] ?? 0 }}</div>
              <div class="text-muted">Pending</div>
            </div>
            <div class="col">
              <div class="fs-4 fw-bold text-success">{{ $nerStats['linked'] ?? 0 }}</div>
              <div class="text-muted">Linked</div>
            </div>
            <div class="col">
              <div class="fs-4 fw-bold text-danger">{{ $nerStats['rejected'] ?? 0 }}</div>
              <div class="text-muted">Rejected</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Usage Stats --}}
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><strong><i class="fas fa-chart-bar"></i> Usage</strong></div>
        <div class="card-body">
          <div class="row text-center small">
            <div class="col">
              <div class="fs-4 fw-bold text-primary">{{ $usageStats['config_count'] ?? 0 }}</div>
              <div class="text-muted">Configs</div>
            </div>
            <div class="col">
              <div class="fs-4 fw-bold text-success">{{ $usageStats['active_config_count'] ?? 0 }}</div>
              <div class="text-muted">Active</div>
            </div>
            @if($usageStats['suggestions'] ?? null)
            <div class="col">
              <div class="fs-4 fw-bold text-info">{{ $usageStats['suggestions']->total ?? 0 }}</div>
              <div class="text-muted">Suggestions</div>
            </div>
            <div class="col">
              <div class="fs-4 fw-bold text-muted">{{ number_format($usageStats['suggestions']->total_tokens ?? 0) }}</div>
              <div class="text-muted">Tokens</div>
            </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- HTR & Specialist Services --}}
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <strong><i class="fas fa-file-alt"></i> Vital Records HTR</strong>
        </div>
        <div class="card-body">
          <p class="small">Handwritten Text Recognition for SA vital records — death certificates, church registers, narrative documents. Extract, batch process, annotate, and fine-tune models.</p>
        </div>
        <div class="card-footer">
          <a href="{{ route('admin.ai.htr.dashboard') }}" class="btn atom-btn-white w-100"><i class="fas fa-arrow-right me-1"></i>Open HTR Dashboard</a>
        </div>
      </div>
    </div>
  </div>

  {{-- Quick Test Section --}}
  <div class="card shadow-sm mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <strong><i class="fas fa-flask"></i> Quick Test</strong>
    </div>
    <div class="card-body">
      <div class="mb-3">
        <label for="aiTestInput" class="form-label">Input Text <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea class="form-control" id="aiTestInput" rows="4" placeholder="Enter text to test AI services...">The National Archives of South Africa in Pretoria holds the records of Jan van Riebeeck from 1652. The Dutch East India Company (VOC) established a refreshment station at the Cape of Good Hope on 6 April 1652.</textarea>
      </div>

      <div class="mb-3">
        <label for="aiTargetLang" class="form-label">Target Language (for translation) <span class="badge bg-secondary ms-1">Optional</span></label>
        <select class="form-select form-select-sm w-auto d-inline-block" id="aiTargetLang">
          <option value="af">Afrikaans</option>
          <option value="fr">French</option>
          <option value="nl">Dutch</option>
          <option value="de">German</option>
          <option value="pt">Portuguese</option>
          <option value="es">Spanish</option>
          <option value="zu">Zulu</option>
          <option value="xh">Xhosa</option>
        </select>
      </div>

      <div class="btn-group flex-wrap" role="group">
        <button type="button" class="btn atom-btn-white" onclick="aiTest('summarize')">
          <i class="fas fa-compress-alt"></i> Summarize
        </button>
        <button type="button" class="btn atom-btn-white" onclick="aiTest('translate')">
          <i class="fas fa-language"></i> Translate
        </button>
        <button type="button" class="btn atom-btn-outline-success" onclick="aiTest('entities')">
          <i class="fas fa-diagram-project"></i> Extract Entities
        </button>
        <button type="button" class="btn atom-btn-white" onclick="aiTest('suggest')">
          <i class="fas fa-lightbulb"></i> Suggest Description
        </button>
        <button type="button" class="btn atom-btn-white" onclick="aiTest('spellcheck')">
          <i class="fas fa-spell-check"></i> Spellcheck
        </button>
      </div>

      {{-- Results Area --}}
      <div id="aiTestResults" class="mt-3" style="display:none;">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
            <strong id="aiResultLabel">Results</strong>
            <span id="aiResultTime" class="badge bg-secondary"></span>
          </div>
          <div class="card-body">
            <div id="aiResultSpinner" class="text-center py-3" style="display:none;">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Processing...</span>
              </div>
              <p class="text-muted mt-2">Processing request...</p>
            </div>
            <div id="aiResultContent"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- LLM Configurations Table --}}
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
      <strong><i class="fas fa-cogs"></i> LLM Configurations</strong>
      <a href="{{ route('admin.ai.config') }}" class="btn btn-sm atom-btn-white">
        <i class="fas fa-plus"></i> Manage
      </a>
    </div>
    <div class="card-body p-0">
      <table class="table table-bordered table-striped table-hover mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Provider</th>
            <th>Model</th>
            <th>Status</th>
            <th>Default</th>
            <th>Endpoint</th>
          </tr>
        </thead>
        <tbody>
          @forelse($configs as $cfg)
          <tr>
            <td>{{ $cfg->name }}</td>
            <td><span class="badge bg-secondary">{{ ucfirst($cfg->provider) }}</span></td>
            <td><code>{{ $cfg->model }}</code></td>
            <td>
              @if($cfg->is_active)
                <span class="badge bg-success">Active</span>
              @else
                <span class="badge bg-warning">Inactive</span>
              @endif
            </td>
            <td>
              @if($cfg->is_default)
                <span class="badge bg-primary">Default</span>
              @endif
            </td>
            <td class="small text-muted text-truncate" style="max-width:200px;" title="{{ $cfg->endpoint_url }}">
              {{ $cfg->endpoint_url }}
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-muted text-center py-3">No LLM configurations found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

@endsection

@push('js')
<script>
function aiTest(action) {
    const text = document.getElementById('aiTestInput').value.trim();
    if (!text) {
        alert('Please enter some text first.');
        return;
    }

    const resultsDiv = document.getElementById('aiTestResults');
    const spinner = document.getElementById('aiResultSpinner');
    const content = document.getElementById('aiResultContent');
    const label = document.getElementById('aiResultLabel');
    const timeEl = document.getElementById('aiResultTime');

    resultsDiv.style.display = 'block';
    spinner.style.display = 'block';
    content.innerHTML = '';
    timeEl.textContent = '';

    const labels = {
        summarize: 'Summary',
        translate: 'Translation',
        entities: 'Named Entities',
        suggest: 'Description Suggestion',
        spellcheck: 'Spellcheck'
    };
    label.textContent = labels[action] || 'Results';

    let url, body;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    switch (action) {
        case 'summarize':
            url = '{{ route("admin.ai.summarize") }}';
            body = { text: text, max_length: 200 };
            break;
        case 'translate':
            url = '{{ route("admin.ai.translate") }}';
            body = { text: text, target_lang: document.getElementById('aiTargetLang').value };
            break;
        case 'entities':
            url = '{{ route("admin.ai.entities") }}';
            body = { text: text };
            break;
        case 'suggest':
            url = '{{ route("admin.ai.suggest") }}';
            body = { title: text.substring(0, 200), context: text };
            break;
        case 'spellcheck':
            url = '{{ route("admin.ai.spellcheck") }}';
            body = { text: text };
            break;
    }

    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json'
        },
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        spinner.style.display = 'none';

        if (data.processing_time_ms) {
            timeEl.textContent = data.processing_time_ms + 'ms';
        }

        if (!data.success) {
            content.innerHTML = '<div class="alert alert-danger mb-0">' + (data.error || 'Request failed') + '</div>';
            return;
        }

        let html = '';
        switch (action) {
            case 'summarize':
                html = '<div class="alert alert-success mb-0">' + escapeHtml(data.summary) + '</div>';
                break;
            case 'translate':
                html = '<div class="alert alert-info mb-0">' + escapeHtml(data.translation)
                    + '<br><small class="text-muted">Source: ' + data.source + '</small></div>';
                break;
            case 'entities':
                html = renderEntities(data.entities, data.entity_count);
                break;
            case 'suggest':
                html = '<div class="alert alert-success mb-0">' + escapeHtml(data.description) + '</div>';
                break;
            case 'spellcheck':
                html = renderSpellcheck(data.corrections);
                break;
        }

        content.innerHTML = html;
    })
    .catch(err => {
        spinner.style.display = 'none';
        content.innerHTML = '<div class="alert alert-danger mb-0">Error: ' + escapeHtml(err.message) + '</div>';
    });
}

function renderEntities(entities, count) {
    let html = '<p class="mb-2"><strong>' + count + '</strong> entities found</p>';
    const labels = { persons: 'Persons', organizations: 'Organizations', places: 'Places', dates: 'Dates' };
    const colors = { persons: 'primary', organizations: 'info', places: 'success', dates: 'warning' };

    for (const [type, values] of Object.entries(entities)) {
        if (values.length === 0) continue;
        html += '<div class="mb-2"><strong>' + (labels[type] || type) + ':</strong> ';
        values.forEach(v => {
            html += '<span class="badge bg-' + (colors[type] || 'secondary') + ' me-1">' + escapeHtml(v) + '</span>';
        });
        html += '</div>';
    }

    return html || '<div class="text-muted">No entities found.</div>';
}

function renderSpellcheck(corrections) {
    if (!corrections || corrections.length === 0) {
        return '<div class="alert alert-success mb-0"><i class="fas fa-check"></i> No spelling errors found.</div>';
    }

    let html = '<table class="table table-sm table-bordered mb-0"><thead><tr><th>Original</th><th>Suggestion</th><th>Position</th></tr></thead><tbody>';
    corrections.forEach(c => {
        html += '<tr><td class="text-danger">' + escapeHtml(c.original) + '</td>'
            + '<td class="text-success">' + escapeHtml(c.suggestion) + '</td>'
            + '<td>' + c.position + '</td></tr>';
    });
    html += '</tbody></table>';

    return html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush
