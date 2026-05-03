{{-- Researcher Profile - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'profile'])
@endsection

@section('content')
  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
      <li class="breadcrumb-item active">My Profile</li>
    </ol>
  </nav>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user-cog me-2"></i>{{ __('My Profile') }}</h1>
    <div>
      {{-- Status Badge --}}
      @php
        $statusColors = ['approved' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'expired' => 'secondary', 'rejected' => 'danger'];
        $statusColor = $statusColors[$researcher->status ?? ''] ?? 'secondary';
      @endphp
      <span class="badge bg-{{ $statusColor }} fs-6">{{ ucfirst(e($researcher->status ?? 'unknown')) }}</span>
    </div>
  </div>

  {{-- Expiration Warning --}}
  @if(($researcher->status ?? '') === 'approved' && ($researcher->expires_at ?? false))
    @php
      $expiresAt = \Carbon\Carbon::parse($researcher->expires_at);
      $daysLeft = now()->diffInDays($expiresAt, false);
    @endphp
    @if($daysLeft <= 30 && $daysLeft > 0)
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Your researcher registration expires in <strong>{{ $daysLeft }} days</strong> ({{ $expiresAt->format('Y-m-d') }}).
        <a href="{{ route('research.renewal') }}" class="btn btn-sm atom-btn-white ms-2">Request Renewal</a>
      </div>
    @elseif($daysLeft <= 0)
      <div class="alert alert-danger">
        <i class="fas fa-calendar-times me-2"></i>
        Your researcher registration has <strong>expired</strong>.
        <a href="{{ route('research.renewal') }}" class="btn atom-btn-outline-danger btn-sm ms-2">Request Renewal</a>
      </div>
    @endif
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ e($error) }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row">
  <div class="col-md-8">
  <form action="{{ route('research.profile.update') }}" method="POST">
    @csrf
    @method('PUT')

    {{-- Personal Information --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-user me-2"></i>{{ __('Personal Information') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-2 mb-3">
            <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="title" id="title" class="form-select">
              <option value="">-- Select --</option>
              @foreach(['Mr', 'Mrs', 'Ms', 'Dr', 'Prof'] as $t)
                <option value="{{ $t }}" {{ old('title', $researcher->title ?? '') === $t ? 'selected' : '' }}>{{ $t }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-5 mb-3">
            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', $researcher->first_name ?? '') }}" required>
          </div>
          <div class="col-md-5 mb-3">
            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', $researcher->last_name ?? '') }}" required>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Email <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="email" class="form-control" value="{{ e($researcher->email ?? '') }}" disabled>
            <small class="text-muted">{{ __('Contact an administrator to change your email.') }}</small>
          </div>
          <div class="col-md-6 mb-3">
            <label for="phone" class="form-label">Phone <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $researcher->phone ?? '') }}">
          </div>
        </div>
      </div>
    </div>

    {{-- Identification (read-only) --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-id-card me-2"></i>{{ __('Identification') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">ID Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            @if($themeData['isAdmin'] ?? false)
              <select name="id_type" class="form-select">
                <option value="">-- Select --</option>
                {{-- Issue #59 Tier 2 — culture-aware dropdown via the COALESCE helper. --}}
                @foreach(\AhgCore\Services\AhgSettingsService::getDropdownChoicesWithAttributes('id_type') as $idt)
                  <option value="{{ $idt->code }}" {{ ($researcher->id_type ?? '') === $idt->code ? 'selected' : '' }}>{{ $idt->label }}</option>
                @endforeach
              </select>
            @else
              <input type="text" class="form-control" value="{{ e(ucfirst(str_replace('_', ' ', $researcher->id_type ?? ''))) }}" disabled>
            @endif
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label">ID Number <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            @if($themeData['isAdmin'] ?? false)
              <input type="text" name="id_number" class="form-control" value="{{ old('id_number', $researcher->id_number ?? '') }}">
            @else
              <input type="text" class="form-control" value="{{ e($researcher->id_number ?? '') }}" disabled>
            @endif
          </div>
          <div class="col-md-4 mb-3">
            <label for="student_id" class="form-label">Student ID <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="student_id" id="student_id" class="form-control" value="{{ old('student_id', $researcher->student_id ?? '') }}">
          </div>
        </div>
        @if(!($themeData['isAdmin'] ?? false))
          <small class="text-muted">{{ __('ID type and number cannot be changed. Contact an administrator if corrections are needed.') }}</small>
        @endif
      </div>
    </div>

    {{-- Affiliation --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-university me-2"></i>{{ __('Affiliation') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="affiliation_type" class="form-label">Affiliation Type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select name="affiliation_type" id="affiliation_type" class="form-select">
              <option value="">-- Select --</option>
              @foreach(['academic', 'government', 'independent', 'corporate', 'student', 'other'] as $type)
                <option value="{{ $type }}" {{ old('affiliation_type', $researcher->affiliation_type ?? '') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="institution" class="form-label">Institution <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="institution" id="institution" class="form-control" value="{{ old('institution', $researcher->institution ?? '') }}">
          </div>
          <div class="col-md-4 mb-3">
            <label for="department" class="form-label">Department <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="department" id="department" class="form-control" value="{{ old('department', $researcher->department ?? '') }}">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="position" class="form-label">Position <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="position" id="position" class="form-control" value="{{ old('position', $researcher->position ?? '') }}">
          </div>
          <div class="col-md-6 mb-3">
            <label for="orcid_id" class="form-label">ORCID iD <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <input type="text" name="orcid_id" id="orcid_id" class="form-control" value="{{ old('orcid_id', $researcher->orcid_id ?? '') }}" placeholder="0000-0000-0000-0000">
          </div>
        </div>
      </div>
    </div>

    {{-- Research --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-microscope me-2"></i>{{ __('Research') }}</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="research_interests" class="form-label">Research Interests <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <textarea name="research_interests" id="research_interests" class="form-control" rows="4">{{ old('research_interests', $researcher->research_interests ?? '') }}</textarea>
        </div>
        <div class="mb-3">
          <label for="current_project" class="form-label">Current Project <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <textarea name="current_project" id="current_project" class="form-control" rows="4">{{ old('current_project', $researcher->current_project ?? '') }}</textarea>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <a href="{{ route('research.dashboard') }}" class="btn atom-btn-white me-2">Cancel</a>
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-save me-1"></i>{{ __('Update Profile') }}
      </button>
    </div>
  </form>
  </div>

  <div class="col-md-4">
    {{-- Recent Bookings --}}
    <div class="card mb-3">
      <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="fas fa-calendar me-2"></i>{{ __('Recent Bookings') }}</h6></div>
      @if(!empty($recentBookings))
        <ul class="list-group list-group-flush">
          @foreach(array_slice((array)$recentBookings, 0, 5) as $b)
            <li class="list-group-item d-flex justify-content-between">
              <span>{{ date('M j', strtotime($b->booking_date ?? $b->date ?? '')) }} - {{ e($b->room_name ?? '') }}</span>
              <span class="badge bg-{{ ($b->status ?? '') === 'confirmed' ? 'success' : (($b->status ?? '') === 'pending' ? 'warning' : 'secondary') }}">{{ $b->status ?? '' }}</span>
            </li>
          @endforeach
        </ul>
      @else
        <div class="card-body text-muted small">No bookings yet</div>
      @endif
    </div>

    {{-- Evidence Sets --}}
    <div class="card mb-3">
      <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Evidence Sets') }}</h6></div>
      @if(!empty($recentCollections))
        <ul class="list-group list-group-flush">
          @foreach($recentCollections as $c)
            <li class="list-group-item d-flex justify-content-between">
              <a href="{{ route('research.viewCollection') }}?id={{ $c->id }}">{{ e($c->name) }}</a>
              <span class="badge bg-secondary">{{ $c->item_count ?? 0 }}</span>
            </li>
          @endforeach
        </ul>
      @else
        <div class="card-body text-muted small">No evidence sets</div>
      @endif
    </div>

    {{-- API Access --}}
    @if(($researcher->status ?? '') === 'approved')
    <div class="card mb-3">
      <div class="card-header bg-dark text-white"><h6 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('API Access') }}</h6></div>
      <div class="card-body">
        <p class="small text-muted mb-2">Access your research data programmatically via REST API.</p>
        <a href="{{ url('/research/apiKeys') }}" class="btn btn-sm btn-outline-dark w-100"><i class="fas fa-key me-1"></i>{{ __('Manage API Keys') }}</a>
      </div>
    </div>
    @endif

    {{-- Quick Links --}}
    <div class="card">
      <div class="card-header bg-secondary text-white"><h6 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Links') }}</h6></div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item"><a href="{{ route('research.projects') }}"><i class="fas fa-project-diagram me-2"></i>{{ __('My Projects') }}</a></li>
        <li class="list-group-item"><a href="{{ route('research.bibliographies') }}"><i class="fas fa-book me-2"></i>{{ __('Bibliographies') }}</a></li>
        <li class="list-group-item"><a href="{{ route('research.workspaces') }}"><i class="fas fa-users-cog me-2"></i>{{ __('Workspaces') }}</a></li>
        <li class="list-group-item"><a href="{{ route('research.reproductions') }}"><i class="fas fa-copy me-2"></i>{{ __('Reproduction Requests') }}</a></li>
      </ul>
    </div>
  </div>
  </div>
@endsection
