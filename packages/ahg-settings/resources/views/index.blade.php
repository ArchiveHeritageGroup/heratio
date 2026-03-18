@extends('theme::layouts.1col')

@section('title', 'Settings')
@section('body-class', 'admin settings')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-cogs"></i> Settings</h1>
  </div>
  <p class="text-muted mb-4">Configure theme, plugin, and system settings</p>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="row">
    {{-- Theme tile --}}
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.themes') }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas fa-palette fa-3x text-primary"></i></div>
            <h5 class="card-title text-dark">Theme Configuration</h5>
            <p class="card-text text-muted small">Customize appearance, colours, logo, branding, and custom CSS</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-primary"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>

    {{-- Heratio setting scopes --}}
    @foreach($scopeCards as $card)
    <div class="col-lg-4 col-md-6 mb-4">
      @php
        $dedicatedRoutes = [
          '_global' => 'settings.global',
          'default_template' => 'settings.default-template',
          'element_visibility' => 'settings.visible-elements',
          'i18n_languages' => 'settings.languages',
          'ui_label' => 'settings.interface-labels',
          'oai' => 'settings.oai',
        ];
        $cardRoute = isset($dedicatedRoutes[$card->key]) ? route($dedicatedRoutes[$card->key]) : route('settings.section', $card->key);
      @endphp
      <a href="{{ $cardRoute }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas {{ $card->icon }} fa-3x text-primary"></i></div>
            <h5 class="card-title text-dark">{{ $card->label }}</h5>
            <p class="card-text text-muted small">{{ $card->description }}</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-primary"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>
    @endforeach

    {{-- AHG setting groups — ordered to match AtoM --}}
    @php
      $ahgOrder = [
        'general', 'email', 'metadata', 'media', 'jobs', 'spectrum', 'photos',
        'data_protection', 'iiif', 'faces', 'fuseki', 'ingest', 'accession',
        'encryption', 'voice_ai', 'integrity', 'multi_tenant', 'portable_export',
        'security', 'features', 'compliance', 'ftp', 'ai_condition',
      ];
      $ahgLabels = [
        'general' => 'Theme Configuration', 'email' => 'Email', 'metadata' => 'Metadata Extraction',
        'media' => 'Media Player', 'jobs' => 'Background Jobs', 'spectrum' => 'Spectrum / Collections',
        'photos' => 'Condition Photos', 'data_protection' => 'Data Protection', 'iiif' => 'IIIF Viewer',
        'faces' => 'Face Detection', 'fuseki' => 'Fuseki / RIC', 'ingest' => 'Data Ingest',
        'accession' => 'Accession Management', 'encryption' => 'Encryption', 'voice_ai' => 'Voice & AI',
        'integrity' => 'Integrity', 'multi_tenant' => 'Multi-Tenancy', 'portable_export' => 'Portable Export',
        'security' => 'Security', 'features' => 'Features', 'compliance' => 'Compliance',
        'ftp' => 'FTP / SFTP', 'ai_condition' => 'AI Condition',
      ];
      $ahgDescriptions = [
        'general' => 'Customize appearance, colours, logo, branding, and custom CSS',
        'email' => 'Email notifications and SMTP configuration',
        'metadata' => 'Automatic metadata extraction from uploaded files',
        'media' => 'Media player behaviour and display options',
        'jobs' => 'Background job processing and notifications',
        'spectrum' => 'Spectrum collections management procedures',
        'photos' => 'Condition photo thumbnails and EXIF settings',
        'data_protection' => 'POPIA / GDPR compliance and data handling',
        'iiif' => 'IIIF image viewer and annotation settings',
        'faces' => 'Face detection and recognition settings',
        'fuseki' => 'Apache Fuseki RDF triplestore synchronisation',
        'ingest' => 'Data ingest pipeline and processing options',
        'accession' => 'Accession workflow and numbering options',
        'encryption' => 'Field-level encryption and key management',
        'voice_ai' => 'Voice interface and AI assistant settings',
        'integrity' => 'Fixity checking and integrity monitoring',
        'multi_tenant' => 'Multi-tenancy isolation and branding',
        'portable_export' => 'Portable offline export configuration',
        'security' => 'Security lockout and password policies',
        'features' => 'Feature toggles for 3D viewer, IIIF, and bookings',
        'compliance' => 'Regulatory compliance settings',
        'ftp' => 'FTP / SFTP connection settings',
        'ai_condition' => 'AI-powered condition assessment',
      ];
      $icons = [
        'accession' => 'fa-inbox', 'ai_condition' => 'fa-robot', 'compliance' => 'fa-clipboard-check',
        'data_protection' => 'fa-user-shield', 'email' => 'fa-envelope', 'encryption' => 'fa-lock',
        'faces' => 'fa-user-circle', 'features' => 'fa-star', 'fuseki' => 'fa-project-diagram',
        'general' => 'fa-palette', 'iiif' => 'fa-images', 'ingest' => 'fa-file-import',
        'integrity' => 'fa-check-double', 'jobs' => 'fa-tasks', 'media' => 'fa-play-circle',
        'metadata' => 'fa-tags', 'multi_tenant' => 'fa-building', 'photos' => 'fa-camera',
        'portable_export' => 'fa-compact-disc', 'security' => 'fa-shield-alt',
        'spectrum' => 'fa-archive', 'voice_ai' => 'fa-microphone', 'ftp' => 'fa-server',
      ];
      $colors = [
        'security' => 'danger', 'encryption' => 'danger', 'data_protection' => 'warning',
        'ai_condition' => 'info', 'iiif' => 'info', 'media' => 'info',
        'compliance' => 'warning', 'integrity' => 'success',
      ];
      // Index ahgGroups by key for count lookup
      $ahgGroupsByKey = $ahgGroups->keyBy('key');
      // Sort: show groups in defined order, then any extra groups from DB
      $orderedKeys = collect($ahgOrder)->filter(fn ($k) => $ahgGroupsByKey->has($k));
      $extraKeys = $ahgGroups->pluck('key')->diff($ahgOrder);
      $sortedKeys = $orderedKeys->merge($extraKeys);
    @endphp

    @foreach($sortedKeys as $gKey)
    @php
      $gData = $ahgGroupsByKey[$gKey];
      $icon = $icons[$gKey] ?? 'fa-puzzle-piece';
      $color = $colors[$gKey] ?? 'primary';
      $label = $ahgLabels[$gKey] ?? $gData->label;
      $desc = $ahgDescriptions[$gKey] ?? ucfirst(str_replace('_', ' ', $gKey)) . ' settings';
    @endphp
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.ahg', $gKey) }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile {{ $color !== 'primary' ? 'border-' . $color : '' }}">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas {{ $icon }} fa-3x text-{{ $color }}"></i></div>
            <h5 class="card-title text-dark">{{ $label }}</h5>
            <p class="card-text text-muted small">{{ $desc }}</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-{{ $color }}"><i class="fas fa-cog"></i> Configure</span>
          </div>
        </div>
      </a>
    </div>
    @endforeach

    {{-- Standalone settings pages --}}
    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.cron-jobs') }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas fa-clock fa-3x text-secondary"></i></div>
            <h5 class="card-title text-dark">Cron Jobs</h5>
            <p class="card-text text-muted small">Manage scheduled tasks — enable/disable, edit schedules, run now</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-secondary"><i class="fas fa-clock"></i> Manage Jobs</span>
          </div>
        </div>
      </a>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.error-log') }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas fa-exclamation-triangle fa-3x text-danger"></i></div>
            <h5 class="card-title text-dark">Error Log</h5>
            <p class="card-text text-muted small">View and manage application error logs</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-danger"><i class="fas fa-bug"></i> View Logs</span>
          </div>
        </div>
      </a>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
      <a href="{{ route('settings.plugins') }}" class="text-decoration-none">
        <div class="card h-100 shadow-sm settings-tile">
          <div class="card-body text-center py-4">
            <div class="mb-3"><i class="fas fa-puzzle-piece fa-3x text-info"></i></div>
            <h5 class="card-title text-dark">Plugins</h5>
            <p class="card-text text-muted small">Manage installed packages and plugins</p>
          </div>
          <div class="card-footer bg-white border-0 text-center pb-4">
            <span class="btn btn-info"><i class="fas fa-plug"></i> View Plugins</span>
          </div>
        </div>
      </a>
    </div>
  </div>

  <style>
    .settings-tile { transition: transform 0.15s ease, box-shadow 0.15s ease; cursor: pointer; }
    .settings-tile:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important; }
    .settings-tile .card-footer { background-color: transparent !important; }
  </style>
@endsection
