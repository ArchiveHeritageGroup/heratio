@php
  $plugins = $themeData['enabledPluginMap'] ?? [];

  // Pending-count badges for menu items
  $pendingResearchers = 0;
  $pendingBookings = 0;
  $pendingReview = 0;
  $pendingDuplicates = 0;
  $pendingDoi = 0;
  try {
      if (\Illuminate\Support\Facades\Schema::hasTable('research_researcher')) {
          $pendingResearchers = \Illuminate\Support\Facades\DB::table('research_researcher')->where('status', 'pending')->count();
      }
  } catch (\Throwable $e) {}
  try {
      if (\Illuminate\Support\Facades\Schema::hasTable('research_booking')) {
          $pendingBookings = \Illuminate\Support\Facades\DB::table('research_booking')->where('status', 'pending')->count();
      }
  } catch (\Throwable $e) {}
  try {
      if (\Illuminate\Support\Facades\Schema::hasTable('researcher_submission')) {
          $pendingReview = \Illuminate\Support\Facades\DB::table('researcher_submission')->whereIn('status', ['submitted', 'under_review'])->count();
      }
  } catch (\Throwable $e) {}
  try {
      if (\Illuminate\Support\Facades\Schema::hasTable('ahg_duplicate_detection')) {
          $pendingDuplicates = \Illuminate\Support\Facades\DB::table('ahg_duplicate_detection')->where('status', 'pending')->count();
      }
  } catch (\Throwable $e) {}
  try {
      if (\Illuminate\Support\Facades\Schema::hasTable('ahg_doi_queue')) {
          $pendingDoi = \Illuminate\Support\Facades\DB::table('ahg_doi_queue')->where('status', 'pending')->count();
      }
  } catch (\Throwable $e) {}
@endphp

{{-- AHG Plugins menu (admin only) --}}
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="ahg-admin-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-cubes px-0 px-lg-2 py-2" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-custom-class="d-none d-lg-block" title="{{ __('AHG Plugins') }}" aria-hidden="true"></i>
    <span class="d-lg-none mx-1" aria-hidden="true">AHG Plugins</span>
    <span class="visually-hidden">AHG Plugins</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="ahg-admin-menu" style="max-height: 80vh; overflow-y: auto;">
    <li><h6 class="dropdown-header">{{ __('AHG Plugins') }}</h6></li>

    {{-- Settings --}}
    <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="fas fa-cog me-2"></i>AHG Settings</a></li>
    <li><a class="dropdown-item" href="{{ route('dropdown.index') }}"><i class="fas fa-list me-2"></i>Dropdown Manager</a></li>

    {{-- Translation --}}
    @php
      $pendingDrafts = 0;
      try {
          if (\Illuminate\Support\Facades\Schema::hasTable('ahg_translation_draft')) {
              $pendingDrafts = \Illuminate\Support\Facades\DB::table('ahg_translation_draft')->where('status', 'draft')->count();
          }
      } catch (\Throwable $e) {}
    @endphp
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Translation') }}</h6></li>
    <li><a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('ahgtranslation.drafts') }}">
      <span><i class="fas fa-language me-2"></i>{{ __('Translation drafts') }}</span>
      @if($pendingDrafts > 0)
        <span class="badge bg-warning text-dark rounded-pill">{{ $pendingDrafts }}</span>
      @endif
    </a></li>
    <li><a class="dropdown-item" href="{{ route('ahgtranslation.languages') }}"><i class="fas fa-globe me-2"></i>{{ __('Languages') }}</a></li>
    <li><a class="dropdown-item" href="{{ route('ahgtranslation.settings') }}"><i class="fas fa-cog me-2"></i>{{ __('Translation settings') }}</a></li>

    {{-- Security --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Security') }}</h6></li>
    <li><a class="dropdown-item" href="{{ route('acl.clearances') }}"><i class="fas fa-user-lock me-2"></i>Clearances</a></li>

    {{-- Research --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Research') }}</h6></li>
    <li><a class="dropdown-item" href="{{ url('/research/dashboard') }}"><i class="fas fa-book-reader me-2"></i>Dashboard</a></li>
    <li><a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ url('/research/researchers') }}">
      <span><i class="fas fa-user-graduate me-2"></i>Researchers</span>
      @if($pendingResearchers > 0)
        <span class="badge bg-warning text-dark rounded-pill">{{ $pendingResearchers }}</span>
      @endif
    </a></li>
    <li><a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ url('/research/bookings') }}">
      <span><i class="fas fa-calendar-check me-2"></i>Bookings</span>
      @if($pendingBookings > 0)
        <span class="badge bg-danger rounded-pill">{{ $pendingBookings }}</span>
      @endif
    </a></li>
    <li><a class="dropdown-item" href="{{ url('/research/rooms') }}"><i class="fas fa-book-reader me-2"></i>Rooms</a></li>

    {{-- Researcher Submissions --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Researcher Submissions') }}</h6></li>
    <li><a class="dropdown-item" href="{{ route('researcher.dashboard') }}"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
    <li><a class="dropdown-item d-flex justify-content-between align-items-center" href="{{ route('researcher.pending') }}">
      <span><i class="fas fa-clock me-2"></i>Pending Review</span>
      @if($pendingReview > 0)
        <span class="badge bg-warning text-dark rounded-pill">{{ $pendingReview }}</span>
      @endif
    </a></li>
    <li><a class="dropdown-item" href="{{ route('researcher.import') }}"><i class="fas fa-file-import me-2"></i>Import Exchange</a></li>

    {{-- Access --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Access') }}</h6></li>
    <li><a class="dropdown-item" href="{{ route('acl.access-requests') }}"><i class="fas fa-key me-2"></i>Requests</a></li>
    <li><a class="dropdown-item" href="{{ route('acl.approvers') }}"><i class="fas fa-user-check me-2"></i>Approvers</a></li>

    {{-- Audit --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Audit') }}</h6></li>
    <li><a class="dropdown-item" href="{{ route('audit.statistics') }}"><i class="fas fa-chart-bar me-2"></i>Statistics</a></li>
    <li><a class="dropdown-item" href="{{ route('acl.audit-log') }}"><i class="fas fa-history me-2"></i>Logs</a></li>
    <li><a class="dropdown-item" href="{{ route('audit.settings') }}"><i class="fas fa-cog me-2"></i>Settings</a></li>
    <li><a class="dropdown-item" href="{{ route('settings.error-log') }}"><i class="fas fa-exclamation-triangle me-2"></i>Error Log</a></li>

    {{-- RiC (gated by ahgRicExplorerPlugin) --}}
    @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgRicExplorerPlugin'))
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">RiC</h6></li>
    <li><a class="dropdown-item" href="{{ route('ric.index') }}"><i class="fas fa-sitemap me-2"></i>RiC Dashboard</a></li>
    <li><a class="dropdown-item" href="{{ route('ric.import') }}"><i class="fas fa-file-import me-2"></i>RDF Import (TTL/JSON-LD/RDF-XML)</a></li>
    @endif

    {{-- Data Quality (Data Migration gated by ahgDataMigrationPlugin; Dedupe always shown) --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Data Quality') }}</h6></li>
    @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgDataMigrationPlugin'))
    <li><a class="dropdown-item" href="{{ route('data-migration.index') }}"><i class="fas fa-exchange-alt me-2"></i>Data Migration</a></li>
    @endif
    <li><a class="dropdown-item" href="{{ route('dedupe.index') }}"><i class="fas fa-clone me-2"></i>Duplicate Detection</a></li>

    {{-- Data Entry --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Data Entry') }}</h6></li>
    <li><a class="dropdown-item" href="{{ url('/admin/formTemplates') }}"><i class="fas fa-wpforms me-2"></i>Form Templates</a></li>

    {{-- DOI Management --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('DOI Management') }}</h6></li>
    <li><a class="dropdown-item" href="{{ route('doi.index') }}"><i class="fas fa-fingerprint me-2"></i>DOI Dashboard</a></li>
    <li><a class="dropdown-item" href="{{ route('doi.queue') }}"><i class="fas fa-stream me-2"></i>Minting Queue</a></li>

    {{-- Heritage (gated by ahgHeritageAccountingPlugin) --}}
    @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgHeritageAccountingPlugin'))
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Heritage') }}</h6></li>
    <li><a class="dropdown-item" href="{{ route('heritage.admin') }}"><i class="fas fa-landmark me-2"></i>Admin</a></li>
    <li><a class="dropdown-item" href="{{ route('heritage.analytics') }}"><i class="fas fa-chart-line me-2"></i>Analytics</a></li>
    <li><a class="dropdown-item" href="{{ route('heritage.custodian') }}"><i class="fas fa-hands me-2"></i>Custodian</a></li>
    @endif

    {{-- Maintenance (Backup/Restore gated by ahgBackupPlugin; Jobs always shown) --}}
    <li><hr class="dropdown-divider"></li>
    <li><h6 class="dropdown-header">{{ __('Maintenance') }}</h6></li>
    @if(\AhgCore\Services\MenuService::isPluginEnabled('ahgBackupPlugin'))
    <li><a class="dropdown-item" href="{{ route('backup.index') }}"><i class="fas fa-download me-2"></i>Backup</a></li>
    <li><a class="dropdown-item" href="{{ route('backup.restore') }}"><i class="fas fa-upload me-2"></i>Restore</a></li>
    @endif
    <li><a class="dropdown-item" href="{{ route('job.browse') }}"><i class="fas fa-tasks me-2"></i>Jobs</a></li>
  </ul>
</li>
