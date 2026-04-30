@extends('theme::layouts.1col')

@section('title', 'DOI Configuration')
@section('body-class', 'admin doi config')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('DOI Configuration') }}</h1>
      <span class="small text-muted">{{ __('DataCite Integration Settings') }}</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('doi.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Dashboard') }}
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('doi.configSave') }}" method="POST">
    @csrf

    <div class="row">
      <div class="col-lg-8">
        {{-- DataCite API Credentials --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" >DataCite API Credentials</div>
          <div class="card-body">
            <div class="row mb-3">
              <label for="datacite_repository_id" class="col-sm-3 col-form-label">Repository ID <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="datacite_repository_id" name="datacite_repository_id"
                       value="{{ old('datacite_repository_id', $settings['datacite_repository_id'] ?? '') }}"
                       placeholder="{{ __('e.g. INSTITUTION.REPOSITORY') }}">
                <div class="form-text">Your DataCite repository ID (format: PREFIX.SUFFIX)</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="datacite_prefix" class="col-sm-3 col-form-label">DOI Prefix <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="datacite_prefix" name="datacite_prefix"
                       value="{{ old('datacite_prefix', $settings['datacite_prefix'] ?? '') }}"
                       placeholder="{{ __('e.g. 10.12345') }}">
                <div class="form-text">Your assigned DOI prefix</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="datacite_password" class="col-sm-3 col-form-label">Password <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <input type="password" class="form-control" id="datacite_password" name="datacite_password"
                       value="{{ old('datacite_password', $settings['datacite_password'] ?? '') }}">
                <div class="form-text">DataCite API password</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="datacite_url" class="col-sm-3 col-form-label">API URL <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <select class="form-select" id="datacite_url" name="datacite_url">
                  <option value="https://api.datacite.org" {{ (old('datacite_url', $settings['datacite_url'] ?? '') === 'https://api.datacite.org') ? 'selected' : '' }}>
                    Production (https://api.datacite.org)
                  </option>
                  <option value="https://api.test.datacite.org" {{ (old('datacite_url', $settings['datacite_url'] ?? '') === 'https://api.test.datacite.org') ? 'selected' : '' }}>
                    Test (https://api.test.datacite.org)
                  </option>
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <label for="datacite_environment" class="col-sm-3 col-form-label">Environment <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <select class="form-select" id="datacite_environment" name="datacite_environment">
                  <option value="test" {{ (old('datacite_environment', $settings['datacite_environment'] ?? '') === 'test') ? 'selected' : '' }}>
                    Test
                  </option>
                  <option value="production" {{ (old('datacite_environment', $settings['datacite_environment'] ?? '') === 'production') ? 'selected' : '' }}>
                    Production
                  </option>
                </select>
                <div class="form-text">Use 'test' until ready for production DOIs</div>
              </div>
            </div>

            <div class="row">
              <div class="col-sm-9 offset-sm-3">
                <button type="button" id="test-connection" class="btn btn-outline-secondary">
                  <i class="fas fa-plug me-1"></i> {{ __('Test Connection') }}
                </button>
                <span id="connection-result" class="ms-2"></span>
              </div>
            </div>
          </div>
        </div>

        {{-- Minting Settings --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" >Minting Settings</div>
          <div class="card-body">
            <div class="row mb-3">
              <label for="auto_mint" class="col-sm-3 col-form-label">Auto-mint DOIs <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="auto_mint" value="1" id="auto_mint"
                         {{ (old('auto_mint', $settings['auto_mint'] ?? '0') === '1') ? 'checked' : '' }}>
                  <label class="form-check-label" for="auto_mint">
                    Auto-mint DOIs when records are published
                   <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <label class="col-sm-3 col-form-label">Auto-mint levels <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                @php
                  $autoMintLevels = json_decode($settings['auto_mint_levels'] ?? '[]', true) ?: [];
                  $levels = ['Fonds', 'Collection', 'Series', 'File', 'Item'];
                @endphp
                @foreach($levels as $level)
                  <div class="form-check">
                    <input type="checkbox" name="auto_mint_levels[]" value="{{ $level }}"
                           class="form-check-input" id="level_{{ strtolower($level) }}"
                           {{ in_array($level, $autoMintLevels) ? 'checked' : '' }}>
                    <label class="form-check-label" for="level_{{ strtolower($level) }}">
                      {{ $level }}
                     <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                @endforeach
                <div class="form-text">Only auto-mint for these levels of description</div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-sm-9 offset-sm-3">
                <div class="form-check">
                  <input type="checkbox" name="require_digital_object" value="1" class="form-check-input" id="require_digital_object"
                         {{ (old('require_digital_object', $settings['require_digital_object'] ?? '0') === '1') ? 'checked' : '' }}>
                  <label class="form-check-label" for="require_digital_object">
                    Only auto-mint if record has a digital object
                   <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Metadata Defaults --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" >Metadata Defaults</div>
          <div class="card-body">
            <div class="row mb-3">
              <label for="default_publisher" class="col-sm-3 col-form-label">Default Publisher <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="default_publisher" name="default_publisher"
                       value="{{ old('default_publisher', $settings['default_publisher'] ?? '') }}"
                       placeholder="{{ __('e.g. The Archive and Heritage Group') }}">
                <div class="form-text">Used when no repository-specific publisher is set</div>
              </div>
            </div>

            <div class="row mb-3">
              <label for="default_resource_type" class="col-sm-3 col-form-label">Default Resource Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                @php
                  $resourceTypes = [
                    'Text' => 'Text',
                    'Collection' => 'Collection',
                    'Dataset' => 'Dataset',
                    'Image' => 'Image',
                    'Sound' => 'Sound',
                    'Audiovisual' => 'Audiovisual',
                    'PhysicalObject' => 'Physical Object',
                    'Other' => 'Other',
                  ];
                @endphp
                <select class="form-select" id="default_resource_type" name="default_resource_type">
                  @foreach($resourceTypes as $value => $label)
                    <option value="{{ $value }}" {{ (old('default_resource_type', $settings['default_resource_type'] ?? 'Text') === $value) ? 'selected' : '' }}>
                      {{ $label }}
                    </option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row mb-3">
              <label for="suffix_pattern" class="col-sm-3 col-form-label">DOI Suffix Pattern <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <div class="col-sm-9">
                <input type="text" class="form-control" id="suffix_pattern" name="suffix_pattern"
                       value="{{ old('suffix_pattern', $settings['suffix_pattern'] ?? '{repository_code}/{year}/{object_id}') }}">
                <div class="form-text">
                  Available placeholders: {repository_code}, {year}, {month}, {object_id}, {slug}, {identifier}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        {{-- Help --}}
        <div class="card mb-4">
          <div class="card-header fw-bold" >Help</div>
          <div class="card-body">
            <h6>{{ __('Getting Started') }}</h6>
            <ol class="small">
              <li>Register at <a href="https://doi.datacite.org/" target="_blank">DataCite Fabrica</a></li>
              <li>Create a repository</li>
              <li>Copy your repository ID and password</li>
              <li>Enter credentials above</li>
              <li>Test the connection</li>
              <li>Start minting DOIs</li>
            </ol>

            <h6 class="mt-3">{{ __('Test Mode') }}</h6>
            <p class="small text-muted">
              Use the test API URL while developing. Test DOIs are not resolvable but allow you to verify your integration.
            </p>
          </div>
        </div>

        {{-- Actions --}}
        <div class="card">
          <div class="card-body">
            <div class="d-grid">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> {{ __('Save Configuration') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>

  <script>
  document.getElementById('test-connection').addEventListener('click', function() {
    var btn = this;
    var result = document.getElementById('connection-result');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Testing...';
    result.innerHTML = '';

    fetch('{{ route("doi.configSave") }}?test=1')
      .then(function(response) { return response.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';

        if (data.success) {
          result.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> ' + data.message + '</span>';
        } else {
          result.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> ' + (data.message || 'Connection failed') + '</span>';
        }
      })
      .catch(function(error) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plug me-1"></i> Test Connection';
        result.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Error: ' + error.message + '</span>';
      });
  });
  </script>
@endsection
