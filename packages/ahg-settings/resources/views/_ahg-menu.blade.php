@php
  $ahgMenuGroups = [
    ['key' => 'general',         'label' => 'Theme Configuration',    'icon' => 'fa-palette'],
    ['key' => 'email',           'label' => 'Email',                  'icon' => 'fa-envelope'],
    ['key' => 'metadata',        'label' => 'Metadata Extraction',    'icon' => 'fa-tags'],
    ['key' => 'media',           'label' => 'Media Player',           'icon' => 'fa-play-circle'],
    ['key' => 'jobs',            'label' => 'Background Jobs',        'icon' => 'fa-tasks'],
    ['key' => 'spectrum',        'label' => 'Spectrum / Collections', 'icon' => 'fa-archive'],
    ['key' => 'photos',          'label' => 'Condition Photos',       'icon' => 'fa-camera'],
    ['key' => 'data_protection', 'label' => 'Data Protection',        'icon' => 'fa-user-shield'],
    ['key' => 'iiif',            'label' => 'IIIF Viewer',            'icon' => 'fa-images'],
    ['key' => 'faces',           'label' => 'Face Detection',         'icon' => 'fa-user-circle'],
    ['key' => 'fuseki',          'label' => 'Fuseki / RIC',           'icon' => 'fa-project-diagram'],
    ['key' => 'ingest',          'label' => 'Data Ingest',            'icon' => 'fa-file-import'],
    ['key' => 'accession',       'label' => 'Accession Management',   'icon' => 'fa-inbox'],
    ['key' => 'encryption',      'label' => 'Encryption',             'icon' => 'fa-lock'],
    ['key' => 'voice_ai',        'label' => 'Voice & AI',             'icon' => 'fa-microphone'],
    ['key' => 'integrity',       'label' => 'Integrity',              'icon' => 'fa-check-double'],
    ['key' => 'multi_tenant',    'label' => 'Multi-Tenancy',          'icon' => 'fa-building'],
    ['key' => 'portable_export', 'label' => 'Portable Export',        'icon' => 'fa-compact-disc'],
    ['key' => 'security',        'label' => 'Security',               'icon' => 'fa-shield-alt'],
    ['key' => 'features',        'label' => 'Features',               'icon' => 'fa-star'],
    ['key' => 'compliance',      'label' => 'Compliance',             'icon' => 'fa-clipboard-check'],
    ['key' => 'ftp',             'label' => 'FTP / SFTP',             'icon' => 'fa-server'],
    ['key' => 'ai_condition',    'label' => 'AI Condition',           'icon' => 'fa-robot'],
  ];
@endphp

<nav id="ahg-settings-menu" class="list-group mb-3 sticky-top" style="top: 1rem;">
  <a href="{{ route('settings.index') }}"
     class="list-group-item list-group-item-action d-flex align-items-center">
    <i class="fas fa-home me-2" style="width:18px;text-align:center;"></i>
    Settings home
  </a>
  @foreach ($ahgMenuGroups as $node)
    <a href="{{ route('settings.ahg', $node['key']) }}"
       class="list-group-item list-group-item-action d-flex align-items-center{{ ($currentGroup ?? '') === $node['key'] ? ' active' : '' }}">
      <i class="fas {{ $node['icon'] }} me-2" style="width:18px;text-align:center;"></i>
      {{ $node['label'] }}
    </a>
  @endforeach
</nav>
