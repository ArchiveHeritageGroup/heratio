{{-- Admin: View Researcher Detail - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'researchers'])
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-user me-2"></i>{{ e($researcher->title ?? '') }} {{ e($researcher->first_name ?? '') }} {{ e($researcher->last_name ?? '') }}</h1>
    @php
      $sc = ['approved' => 'success', 'pending' => 'warning', 'suspended' => 'danger', 'expired' => 'secondary', 'rejected' => 'danger'];
    @endphp
    <span class="badge bg-{{ $sc[$researcher->status ?? ''] ?? 'secondary' }} fs-6">{{ ucfirst(e($researcher->status ?? 'unknown')) }}</span>
  </div>

  {{-- Personal Info --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-user me-2"></i>Personal Information</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <dl class="row mb-0">
            <dt class="col-sm-4">Name</dt>
            <dd class="col-sm-8">{{ e($researcher->title ?? '') }} {{ e($researcher->first_name ?? '') }} {{ e($researcher->last_name ?? '') }}</dd>
            <dt class="col-sm-4">Email</dt>
            <dd class="col-sm-8">{{ e($researcher->email ?? '') }}</dd>
            <dt class="col-sm-4">Phone</dt>
            <dd class="col-sm-8">{{ e($researcher->phone ?? '-') }}</dd>
          </dl>
        </div>
        <div class="col-md-6">
          <dl class="row mb-0">
            <dt class="col-sm-4">ID Type</dt>
            <dd class="col-sm-8">{{ e(ucfirst(str_replace('_', ' ', $researcher->id_type ?? '-'))) }}</dd>
            <dt class="col-sm-4">ID Number</dt>
            <dd class="col-sm-8">{{ e($researcher->id_number ?? '-') }}</dd>
            <dt class="col-sm-4">Student ID</dt>
            <dd class="col-sm-8">{{ e($researcher->student_id ?? '-') }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  {{-- Affiliation --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-university me-2"></i>Affiliation</div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Affiliation Type</dt>
        <dd class="col-sm-9">{{ e(ucfirst($researcher->affiliation_type ?? '-')) }}</dd>
        <dt class="col-sm-3">Institution</dt>
        <dd class="col-sm-9">{{ e($researcher->institution ?? '-') }}</dd>
        <dt class="col-sm-3">Department</dt>
        <dd class="col-sm-9">{{ e($researcher->department ?? '-') }}</dd>
        <dt class="col-sm-3">Position</dt>
        <dd class="col-sm-9">{{ e($researcher->position ?? '-') }}</dd>
        <dt class="col-sm-3">ORCID iD</dt>
        <dd class="col-sm-9">{{ e($researcher->orcid_id ?? '-') }}</dd>
      </dl>
    </div>
  </div>

  {{-- Research Interests --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-microscope me-2"></i>Research Interests</div>
    <div class="card-body">
      <p>{{ e($researcher->research_interests ?? 'Not specified') }}</p>
      @if($researcher->current_project ?? false)
        <hr>
        <h6>Current Project</h6>
        <p>{{ e($researcher->current_project) }}</p>
      @endif
    </div>
  </div>

  {{-- Booking History --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-calendar-alt me-2"></i>Booking History</div>
    <div class="card-body">
      @if(count($bookings ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-hover">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th>Date</th>
                <th>Time</th>
                <th>Room</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($bookings as $booking)
                <tr>
                  <td>{{ e($booking->date ?? '') }}</td>
                  <td>{{ e($booking->start_time ?? '') }} - {{ e($booking->end_time ?? '') }}</td>
                  <td>{{ e($booking->room_name ?? '') }}</td>
                  <td>
                    <span class="badge bg-{{ ($booking->status ?? '') === 'confirmed' ? 'success' : (($booking->status ?? '') === 'cancelled' ? 'danger' : 'secondary') }}">
                      {{ ucfirst(e($booking->status ?? '')) }}
                    </span>
                  </td>
                  <td>
                    <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm atom-btn-white">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No booking history.</p>
      @endif
    </div>
  </div>

  {{-- Admin Actions --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cogs me-2"></i>Admin Actions</div>
    <div class="card-body d-flex flex-wrap gap-2">
      @if(($researcher->status ?? '') === 'pending')
        <form action="{{ route('research.researchers.approve', $researcher->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-check me-1"></i>Approve
          </button>
        </form>
      @endif

      @if(in_array($researcher->status ?? '', ['approved', 'pending']))
        <button type="button" class="btn atom-btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
          <i class="fas fa-times me-1"></i>Reject
        </button>
      @endif

      @if(($researcher->status ?? '') === 'approved')
        <form action="{{ route('research.researchers.suspend', $researcher->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-white">
            <i class="fas fa-ban me-1"></i>Suspend
          </button>
        </form>
      @endif

      @if(in_array($researcher->status ?? '', ['suspended', 'rejected', 'expired']))
        <form action="{{ route('research.researchers.approve', $researcher->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-redo me-1"></i>Re-approve
          </button>
        </form>
      @endif

      <a href="{{ route('research.researchers') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i>Back to List
      </a>
    </div>
  </div>

  {{-- Reject Modal --}}
  <div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{ route('research.researchers.reject', $researcher->id) }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title">Reject Researcher</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="rejection_reason" class="form-label">Reason for Rejection <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn atom-btn-outline-danger">
              <i class="fas fa-times me-1"></i>Reject
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
