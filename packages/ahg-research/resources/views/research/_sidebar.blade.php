{{-- Research Plugin Sidebar Navigation - Migrated from AtoM: _researchSidebar.php --}}
@php
    $active = $sidebarActive ?? '';
    $isAdmin = Auth::check() && \AhgCore\Services\AclService::canAdmin(Auth::id());
    // Resolve the researcher's mode here so the sidebar is correct on EVERY
    // research page, not only the few controllers that pass experienceLevel.
    // Falls back to the controller-supplied value, then a self lookup, then default.
    $expLevel = $experienceLevel ?? null;
    if (empty($expLevel) && Auth::check()) {
        try {
            $expLevel = \Illuminate\Support\Facades\DB::table('research_researcher')
                ->where('user_id', Auth::id())->value('experience_level');
        } catch (\Throwable $e) {
            $expLevel = null;
        }
    }
    $expLevel = $expLevel ?: 'intermediate';
    // Research mode curates the sidebar: Beginning shows the core essentials,
    // Intermediate adds the working tools, Advanced reveals everything.
    $lvlRank = ['beginning' => 1, 'intermediate' => 2, 'advanced' => 3];
    $lvlCur = $lvlRank[$expLevel] ?? 2;
    $atLeast = fn ($n) => $lvlCur >= $n;
    // Administration is collapsed by default - an admin is often here as a
    // researcher, not to administer. Auto-expand only when on an admin page.
    $adminActives = ['researchers', 'bookings', 'rooms', 'seats', 'equipment', 'retrievalQueue', 'walkIn', 'adminTypes', 'adminStatistics', 'institutions', 'activities', 'adminQuotas'];
    $adminOpen = in_array($active, $adminActives, true);
@endphp

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small d-flex justify-content-between align-items-center">
        <span>{{ __('Research mode') }}</span>
        <a href="{{ route('research.projects') }}#research-modes" title="{{ __('What do these modes mean?') }}" class="text-decoration-none"><i class="fas fa-circle-question"></i></a>
    </span>
    <div class="list-group-item">
        <select id="research-experience-level" class="form-select form-select-sm" data-url="{{ route('research.saveExperienceLevel') }}" aria-label="{{ __('Research mode') }}">
            <option value="beginning" {{ $expLevel === 'beginning' ? 'selected' : '' }}>{{ __('Beginning') }}</option>
            <option value="intermediate" {{ $expLevel === 'intermediate' ? 'selected' : '' }}>{{ __('Intermediate') }}</option>
            <option value="advanced" {{ $expLevel === 'advanced' ? 'selected' : '' }}>{{ __('Advanced') }}</option>
        </select>
        <small id="research-experience-level-status" class="text-muted d-block mt-1" aria-live="polite"></small>
    </div>
</div>

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('My Research Journey') }}</span>
    <a href="{{ route('research.dashboard') }}"
       class="list-group-item list-group-item-action {{ $active === 'workspace' ? 'active' : '' }}">
        <i class="fas fa-tachometer-alt me-2"></i>{{ __('My Workspace') }}
    </a>
    <a href="{{ route('research.projects') }}"
       class="list-group-item list-group-item-action {{ $active === 'projects' ? 'active' : '' }}">
        <i class="fas fa-project-diagram me-2"></i>{{ __('My Projects') }}
    </a>
    @if($atLeast(2) && \Illuminate\Support\Facades\Route::has('research.inbox.index'))
    <a href="{{ route('research.inbox.index') }}"
       class="list-group-item list-group-item-action {{ $active === 'inbox' ? 'active' : '' }}">
        <i class="fas fa-inbox me-2"></i>{{ __('Quick Capture Inbox') }}
    </a>
    @endif
    @if($atLeast(2))
    <a href="{{ route('research.workspaces') }}"
       class="list-group-item list-group-item-action {{ $active === 'workspaces' ? 'active' : '' }}">
        <i class="fas fa-users me-2"></i>{{ __('Team Workspaces') }}
    </a>
    @endif
    <a href="{{ route('research.collections') }}"
       class="list-group-item list-group-item-action {{ $active === 'collections' ? 'active' : '' }}">
        <i class="fas fa-layer-group me-2"></i>{{ __('Evidence Sets') }}
    </a>
    <a href="{{ route('research.mobileHome') }}"
       class="list-group-item list-group-item-action {{ $active === 'offline' ? 'active' : '' }}">
        <i class="fas fa-laptop me-2"></i>{{ __('Work Offline') }}
    </a>
    @if($atLeast(2))
    <a href="{{ route('research.journal') }}"
       class="list-group-item list-group-item-action {{ $active === 'journal' ? 'active' : '' }}">
        <i class="fas fa-journal-whills me-2"></i>{{ __('Research Journal') }}
    </a>
    @endif
    @if($atLeast(3))
    <a href="{{ route('research.journal-builder.index') }}"
       class="list-group-item list-group-item-action {{ $active === 'journals' ? 'active' : '' }}">
        <i class="fas fa-newspaper me-2"></i>{{ __('Journals') }}
    </a>
    <a href="{{ route('research.lecture-builder.index') }}"
       class="list-group-item list-group-item-action {{ $active === 'lectures' ? 'active' : '' }}">
        <i class="fas fa-chalkboard-teacher me-2"></i>{{ __('Lectures') }}
    </a>
    <a href="{{ route('research.target-journal.index') }}"
       class="list-group-item list-group-item-action {{ $active === 'target-journals' ? 'active' : '' }}">
        <i class="fas fa-bullseye me-2"></i>{{ __('Where to Publish') }}
    </a>
    @endif
    <a href="{{ route('research.training.index') }}"
       class="list-group-item list-group-item-action {{ $active === 'training' ? 'active' : '' }}">
        <i class="fas fa-user-graduate me-2"></i>{{ __('Training') }}
    </a>
    <a href="{{ route('research.bibliographies') }}"
       class="list-group-item list-group-item-action {{ $active === 'bibliographies' ? 'active' : '' }}">
        <i class="fas fa-book me-2"></i>{{ __('Bibliographies') }}
    </a>
    <a href="{{ route('research.notebooks') }}"
       class="list-group-item list-group-item-action {{ $active === 'notebooks' ? 'active' : '' }}">
        <i class="fas fa-sticky-note me-2"></i>{{ __('Notebooks') }}
    </a>
    @if($atLeast(3))
    <a href="{{ route('research.crossFondsQuery') }}"
       class="list-group-item list-group-item-action {{ $active === 'crossFonds' ? 'active' : '' }}">
        <i class="fas fa-network-wired me-2"></i>{{ __('Cross-fonds Query') }}
    </a>
    <a href="{{ route('research.analytics') }}"
       class="list-group-item list-group-item-action {{ $active === 'analytics' ? 'active' : '' }}">
        <i class="fas fa-chart-line me-2"></i>{{ __('Analytics') }}
    </a>
    @endif
    @if($atLeast(2))
    <a href="{{ route('research.orcid') }}"
       class="list-group-item list-group-item-action {{ $active === 'orcid' ? 'active' : '' }}">
        <i class="fas fa-id-badge me-2 text-success"></i>{{ __('ORCID Link') }}
    </a>
    <a href="{{ route('research.reports') }}"
       class="list-group-item list-group-item-action {{ $active === 'reports' ? 'active' : '' }}">
        <i class="fas fa-file-alt me-2"></i>{{ __('My Reports') }}
    </a>
    <a href="{{ route('research.assessments') }}"
       class="list-group-item list-group-item-action {{ ($active ?? '') === 'assessments' ? 'active' : '' }}">
        <i class="fas fa-clipboard-check me-2"></i>{{ __('Source Assessments') }}
    </a>
    @endif
    <a href="{{ url('/favorites/browse') }}"
       class="list-group-item list-group-item-action {{ $active === 'favorites' ? 'active' : '' }}">
        <i class="fas fa-heart me-2"></i>{{ __('My Favorites') }}
    </a>
</div>

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('Knowledge Platform') }}</span>
    <a href="{{ route('research.savedSearches') }}"
       class="list-group-item list-group-item-action {{ $active === 'savedSearches' ? 'active' : '' }}">
        <i class="fas fa-search me-2"></i>{{ __('Saved Searches') }}
    </a>
    @if($atLeast(2))
    <a href="{{ route('research.annotations') }}"
       class="list-group-item list-group-item-action {{ $active === 'annotations' ? 'active' : '' }}">
        <i class="fas fa-highlighter me-2"></i>{{ __('Annotation Studio') }}
    </a>
    @endif
    @if($atLeast(3))
    <a href="{{ route('research.validationQueue') }}"
       class="list-group-item list-group-item-action {{ $active === 'validationQueue' ? 'active' : '' }}">
        <i class="fas fa-check-double me-2"></i>{{ __('Validation Queue') }}
    </a>
    <a href="{{ route('research.entityResolution') }}"
       class="list-group-item list-group-item-action {{ $active === 'entityResolution' ? 'active' : '' }}">
        <i class="fas fa-object-group me-2"></i>{{ __('Entity Resolution') }}
    </a>
    <a href="{{ route('research.odrlPolicies') }}"
       class="list-group-item list-group-item-action {{ $active === 'odrlPolicies' ? 'active' : '' }}">
        <i class="fas fa-balance-scale me-2"></i>{{ __('ODRL Policies') }}
    </a>
    @endif
    @if($atLeast(2))
    <a href="{{ route('research.documentTemplates') }}"
       class="list-group-item list-group-item-action {{ $active === 'documentTemplates' ? 'active' : '' }}">
        <i class="fas fa-file-alt me-2"></i>{{ __('Document Templates') }}
    </a>
    @endif
</div>

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('Services') }}</span>
    @if($atLeast(2))
    <a href="{{ route('research.reproductions') }}"
       class="list-group-item list-group-item-action {{ $active === 'reproductions' ? 'active' : '' }}">
        <i class="fas fa-copy me-2"></i>{{ __('Reproduction Requests') }}
    </a>
    @endif
    <a href="{{ route('research.book') }}"
       class="list-group-item list-group-item-action {{ $active === 'book' ? 'active' : '' }}">
        <i class="fas fa-calendar-plus me-2"></i>{{ __('Book Reading Room') }}
    </a>
</div>

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('System') }}</span>
    <a href="{{ route('research.notifications') }}"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $active === 'notifications' ? 'active' : '' }}">
        <span><i class="fas fa-bell me-2"></i>{{ __('Notifications') }}</span>
        @if(($unreadNotifications ?? 0) > 0)
        <span class="badge bg-danger rounded-pill">{{ $unreadNotifications }}</span>
        @endif
    </a>
    <a href="{{ route('research.profile') }}"
       class="list-group-item list-group-item-action {{ $active === 'profile' ? 'active' : '' }}">
        <i class="fas fa-user-cog me-2"></i>{{ __('My Profile') }}
    </a>
</div>

@if($isAdmin)
<div class="list-group mb-4">
    <button type="button"
            class="list-group-item list-group-item-action bg-light fw-bold text-uppercase small d-flex justify-content-between align-items-center"
            data-bs-toggle="collapse" data-bs-target="#research-admin-nav"
            aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="research-admin-nav">
        <span><i class="fas fa-user-shield me-2"></i>{{ __('Administration') }}</span>
        <i class="fas fa-chevron-{{ $adminOpen ? 'up' : 'down' }} small"></i>
    </button>
    <div class="collapse {{ $adminOpen ? 'show' : '' }}" id="research-admin-nav">
    <a href="{{ route('research.researchers') }}"
       class="list-group-item list-group-item-action {{ $active === 'researchers' ? 'active' : '' }}">
        <i class="fas fa-user-check me-2"></i>{{ __('Manage Researchers') }}
    </a>
    <a href="{{ route('research.bookings') }}"
       class="list-group-item list-group-item-action {{ $active === 'bookings' ? 'active' : '' }}">
        <i class="fas fa-calendar-alt me-2"></i>{{ __('Manage Bookings') }}
    </a>
    <a href="{{ route('research.rooms') }}"
       class="list-group-item list-group-item-action {{ $active === 'rooms' ? 'active' : '' }}">
        <i class="fas fa-door-open me-2"></i>{{ __('Reading Rooms') }}
    </a>
    <a href="{{ route('research.seats') }}"
       class="list-group-item list-group-item-action {{ $active === 'seats' ? 'active' : '' }}">
        <i class="fas fa-chair me-2"></i>{{ __('Seat Management') }}
    </a>
    <a href="{{ route('research.equipment') }}"
       class="list-group-item list-group-item-action {{ $active === 'equipment' ? 'active' : '' }}">
        <i class="fas fa-tools me-2"></i>{{ __('Equipment') }}
    </a>
    <a href="{{ route('research.retrievalQueue') }}"
       class="list-group-item list-group-item-action {{ $active === 'retrievalQueue' ? 'active' : '' }}">
        <i class="fas fa-dolly me-2"></i>{{ __('Retrieval Queue') }}
    </a>
    <a href="{{ route('research.walkIn') }}"
       class="list-group-item list-group-item-action {{ $active === 'walkIn' ? 'active' : '' }}">
        <i class="fas fa-walking me-2"></i>{{ __('Walk-In Visitors') }}
    </a>
    <a href="{{ route('research.adminTypes') }}"
       class="list-group-item list-group-item-action {{ $active === 'adminTypes' ? 'active' : '' }}">
        <i class="fas fa-tags me-2"></i>{{ __('Researcher Types') }}
    </a>
    <a href="{{ route('research.adminQuotas') }}"
       class="list-group-item list-group-item-action {{ $active === 'adminQuotas' ? 'active' : '' }}">
        <i class="fas fa-hdd me-2"></i>{{ __('Quotas') }}
    </a>
    <a href="{{ route('research.adminStatistics') }}"
       class="list-group-item list-group-item-action {{ $active === 'adminStatistics' ? 'active' : '' }}">
        <i class="fas fa-chart-bar me-2"></i>{{ __('Statistics') }}
    </a>
    <a href="{{ route('research.institutions') }}"
       class="list-group-item list-group-item-action {{ $active === 'institutions' ? 'active' : '' }}">
        <i class="fas fa-university me-2"></i>{{ __('Institutions') }}
    </a>
    <a href="{{ route('research.activities') }}"
       class="list-group-item list-group-item-action {{ $active === 'activities' ? 'active' : '' }}">
        <i class="fas fa-stream me-2"></i>{{ __('Activity Log') }}
    </a>
    </div>{{-- /#research-admin-nav --}}
</div>
@endif

@once
<script>
(function () {
    var sel = document.getElementById('research-experience-level');
    if (!sel || sel.dataset.bound) return;
    sel.dataset.bound = '1';
    var status = document.getElementById('research-experience-level-status');
    function note(msg) { if (status) { status.textContent = msg; if (msg) setTimeout(function () { status.textContent = ''; }, 2000); } }
    sel.addEventListener('change', function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var token = meta ? meta.getAttribute('content') : '{{ csrf_token() }}';
        note('{{ __('Saving...') }}');
        fetch(sel.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ level: sel.value })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (d && d.ok) {
                // The sidebar curation is rendered server-side, so reload to
                // apply the new mode's link set immediately.
                note('{{ __('Saving...') }}');
                window.location.reload();
            } else {
                note('{{ __('Could not save') }}');
            }
        }).catch(function () { note('{{ __('Could not save') }}'); });
    });
})();
</script>
@endonce
