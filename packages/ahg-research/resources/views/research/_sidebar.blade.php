{{-- Research Plugin Sidebar Navigation - Migrated from AtoM: _researchSidebar.php --}}
@php $active = $sidebarActive ?? ''; $isAdmin = Auth::check() && \AhgCore\Services\AclService::canAdmin(Auth::id()); @endphp

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('Research') }}</span>
    <a href="{{ route('research.dashboard') }}"
       class="list-group-item list-group-item-action {{ $active === 'workspace' ? 'active' : '' }}">
        <i class="fas fa-tachometer-alt me-2"></i>{{ __('My Workspace') }}
    </a>
    <a href="{{ route('research.projects') }}"
       class="list-group-item list-group-item-action {{ $active === 'projects' ? 'active' : '' }}">
        <i class="fas fa-project-diagram me-2"></i>{{ __('My Projects') }}
    </a>
    <a href="{{ route('research.workspaces') }}"
       class="list-group-item list-group-item-action {{ $active === 'workspaces' ? 'active' : '' }}">
        <i class="fas fa-users me-2"></i>{{ __('Team Workspaces') }}
    </a>
    <a href="{{ route('research.collections') }}"
       class="list-group-item list-group-item-action {{ $active === 'collections' ? 'active' : '' }}">
        <i class="fas fa-layer-group me-2"></i>{{ __('Evidence Sets') }}
    </a>
    <a href="{{ route('research.journal') }}"
       class="list-group-item list-group-item-action {{ $active === 'journal' ? 'active' : '' }}">
        <i class="fas fa-journal-whills me-2"></i>{{ __('Research Journal') }}
    </a>
    <a href="{{ route('research.bibliographies') }}"
       class="list-group-item list-group-item-action {{ $active === 'bibliographies' ? 'active' : '' }}">
        <i class="fas fa-book me-2"></i>{{ __('Bibliographies') }}
    </a>
    <a href="{{ route('research.reports') }}"
       class="list-group-item list-group-item-action {{ $active === 'reports' ? 'active' : '' }}">
        <i class="fas fa-file-alt me-2"></i>{{ __('My Reports') }}
    </a>
    <a href="{{ route('research.assessments') }}"
       class="list-group-item list-group-item-action {{ ($active ?? '') === 'assessments' ? 'active' : '' }}">
        <i class="fas fa-clipboard-check me-2"></i>{{ __('Source Assessments') }}
    </a>
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
    <a href="{{ route('research.annotations') }}"
       class="list-group-item list-group-item-action {{ $active === 'annotations' ? 'active' : '' }}">
        <i class="fas fa-highlighter me-2"></i>{{ __('Annotation Studio') }}
    </a>
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
    <a href="{{ route('research.documentTemplates') }}"
       class="list-group-item list-group-item-action {{ $active === 'documentTemplates' ? 'active' : '' }}">
        <i class="fas fa-file-alt me-2"></i>{{ __('Document Templates') }}
    </a>
</div>

<div class="list-group mb-4">
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('Services') }}</span>
    <a href="{{ route('research.reproductions') }}"
       class="list-group-item list-group-item-action {{ $active === 'reproductions' ? 'active' : '' }}">
        <i class="fas fa-copy me-2"></i>{{ __('Reproduction Requests') }}
    </a>
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
    <span class="list-group-item bg-light fw-bold text-uppercase small">{{ __('Administration') }}</span>
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
</div>
@endif
