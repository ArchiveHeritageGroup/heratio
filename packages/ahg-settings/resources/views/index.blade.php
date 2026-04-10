@extends('theme::layouts.1col')

@section('title', 'Settings')
@section('body-class', 'admin settings')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0">AHG Plugin Settings</h1>
  </div>
  <p class="text-muted mb-4">Configure theme, plugin, and system settings</p>

  @php
    // ── Build a single sorted array of ALL tiles ──────────────────────────
    $allTiles = [];

    // Helper to add a tile only when its route exists
    $addTile = function (string $label, string $icon, string $desc, string $routeName, string $color = 'primary', string $btnText = 'Configure', string $btnIcon = 'fa-cog', ?string $routeParam = null) use (&$allTiles) {
        if (!\Route::has($routeName)) return;
        $allTiles[$label] = [
            'label' => $label,
            'icon'  => $icon,
            'desc'  => $desc,
            'url'   => $routeParam ? route($routeName, $routeParam) : route($routeName),
            'color' => $color,
            'btn'   => $btnText,
            'btn_icon' => $btnIcon,
        ];
    };

    // ── 1. Scope cards from controller ($scopeCards) ──
    $dedicatedRoutes = [
        '_global' => 'settings.global',
        'default_template' => 'settings.default-template',
        'element_visibility' => 'settings.visible-elements',
        'i18n_languages' => 'settings.languages',
        'ui_label' => 'settings.interface-labels',
        'oai' => 'settings.oai',
    ];
    foreach ($scopeCards ?? [] as $card) {
        $rn = $dedicatedRoutes[$card->key] ?? null;
        $url = $rn ? route($rn) : route('settings.section', $card->key);
        $allTiles[$card->label] = [
            'label' => $card->label,
            'icon'  => $card->icon ?? 'fa-cogs',
            'desc'  => $card->description ?? '',
            'url'   => $url,
            'color' => 'primary',
            'btn'   => 'Configure',
            'btn_icon' => 'fa-cog',
        ];
    }

    // ── 2. AHG setting groups ($ahgGroups) ──
    $ahgLabels = [
        'general' => 'Theme Configuration', 'email' => 'Email', 'metadata' => 'Metadata Extraction',
        'media' => 'Media Player', 'jobs' => 'Background Jobs', 'spectrum' => 'Spectrum / Collections',
        'photos' => 'Condition Photos', 'data_protection' => 'Data Protection', 'iiif' => 'IIIF Viewer',
        'faces' => 'Face Detection', 'fuseki' => 'Fuseki / RIC', 'ingest' => 'Data Ingest',
        'accession' => 'Accession Management', 'encryption' => 'Encryption', 'voice_ai' => 'Voice & AI',
        'integrity' => 'Integrity', 'multi_tenant' => 'Multi-Tenancy', 'portable_export' => 'Portable Export',
        'security' => 'Security', 'features' => 'Features', 'compliance' => 'Compliance',
        'ftp' => 'FTP / SFTP', 'ai_condition' => 'AI Condition Assessment',
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
    $ahgIcons = [
        'accession' => 'fa-inbox', 'ai_condition' => 'fa-robot', 'compliance' => 'fa-clipboard-check',
        'data_protection' => 'fa-user-shield', 'email' => 'fa-envelope', 'encryption' => 'fa-lock',
        'faces' => 'fa-user-circle', 'features' => 'fa-star', 'fuseki' => 'fa-project-diagram',
        'general' => 'fa-palette', 'iiif' => 'fa-images', 'ingest' => 'fa-file-import',
        'integrity' => 'fa-check-double', 'jobs' => 'fa-tasks', 'media' => 'fa-play-circle',
        'metadata' => 'fa-tags', 'multi_tenant' => 'fa-building', 'photos' => 'fa-camera',
        'portable_export' => 'fa-compact-disc', 'security' => 'fa-shield-alt',
        'spectrum' => 'fa-archive', 'voice_ai' => 'fa-microphone', 'ftp' => 'fa-server',
    ];
    $ahgColors = [
        'security' => 'danger', 'encryption' => 'danger', 'data_protection' => 'warning',
        'ai_condition' => 'info', 'iiif' => 'info', 'media' => 'info',
        'compliance' => 'warning', 'integrity' => 'success',
    ];
    $ahgGroupsByKey = ($ahgGroups ?? collect())->keyBy('key');
    foreach ($ahgGroupsByKey as $gKey => $gData) {
        $label = $ahgLabels[$gKey] ?? $gData->label;
        // Skip 'general' — we have a dedicated Theme Configuration tile
        if ($gKey === 'general') continue;
        $allTiles[$label] = [
            'label' => $label,
            'icon'  => $ahgIcons[$gKey] ?? 'fa-puzzle-piece',
            'desc'  => $ahgDescriptions[$gKey] ?? ucfirst(str_replace('_', ' ', $gKey)) . ' settings',
            'url'   => route('settings.ahg', $gKey),
            'color' => $ahgColors[$gKey] ?? 'primary',
            'btn'   => 'Configure',
            'btn_icon' => 'fa-cog',
        ];
    }

    // ── 3. Static / standalone tiles ──
    $addTile('Theme Configuration',   'fa-palette',            'Customize appearance, colours, logo, branding, and custom CSS', 'settings.themes');
    $addTile('Reading Room',          'fa-book-reader',        'Researcher registration and reading room settings', 'research.dashboard', 'success');
    $addTile('Default page elements', 'fa-th-large',           'Toggle logo, title, description, language menu, digital object carousel and map, copyright and material filters', 'settings.page-elements');
    $addTile('Cron Jobs',             'fa-clock',              'Manage scheduled tasks — enable/disable, edit schedules, run now', 'settings.cron-jobs', 'secondary', 'Manage Jobs', 'fa-clock');
    $addTile('Error Log',             'fa-exclamation-triangle','View and manage application error logs', 'settings.error-log', 'danger', 'View Logs', 'fa-bug');
    $addTile('Plugins',               'fa-puzzle-piece',       'Manage installed packages and plugins', 'settings.plugins', 'info', 'View Plugins', 'fa-plug');

    // AtoM-parity tiles
    $addTile('Heritage Platform',     'fa-landmark',           'Access control, analytics, branding, custodian tools, and community features', 'heritage.admin', 'warning', 'Admin', 'fa-tools');
    $addTile('Integrity Assurance',   'fa-shield-alt',         'Fixity verification, retention policies, legal holds, disposition review, and alerting', 'integrity.index', 'danger', 'Dashboard', 'fa-tachometer-alt');
    $addTile('Media Processing',      'fa-cogs',               'Transcription, thumbnails, waveforms & media derivatives', 'media-processing.index');
    $addTile('Watermark Settings',    'fa-stamp',              'Configure default watermarks for images and downloads', 'acl.watermark-settings', 'warning');
    $addTile('Library Settings',      'fa-book',               'Loan rules, circulation, fines, patron defaults, OPAC, ISBN providers', 'settings.library');
    $addTile('Levels of Description', 'fa-layer-group',        'Assign levels to sectors (Archive, Museum, Library, Gallery, DAM)', 'settings.levels');
    $addTile('Carousel Settings',     'fa-images',             'Homepage carousel and slideshow configuration', 'settings.carousel', 'info');
    $addTile('Privacy Compliance',    'fa-user-shield',        'POPIA, NDPA, GDPR compliance - DSARs, Breaches, ROPA, PAIA', 'ahgspectrum.privacy-compliance', 'warning');
    $addTile('Authority Records',     'fa-id-card',            'External linking, completeness, NER pipeline, merge/dedup, occupations, functions', 'settings.authority');
    $addTile('Semantic Search',       'fa-brain',              'Thesaurus, synonyms, query expansion and search enhancement settings', 'ric.semantic-search', 'info');
    $addTile('E-Commerce',            'fa-store',              'Shopping cart, product pricing, payment gateway and order management', 'cart.admin.settings', 'success');
    $addTile('Order Management',      'fa-shopping-bag',       'View and manage customer orders', 'cart.admin.orders', 'success', 'Manage');
    $addTile('Preservation & Backup', 'fa-cloud-upload-alt',   'Configure backup replication targets, verify integrity, and manage preservation', 'settings.section', 'success', 'Configure', 'fa-cog', 'preservation');

    // 9 tiles missing from AtoM parity (all have existing routes — were sidebar-only)
    $addTile('AHG Central',           'fa-cloud',              'Connect to AHG Central cloud services for shared NER training and AI features', 'settings.ahg-integration');
    $addTile('AI Services',           'fa-brain',              'NER, Summarization, Spell Check — processing mode and field mappings', 'settings.ai-services');
    $addTile('ICIP Settings',         'fa-shield-alt',         'Indigenous Cultural and Intellectual Property management settings', 'settings.icip-settings', 'warning');
    $addTile('Marketplace',           'fa-store-alt',          'Commission rates, listing fees, currencies, payout rules, and platform configuration', 'ahgmarketplace.admin-settings', 'success');
    $addTile('Sector Numbering',      'fa-hashtag',            'Configure unique numbering schemes per GLAM/DAM sector', 'settings.sector-numbering');
    $addTile('Services Monitor',      'fa-heartbeat',          'Monitor system services health and configure notifications', 'settings.services', 'success');
    $addTile('System Information',    'fa-server',             'Installed software versions, PHP extensions, disk usage, and system health', 'settings.system-info');
    $addTile('Text-to-Speech',        'fa-volume-up',          'Configure read-aloud accessibility feature for record pages', 'settings.tts');
    $addTile('Webhooks',              'fa-broadcast-tower',    'Configure event-based webhooks for external system integration', 'settings.webhooks');

    // Sort alphabetically by label (matching AtoM ksort)
    ksort($allTiles, SORT_NATURAL | SORT_FLAG_CASE);
  @endphp

  <div class="row">
    @foreach($allTiles as $tile)
      <div class="col-lg-4 col-md-6 mb-4">
        <a href="{{ $tile['url'] }}" class="text-decoration-none">
          <div class="card h-100 shadow-sm settings-tile {{ $tile['color'] !== 'primary' ? 'border-' . $tile['color'] : '' }}">
            <div class="card-body text-center py-4">
              <div class="mb-3"><i class="fas {{ $tile['icon'] }} fa-3x text-{{ $tile['color'] }}"></i></div>
              <h5 class="card-title text-dark">{{ $tile['label'] }}</h5>
              <p class="card-text text-muted small">{{ $tile['desc'] }}</p>
            </div>
            <div class="card-footer bg-white border-0 text-center pb-4">
              <span class="btn atom-btn-{{ $tile['color'] === 'primary' ? 'white' : ($tile['color'] === 'secondary' ? 'white' : $tile['color']) }}">
                <i class="fas {{ $tile['btn_icon'] }}"></i> {{ $tile['btn'] }}
              </span>
            </div>
          </div>
        </a>
      </div>
    @endforeach
  </div>

  {{-- Quick Access: TIFF to PDF Merge (matches AtoM dam-enabled section) --}}
  @php
    $damEnabled = false;
    try {
      $damEnabled = (bool) \DB::table('ahg_settings')
        ->where('setting_key', 'dam_tools_enabled')
        ->where('setting_group', 'general')
        ->value('setting_value');
    } catch (\Throwable $e) {}
  @endphp
  @if($damEnabled && \Route::has('tiffpdfmerge.index'))
  <div class="card mt-4 border-primary">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Quick Access: TIFF to PDF Merge</h5>
    </div>
    <div class="card-body">
      <div class="row align-items-center">
        <div class="col-md-8">
          <p class="mb-0">
            <strong>Create multi-page PDF documents from images</strong><br>
            <small class="text-muted">Upload multiple TIFF, JPEG, or PNG files and merge them into a single PDF/A archival document. Jobs run in the background and can be attached directly to archival records.</small>
          </p>
        </div>
        <div class="col-md-4 text-end">
          <a href="{{ route('tiffpdfmerge.index') }}" class="btn btn-primary btn-lg">
            <i class="fas fa-file-pdf me-1"></i> Create PDF
          </a>
        </div>
      </div>
    </div>
  </div>
  @endif

  <style>
    .settings-tile { transition: transform 0.15s ease, box-shadow 0.15s ease; cursor: pointer; }
    .settings-tile:hover { transform: translateY(-4px); box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important; }
    .settings-tile .card-footer { background-color: transparent !important; }
  </style>
@endsection
