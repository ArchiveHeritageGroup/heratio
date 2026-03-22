{{-- Admin: Manage Bookings - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'bookings'])
@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <h1 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>Manage Bookings</h1>

  {{-- Pending Confirmation --}}
  <div class="card mb-4">
    <div class="card-header text-dark" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-hourglass-half me-2"></i>Pending Confirmation
      @if(count($pendingBookings ?? []) > 0)
        <span class="badge bg-dark">{{ count($pendingBookings) }}</span>
      @endif
    </div>
    <div class="card-body">
      @if(count($pendingBookings ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-hover">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Researcher</th>
                <th>Room</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($pendingBookings as $booking)
                <tr>
                  <td>{{ e($booking->date ?? '') }}</td>
                  <td>{{ e($booking->start_time ?? '') }} - {{ e($booking->end_time ?? '') }}</td>
                  <td>
                    <a href="{{ route('research.viewResearcher', $booking->researcher_id ?? 0) }}">
                      {{ e($booking->researcher_name ?? '-') }}
                    </a>
                  </td>
                  <td>{{ e($booking->room_name ?? '-') }}</td>
                  <td>
                    <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm atom-btn-white" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    <form action="{{ route('research.bookings.confirm', $booking->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-success" title="Confirm">
                        <i class="fas fa-check"></i>
                      </button>
                    </form>
                    <form action="{{ route('research.bookings.cancel', $booking->id) }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Cancel">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No pending bookings.</p>
      @endif
    </div>
  </div>

  {{-- Upcoming Confirmed --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <i class="fas fa-calendar-check me-2"></i>Upcoming Confirmed Bookings
    </div>
    <div class="card-body">
      @if(count($upcomingBookings ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-hover">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Researcher</th>
                <th>Room</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($upcomingBookings as $booking)
                <tr>
                  <td>{{ e($booking->date ?? '') }}</td>
                  <td>{{ e($booking->start_time ?? '') }} - {{ e($booking->end_time ?? '') }}</td>
                  <td>
                    <a href="{{ route('research.viewResearcher', $booking->researcher_id ?? 0) }}">
                      {{ e($booking->researcher_name ?? '-') }}
                    </a>
                  </td>
                  <td>{{ e($booking->room_name ?? '-') }}</td>
                  <td>
                    <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm atom-btn-white" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No upcoming confirmed bookings.</p>
      @endif
    </div>
  </div>
@endsection
