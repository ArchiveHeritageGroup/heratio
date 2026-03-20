{{-- Research Dashboard - Cloned from AtoM ahgResearchPlugin --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'workspace'])
@endsection

@section('title', 'Research Services')

@section('content')
<h1><i class="fas fa-book-reader text-primary me-2"></i>Research Services</h1>

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  {{-- Registration Banners --}}
  @guest
    <div class="alert alert-info mb-4">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h4><i class="fas fa-user-plus me-2"></i>Register as a Researcher</h4>
          <p class="mb-0">Create an account to book reading room visits, request materials, and save your research.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
          <a href="{{ route('research.publicRegister') }}" class="btn btn-primary btn-lg">
            <i class="fas fa-user-plus me-2"></i>Register Now
          </a>
          <div class="mt-2">
            <small><a href="{{ route('login') }}">Already have an account? Login</a></small>
          </div>
        </div>
      </div>
    </div>
  @endguest

  @auth
    @if(!isset($researcher) || !$researcher)
      <div class="alert alert-warning mb-4">
        <div class="row align-items-center">
          <div class="col-md-8">
            <h4><i class="fas fa-clipboard-list me-2"></i>Complete Your Researcher Profile</h4>
            <p class="mb-0">You need to complete your researcher registration to book reading room visits.</p>
          </div>
          <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="{{ route('researcher.register') }}" class="btn btn-warning"><i class="fas fa-edit me-2"></i>Complete Registration</a>
          </div>
        </div>
      </div>
    @elseif(($researcher->status ?? '') === 'pending')
      <div class="alert alert-info mb-4">
        <h4><i class="fas fa-clock me-2"></i>Registration Pending</h4>
        <p class="mb-0">Your researcher registration is being reviewed. You will be notified once approved.</p>
      </div>
    @elseif(($researcher->status ?? '') === 'expired')
      <div class="alert alert-danger mb-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h4><i class="fas fa-exclamation-circle me-2"></i>Registration Expired</h4>
            <p class="mb-0">Your researcher registration has expired. Please request a renewal to continue.</p>
          </div>
          <a href="{{ route('research.renewal') }}" class="btn btn-danger"><i class="fas fa-sync-alt me-1"></i>Request Renewal</a>
        </div>
      </div>
    @elseif(($researcher->status ?? '') === 'rejected')
      <div class="alert alert-danger mb-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h4><i class="fas fa-times-circle me-2"></i>Registration Rejected</h4>
            <p class="mb-0">
              Your registration was not approved.
              @if($researcher->rejection_reason ?? false)
                <br><small>Reason: {{ e($researcher->rejection_reason) }}</small>
              @endif
            </p>
          </div>
          <a href="{{ route('researcher.register') }}" class="btn btn-primary"><i class="fas fa-redo me-1"></i>Re-apply</a>
        </div>
      </div>
    @elseif(($researcher->status ?? '') === 'approved')
      {{-- Welcome Card --}}
      <div class="card bg-light mb-4">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-md-8">
              <h5 class="mb-1">Welcome back, {{ e($researcher->first_name ?? '') }}!</h5>
              <p class="text-muted mb-0">{{ e($researcher->institution ?? 'Independent Researcher') }}</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
              <a href="{{ route('research.book') }}" class="btn btn-primary"><i class="fas fa-calendar-plus me-2"></i>Book Visit</a>
              <a href="{{ route('research.profile') }}" class="btn btn-outline-secondary"><i class="fas fa-user me-2"></i>My Profile</a>
            </div>
          </div>
        </div>
      </div>
    @endif
  @endauth

  {{-- Quick Action Buttons (approved researchers) --}}
  @auth
    @if(isset($researcher) && ($researcher->status ?? '') === 'approved')
      <div class="row mb-4">
        <div class="col">
          <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('research.book') }}" class="btn btn-outline-primary"><i class="fas fa-calendar-plus me-1"></i>Book Visit</a>
            <a href="{{ route('research.journal.create') }}" class="btn btn-outline-success"><i class="fas fa-pen-fancy me-1"></i>New Journal Entry</a>
            <a href="{{ route('research.reports') }}" class="btn btn-outline-info"><i class="fas fa-file-alt me-1"></i>New Report</a>
            <a href="{{ route('research.annotations') }}" class="btn btn-outline-warning"><i class="fas fa-sticky-note me-1"></i>My Notes</a>
          </div>
        </div>
      </div>

      {{-- Knowledge Platform --}}
      <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <span><i class="fas fa-brain me-2"></i>Knowledge Platform</span>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-md-3 col-6">
              <a href="{{ route('research.annotations') }}" class="btn btn-outline-primary w-100 py-2 text-start">
                <i class="fas fa-highlighter me-1"></i> Annotations
              </a>
            </div>
            <div class="col-md-3 col-6">
              <a href="{{ route('research.savedSearches') }}" class="btn btn-outline-info w-100 py-2 text-start">
                <i class="fas fa-search me-1"></i> Saved Searches
              </a>
            </div>
            <div class="col-md-3 col-6">
              <a href="{{ route('research.validationQueue') }}" class="btn btn-outline-success w-100 py-2 text-start">
                <i class="fas fa-check-double me-1"></i> Validation Queue
              </a>
            </div>
            <div class="col-md-3 col-6">
              <a href="{{ route('research.entityResolution') }}" class="btn btn-outline-warning w-100 py-2 text-start">
                <i class="fas fa-object-group me-1"></i> Entity Resolution
              </a>
            </div>
            <div class="col-md-3 col-6">
              <a href="{{ route('research.odrlPolicies') }}" class="btn btn-outline-secondary w-100 py-2 text-start">
                <i class="fas fa-balance-scale me-1"></i> ODRL Policies
              </a>
            </div>
            <div class="col-md-3 col-6">
              <a href="{{ route('research.documentTemplates') }}" class="btn btn-outline-dark w-100 py-2 text-start">
                <i class="fas fa-file-alt me-1"></i> Doc Templates
              </a>
            </div>
          </div>
          <p class="text-muted small mt-2 mb-0"><i class="fas fa-info-circle me-1"></i>Project-specific tools (Knowledge Graph, Timeline, Map, AI Extraction) are available from each project page.</p>
        </div>
      </div>
    @endif
  @endauth

  {{-- Statistics --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <h2 class="text-primary">{{ number_format($stats['researchers'] ?? 0) }}</h2>
          <p class="mb-0">Registered Researchers</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <h2 class="text-success">{{ number_format($stats['todayBookings'] ?? $stats['bookings_today'] ?? 0) }}</h2>
          <p class="mb-0">Today's Bookings</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <h2 class="text-info">{{ number_format($stats['weekBookings'] ?? $stats['bookings_week'] ?? 0) }}</h2>
          <p class="mb-0">This Week</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center h-100">
        <div class="card-body">
          <h2 class="text-warning">{{ number_format($stats['pendingRequests'] ?? $stats['pending_requests'] ?? 0) }}</h2>
          <p class="mb-0">Pending Requests</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Pending Approvals (Admin) --}}
  @if($isAdmin ?? false)
    @if(count($pendingApprovals ?? $pendingResearchers ?? []) > 0)
      @php $pending = $pendingApprovals ?? $pendingResearchers ?? collect(); @endphp
      <div class="card mb-4">
        <div class="card-header bg-warning">
          <i class="fas fa-user-clock me-2"></i>Pending Approvals
          <span class="badge bg-dark float-end">{{ count($pending) }}</span>
        </div>
        <ul class="list-group list-group-flush">
          @foreach(collect($pending)->take(5) as $applicant)
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong>{{ e($applicant->first_name ?? '') }} {{ e($applicant->last_name ?? '') }}</strong><br>
                <small class="text-muted">{{ e($applicant->institution ?? 'Independent') }}</small>
              </div>
              <a href="{{ route('research.viewResearcher', $applicant->id) }}" class="btn btn-sm btn-outline-primary">Review</a>
            </li>
          @endforeach
        </ul>
        @if(count($pending) > 5)
          <div class="card-footer text-center">
            <a href="{{ route('research.researchers', ['status' => 'pending']) }}">View all pending</a>
          </div>
        @endif
      </div>
    @endif
  @endif

  {{-- Today's Schedule --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-calendar-day me-2"></i>Today's Schedule</span>
      @auth
        <a href="{{ route('research.bookings') }}" class="btn btn-sm btn-outline-primary">View All</a>
      @endauth
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Time</th>
            <th>Researcher</th>
            <th>Room</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($todaySchedule ?? $todayBookings ?? [] as $booking)
            <tr>
              <td>{{ substr($booking->start_time ?? '', 0, 5) }} - {{ substr($booking->end_time ?? '', 0, 5) }}</td>
              <td>{{ e(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? $booking->researcher_name ?? '')) }}</td>
              <td>{{ e($booking->room_name ?? '-') }}</td>
              <td>
                <span class="badge bg-{{ ($booking->status ?? '') === 'confirmed' ? 'success' : 'warning' }}">
                  {{ ucfirst($booking->status ?? 'unknown') }}
                </span>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No bookings scheduled for today</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Recent Activity --}}
  @auth
    @if(count($recentActivity ?? []) > 0)
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-stream me-2"></i>Recent Activity</span>
        </div>
        <ul class="list-group list-group-flush">
          @foreach(collect($recentActivity)->take(5) as $activity)
            <li class="list-group-item py-2">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <span class="badge bg-light text-dark me-1">{{ ucfirst(str_replace('_', ' ', $activity->activity_type ?? '')) }}</span>
                  <span class="small">{{ e($activity->entity_title ?? $activity->description ?? '') }}</span>
                </div>
                <small class="text-muted">{{ isset($activity->created_at) ? \Carbon\Carbon::parse($activity->created_at)->format('M j, H:i') : '' }}</small>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  @endauth

  {{-- Recent Notes --}}
  @auth
    @php $recentNotes = $enhancedData['recent_notes'] ?? []; @endphp
    @if(!empty($recentNotes))
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-sticky-note me-2 text-warning"></i>Recent Notes</span>
          <a href="{{ route('research.annotations') }}" class="btn btn-sm btn-outline-warning">All Notes</a>
        </div>
        <ul class="list-group list-group-flush">
          @foreach(collect($recentNotes)->take(5) as $note)
            <li class="list-group-item py-2">
              <a href="{{ route('research.annotations') }}#note-{{ $note->id ?? '' }}" class="text-decoration-none">
                <strong>{{ e($note->title ?? 'Untitled Note') }}</strong>
              </a>
              <br><small class="text-muted">{{ isset($note->created_at) ? \Carbon\Carbon::parse($note->created_at)->format('M j, Y H:i') : '' }}</small>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  @endauth

  {{-- Saved Search Alerts --}}
  @auth
    @php $searchAlerts = $enhancedData['search_alerts'] ?? []; @endphp
    @if(!empty($searchAlerts))
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-search me-2 text-info"></i>Saved Search Alerts</span>
          <a href="{{ route('research.savedSearches') }}" class="btn btn-sm btn-outline-info">View All</a>
        </div>
        <ul class="list-group list-group-flush">
          @foreach($searchAlerts as $alert)
            <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
              <span>{{ e($alert->name ?? '') }}</span>
              <span class="badge bg-info rounded-pill">{{ (int) ($alert->new_results_count ?? 0) }} new</span>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  @endauth

  {{-- Pending Invitations --}}
  @auth
    @php $pendingInvites = $enhancedData['pending_invitations'] ?? []; @endphp
    @if(!empty($pendingInvites))
      <div class="card mb-4">
        <div class="card-header bg-info text-white">
          <i class="fas fa-envelope me-2"></i>Pending Invitations
          <span class="badge bg-white text-info float-end">{{ count($pendingInvites) }}</span>
        </div>
        <ul class="list-group list-group-flush">
          @foreach($pendingInvites as $invite)
            <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
              <span>{{ e($invite->project_title ?? '') }}</span>
              <a href="{{ route('research.viewProject', ['id' => $invite->project_id ?? 0]) }}" class="btn btn-sm btn-outline-primary">View</a>
            </li>
          @endforeach
        </ul>
      </div>
    @endif
  @endauth

  {{-- Recent Journal Entries (Heratio enhancement) --}}
  @auth
    @if(isset($researcher) && ($researcher->status ?? '') === 'approved')
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><i class="fas fa-journal-whills me-2"></i>Recent Journal Entries</span>
          <a href="{{ route('research.journal') }}" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body">
          @if(count($recentJournalEntries ?? []) > 0)
            <ul class="list-group list-group-flush">
              @foreach($recentJournalEntries as $entry)
                <li class="list-group-item">
                  <div class="d-flex justify-content-between">
                    <strong><a href="{{ route('research.journal.show', $entry->id) }}">{{ e($entry->title) }}</a></strong>
                    <small class="text-muted">{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d') : '' }}</small>
                  </div>
                  <small class="text-muted">{{ e(\Illuminate\Support\Str::limit($entry->content ?? '', 100)) }}</small>
                </li>
              @endforeach
            </ul>
          @else
            <p class="text-muted mb-0">No journal entries yet. <a href="{{ route('research.journal.create') }}">Create your first entry</a>.</p>
          @endif
        </div>
      </div>
    @endif
  @endauth
@endsection
