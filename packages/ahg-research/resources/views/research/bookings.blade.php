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
  @if(count($pendingBookings ?? []) > 0)
  <div class="card mb-4">
    <div class="card-header bg-warning text-dark">
      <i class="fas fa-clock me-2"></i>Pending Confirmation
      <span class="badge bg-dark float-end">{{ count($pendingBookings) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Time') }}</th>
            <th>{{ __('Researcher') }}</th>
            <th>{{ __('Room') }}</th>
            <th width="150"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($pendingBookings as $booking)
            <tr>
              <td>{{ e($booking->date ?? $booking->booking_date ?? '') }}</td>
              <td>{{ substr($booking->start_time ?? '', 0, 5) }} - {{ substr($booking->end_time ?? '', 0, 5) }}</td>
              <td>
                <a href="{{ route('research.viewResearcher', $booking->researcher_id ?? 0) }}">
                  <strong>{{ e($booking->researcher_name ?? '-') }}</strong>
                </a><br>
                <small class="text-muted">{{ e($booking->email ?? '') }}</small>
              </td>
              <td>{{ e($booking->room_name ?? '-') }}</td>
              <td>
                <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm btn-primary">
                  <i class="fas fa-eye me-1"></i>Review
                </a>
                <form action="{{ route('research.bookings.confirm', $booking->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Confirm') }}">
                    <i class="fas fa-check"></i>
                  </button>
                </form>
                <form action="{{ route('research.bookings.cancel', $booking->id) }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Cancel') }}">
                    <i class="fas fa-times"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif

  {{-- Upcoming Confirmed --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-calendar-check me-2"></i>Upcoming Confirmed Bookings</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Time') }}</th>
            <th>{{ __('Researcher') }}</th>
            <th>{{ __('Room') }}</th>
            <th>{{ __('Check-in') }}</th>
            <th width="180"></th>
          </tr>
        </thead>
        <tbody>
          @if(count($upcomingBookings ?? []) === 0)
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No upcoming bookings</td>
            </tr>
          @else
            @foreach($upcomingBookings as $booking)
              @php $isToday = ($booking->booking_date ?? $booking->date ?? '') === date('Y-m-d'); @endphp
              <tr class="{{ $isToday ? 'table-info' : '' }}">
                <td>
                  {{ e($booking->date ?? $booking->booking_date ?? '') }}
                  @if($isToday)
                    <span class="badge bg-info">Today</span>
                  @endif
                </td>
                <td>{{ substr($booking->start_time ?? '', 0, 5) }} - {{ substr($booking->end_time ?? '', 0, 5) }}</td>
                <td>
                  <a href="{{ route('research.viewResearcher', $booking->researcher_id ?? 0) }}">
                    {{ e($booking->researcher_name ?? '-') }}
                  </a>
                </td>
                <td>{{ e($booking->room_name ?? '-') }}</td>
                <td>
                  @if(!empty($booking->checked_in_at))
                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ date('H:i', strtotime($booking->checked_in_at)) }}</span>
                  @else
                    <span class="badge bg-secondary">Not yet</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-eye"></i>
                  </a>
                  @if($isToday && empty($booking->checked_in_at))
                    <form action="{{ route('research.bookings.checkIn', $booking->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Check in this researcher?')">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success">
                        <i class="fas fa-sign-in-alt"></i>
                      </button>
                    </form>
                  @elseif(!empty($booking->checked_in_at) && empty($booking->checked_out_at))
                    <form action="{{ route('research.bookings.checkOut', $booking->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Check out this researcher?')">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-warning">
                        <i class="fas fa-sign-out-alt"></i>
                      </button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>
  </div>
@endsection
