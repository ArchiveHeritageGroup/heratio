{{-- Booking Detail - Migrated from AtoM --}}
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

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-calendar-check me-2"></i>{{ __('Booking Detail') }}</h1>
    @php
      $bsc = ['confirmed' => 'success', 'pending' => 'warning', 'cancelled' => 'danger', 'checked_in' => 'primary', 'checked_out' => 'secondary', 'no_show' => 'dark'];
    @endphp
    <span class="badge bg-{{ $bsc[$booking->status ?? ''] ?? 'secondary' }} fs-6">{{ ucfirst(str_replace('_', ' ', e($booking->status ?? 'unknown'))) }}</span>
  </div>

  {{-- Schedule Info --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-clock me-2"></i>{{ __('Schedule') }}</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <dl class="row mb-0">
            <dt class="col-sm-4">Date</dt>
            <dd class="col-sm-8">{{ e($booking->date ?? '-') }}</dd>
            <dt class="col-sm-4">Start Time</dt>
            <dd class="col-sm-8">{{ e($booking->start_time ?? '-') }}</dd>
            <dt class="col-sm-4">End Time</dt>
            <dd class="col-sm-8">{{ e($booking->end_time ?? '-') }}</dd>
          </dl>
        </div>
        <div class="col-md-6">
          <dl class="row mb-0">
            <dt class="col-sm-4">Room</dt>
            <dd class="col-sm-8">{{ e($booking->room_name ?? '-') }}</dd>
            <dt class="col-sm-4">Check-in</dt>
            <dd class="col-sm-8">{{ e($booking->checked_in_at ?? '-') }}</dd>
            <dt class="col-sm-4">Check-out</dt>
            <dd class="col-sm-8">{{ e($booking->checked_out_at ?? '-') }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>

  {{-- Researcher Info --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-user me-2"></i>{{ __('Researcher') }}</div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Name</dt>
        <dd class="col-sm-9">
          @if($isAdmin ?? false)
            <a href="{{ route('research.viewResearcher', $booking->researcher_id ?? 0) }}">
              {{ e($booking->researcher_name ?? '-') }}
            </a>
          @else
            {{ e($booking->researcher_name ?? '-') }}
          @endif
        </dd>
        <dt class="col-sm-3">Email</dt>
        <dd class="col-sm-9">{{ e($booking->researcher_email ?? '-') }}</dd>
        <dt class="col-sm-3">Institution</dt>
        <dd class="col-sm-9">{{ e($booking->researcher_institution ?? '-') }}</dd>
      </dl>
    </div>
  </div>

  {{-- Purpose --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-clipboard me-2"></i>{{ __('Purpose') }}</div>
    <div class="card-body">
      <p>{{ e($booking->purpose ?? 'Not specified') }}</p>
      @if($booking->notes ?? false)
        <hr>
        <h6>{{ __('Notes') }}</h6>
        <p>{{ e($booking->notes) }}</p>
      @endif
    </div>
  </div>

  {{-- Materials Requested --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-archive me-2"></i>{{ __('Materials Requested') }}</div>
    <div class="card-body">
      @if(count($materials ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ __('Material') }}</th>
                <th>{{ __('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($materials as $i => $material)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td>
                    @if($material->slug ?? null)
                      <a href="{{ url('/' . $material->slug) }}">{{ e($material->description ?? 'Untitled') }}</a>
                    @else
                      {{ e($material->description ?? 'Object #' . ($material->object_id ?? '')) }}
                    @endif
                  </td>
                  <td>
                    <span class="badge bg-{{ ($material->status ?? '') === 'retrieved' ? 'success' : (($material->status ?? '') === 'pending' ? 'warning' : 'secondary') }}">
                      {{ ucfirst(e($material->status ?? 'pending')) }}
                    </span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No materials requested.</p>
      @endif
    </div>
  </div>

  {{-- Action Buttons --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cogs me-2"></i>{{ __('Actions') }}</div>
    <div class="card-body d-flex flex-wrap gap-2">
      @if($isAdmin ?? false)
        @if(($booking->status ?? '') === 'pending')
          <form action="{{ route('research.bookings.confirm', $booking->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-check me-1"></i>{{ __('Confirm') }}</button>
          </form>
        @endif

        @if(($booking->status ?? '') === 'confirmed')
          <form action="{{ route('research.bookings.checkIn', $booking->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-sign-in-alt me-1"></i>{{ __('Check In') }}</button>
          </form>
          <form action="{{ route('research.bookings.noShow', $booking->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-user-slash me-1"></i>{{ __('No Show') }}</button>
          </form>
        @endif

        @if(($booking->status ?? '') === 'checked_in')
          <form action="{{ route('research.bookings.checkOut', $booking->id) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn atom-btn-white"><i class="fas fa-sign-out-alt me-1"></i>{{ __('Check Out') }}</button>
          </form>
        @endif
      @endif

      @if(in_array($booking->status ?? '', ['pending', 'confirmed']))
        <form action="{{ route('research.bookings.cancel', $booking->id) }}" method="POST" class="d-inline">
          @csrf
          <button type="submit" class="btn atom-btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this booking?')">
            <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
          </button>
        </form>
      @endif

      <a href="{{ route('research.bookings') }}" class="btn atom-btn-white">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
      </a>
    </div>
  </div>
@endsection
