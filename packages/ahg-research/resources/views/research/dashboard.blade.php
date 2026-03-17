{{-- Research Dashboard - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'workspace'])
@endsection

@section('content')
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

  <h1 class="mb-4"><i class="fas fa-tachometer-alt me-2"></i>Research Dashboard</h1>

  {{-- Registration banners for different states --}}
  @guest
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>
      Welcome to the Research Portal. Please
      <a href="{{ route('login') }}" class="alert-link">log in</a> or
      <a href="{{ route('research.publicRegister') }}" class="alert-link">register as a researcher</a>
      to access research tools and book reading rooms.
    </div>
  @endguest

  @auth
    @if(!isset($researcher))
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        You have not yet registered as a researcher.
        <a href="{{ route('researcher.register') }}" class="btn btn-sm btn-warning ms-2">
          <i class="fas fa-user-plus me-1"></i>Register Now
        </a>
      </div>
    @elseif(($researcher->status ?? '') === 'pending')
      <div class="alert alert-info">
        <i class="fas fa-hourglass-half me-2"></i>
        Your researcher registration is <strong>pending approval</strong>. You will be notified once your application has been reviewed.
      </div>
    @elseif(($researcher->status ?? '') === 'expired')
      <div class="alert alert-danger">
        <i class="fas fa-calendar-times me-2"></i>
        Your researcher registration has <strong>expired</strong>.
        <a href="{{ route('research.renewal') }}" class="btn btn-sm btn-danger ms-2">
          <i class="fas fa-redo me-1"></i>Request Renewal
        </a>
      </div>
    @elseif(($researcher->status ?? '') === 'rejected')
      <div class="alert alert-danger">
        <i class="fas fa-times-circle me-2"></i>
        Your researcher registration was <strong>rejected</strong>.
        @if($researcher->rejection_reason ?? false)
          <br>Reason: {{ e($researcher->rejection_reason) }}
        @endif
        <a href="{{ route('researcher.register') }}" class="btn btn-sm btn-outline-danger ms-2">
          <i class="fas fa-redo me-1"></i>Re-apply
        </a>
      </div>
    @elseif(($researcher->status ?? '') === 'approved')
      {{-- Quick Action Buttons --}}
      <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-2">
          <a href="{{ route('research.journal.create') }}" class="btn btn-outline-primary w-100">
            <i class="fas fa-pen me-1"></i>New Journal Entry
          </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <a href="{{ route('research.projects.create') }}" class="btn btn-outline-success w-100">
            <i class="fas fa-project-diagram me-1"></i>New Project
          </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <a href="{{ route('research.book') }}" class="btn btn-outline-info w-100">
            <i class="fas fa-calendar-plus me-1"></i>Book Room
          </a>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <a href="{{ route('research.collections.create') }}" class="btn btn-outline-warning w-100">
            <i class="fas fa-layer-group me-1"></i>New Collection
          </a>
        </div>
      </div>

      {{-- Knowledge Platform Tools --}}
      <h5 class="mb-3">Knowledge Platform Tools</h5>
      <div class="row mb-4">
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card h-100 text-center">
            <div class="card-body">
              <i class="fas fa-search fa-2x text-primary mb-2"></i>
              <h6 class="card-title">Saved Searches</h6>
              <p class="card-text small text-muted">Access your saved search queries</p>
              <a href="{{ route('research.savedSearches') }}" class="btn btn-sm btn-outline-primary">Open</a>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card h-100 text-center">
            <div class="card-body">
              <i class="fas fa-highlighter fa-2x text-warning mb-2"></i>
              <h6 class="card-title">Annotation Studio</h6>
              <p class="card-text small text-muted">View and manage your annotations</p>
              <a href="{{ route('research.annotations') }}" class="btn btn-sm btn-outline-warning">Open</a>
            </div>
          </div>
        </div>
        <div class="col-md-4 col-sm-6 mb-3">
          <div class="card h-100 text-center">
            <div class="card-body">
              <i class="fas fa-quote-right fa-2x text-info mb-2"></i>
              <h6 class="card-title">Citation Generator</h6>
              <p class="card-text small text-muted">Generate citations for records</p>
              <a href="{{ route('research.cite') }}" class="btn btn-sm btn-outline-info">Open</a>
            </div>
          </div>
        </div>
      </div>
    @endif
  @endauth

  {{-- Statistics Row --}}
  <div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card bg-primary text-white">
        <div class="card-body text-center">
          <i class="fas fa-users fa-2x mb-2"></i>
          <h3>{{ $stats['researchers'] ?? 0 }}</h3>
          <small>Registered Researchers</small>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card bg-success text-white">
        <div class="card-body text-center">
          <i class="fas fa-calendar-day fa-2x mb-2"></i>
          <h3>{{ $stats['todayBookings'] ?? 0 }}</h3>
          <small>Today's Bookings</small>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <i class="fas fa-calendar-week fa-2x mb-2"></i>
          <h3>{{ $stats['weekBookings'] ?? 0 }}</h3>
          <small>This Week</small>
        </div>
      </div>
    </div>
    <div class="col-md-3 col-sm-6 mb-3">
      <div class="card bg-warning text-dark">
        <div class="card-body text-center">
          <i class="fas fa-hourglass-half fa-2x mb-2"></i>
          <h3>{{ $stats['pendingRequests'] ?? 0 }}</h3>
          <small>Pending Requests</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Admin Sections --}}
  @if($isAdmin ?? false)
    {{-- Pending Approvals --}}
    <div class="card mb-4">
      <div class="card-header bg-warning text-dark">
        <i class="fas fa-user-clock me-2"></i>Pending Approvals
      </div>
      <div class="card-body">
        @if(count($pendingApprovals ?? []) > 0)
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Institution</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($pendingApprovals as $applicant)
                  <tr>
                    <td>{{ e($applicant->first_name) }} {{ e($applicant->last_name) }}</td>
                    <td>{{ e($applicant->email) }}</td>
                    <td>{{ e($applicant->institution ?? '-') }}</td>
                    <td>{{ $applicant->created_at ? $applicant->created_at->format('Y-m-d') : '-' }}</td>
                    <td>
                      <a href="{{ route('research.viewResearcher', $applicant->id) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i> Review
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="text-muted mb-0">No pending approvals.</p>
        @endif
      </div>
    </div>

    {{-- Today's Schedule --}}
    <div class="card mb-4">
      <div class="card-header">
        <i class="fas fa-clock me-2"></i>Today's Schedule
      </div>
      <div class="card-body">
        @if(count($todaySchedule ?? []) > 0)
          <div class="table-responsive">
            <table class="table table-sm table-hover">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Researcher</th>
                  <th>Room</th>
                  <th>Purpose</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @foreach($todaySchedule as $booking)
                  <tr>
                    <td>{{ e($booking->start_time) }} - {{ e($booking->end_time) }}</td>
                    <td>{{ e($booking->researcher_name ?? '-') }}</td>
                    <td>{{ e($booking->room_name ?? '-') }}</td>
                    <td>{{ e(\Illuminate\Support\Str::limit($booking->purpose ?? '', 50)) }}</td>
                    <td>
                      <span class="badge bg-{{ $booking->status === 'confirmed' ? 'success' : ($booking->status === 'checked_in' ? 'primary' : 'secondary') }}">
                        {{ e(ucfirst($booking->status ?? 'unknown')) }}
                      </span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @else
          <p class="text-muted mb-0">No bookings scheduled for today.</p>
        @endif
      </div>
    </div>
  @endif

  {{-- Recent Activity --}}
  <div class="card mb-4">
    <div class="card-header">
      <i class="fas fa-stream me-2"></i>Recent Activity
    </div>
    <div class="card-body">
      @if(count($recentActivity ?? []) > 0)
        <ul class="list-group list-group-flush">
          @foreach($recentActivity as $activity)
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>
                <i class="fas fa-{{ $activity->icon ?? 'circle' }} me-2 text-muted"></i>
                {{ e($activity->description) }}
              </span>
              <small class="text-muted">{{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }}</small>
            </li>
          @endforeach
        </ul>
      @else
        <p class="text-muted mb-0">No recent activity.</p>
      @endif
    </div>
  </div>

  {{-- Recent Journal Entries --}}
  @auth
    @if(($researcher->status ?? '') === 'approved')
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
                    <strong>
                      <a href="{{ route('research.journal.show', $entry->id) }}">{{ e($entry->title) }}</a>
                    </strong>
                    <small class="text-muted">{{ $entry->created_at ? $entry->created_at->format('Y-m-d') : '' }}</small>
                  </div>
                  <small class="text-muted">{{ e(\Illuminate\Support\Str::limit($entry->content ?? '', 100)) }}</small>
                </li>
              @endforeach
            </ul>
          @else
            <p class="text-muted mb-0">No journal entries yet.
              <a href="{{ route('research.journal.create') }}">Create your first entry</a>.
            </p>
          @endif
        </div>
      </div>
    @endif
  @endauth
@endsection
