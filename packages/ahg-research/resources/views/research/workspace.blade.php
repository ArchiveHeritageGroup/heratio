{{-- Researcher Workspace - Migrated from AtoM --}}
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

  <h1 class="mb-4"><i class="fas fa-briefcase me-2"></i>My Workspace</h1>

  {{-- Stats Cards --}}
  <div class="row mb-4">
    <div class="col-md col-sm-6 mb-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
          <h4>{{ $stats['bookings'] ?? 0 }}</h4>
          <small class="text-muted">Bookings</small>
        </div>
      </div>
    </div>
    <div class="col-md col-sm-6 mb-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="fas fa-layer-group fa-2x text-success mb-2"></i>
          <h4>{{ $stats['collections'] ?? 0 }}</h4>
          <small class="text-muted">Collections</small>
        </div>
      </div>
    </div>
    <div class="col-md col-sm-6 mb-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="fas fa-search fa-2x text-info mb-2"></i>
          <h4>{{ $stats['savedSearches'] ?? 0 }}</h4>
          <small class="text-muted">Saved Searches</small>
        </div>
      </div>
    </div>
    <div class="col-md col-sm-6 mb-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="fas fa-highlighter fa-2x text-warning mb-2"></i>
          <h4>{{ $stats['annotations'] ?? 0 }}</h4>
          <small class="text-muted">Annotations</small>
        </div>
      </div>
    </div>
    <div class="col-md col-sm-6 mb-3">
      <div class="card text-center">
        <div class="card-body">
          <i class="fas fa-archive fa-2x text-secondary mb-2"></i>
          <h4>{{ $stats['totalItems'] ?? 0 }}</h4>
          <small class="text-muted">Total Items</small>
        </div>
      </div>
    </div>
  </div>

  {{-- Upcoming Bookings --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-calendar-alt me-2"></i>Upcoming Bookings</span>
      <a href="{{ route('research.book') }}" class="btn btn-sm btn-primary">
        <i class="fas fa-plus me-1"></i>Book Room
      </a>
    </div>
    <div class="card-body">
      @if(count($upcomingBookings ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Room</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($upcomingBookings as $booking)
                <tr>
                  <td>{{ e($booking->date ?? '') }}</td>
                  <td>{{ e($booking->start_time ?? '') }} - {{ e($booking->end_time ?? '') }}</td>
                  <td>{{ e($booking->room_name ?? '') }}</td>
                  <td>
                    <span class="badge bg-{{ ($booking->status ?? '') === 'confirmed' ? 'success' : 'warning' }}">
                      {{ ucfirst(e($booking->status ?? '')) }}
                    </span>
                  </td>
                  <td>
                    <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm btn-outline-primary">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No upcoming bookings.</p>
      @endif
    </div>
  </div>

  {{-- Past Bookings --}}
  <div class="card mb-4">
    <div class="card-header">
      <i class="fas fa-history me-2"></i>Past Bookings
    </div>
    <div class="card-body">
      @if(count($pastBookings ?? []) > 0)
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Room</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($pastBookings as $booking)
                <tr>
                  <td>{{ e($booking->date ?? '') }}</td>
                  <td>{{ e($booking->start_time ?? '') }} - {{ e($booking->end_time ?? '') }}</td>
                  <td>{{ e($booking->room_name ?? '') }}</td>
                  <td>
                    <span class="badge bg-secondary">{{ ucfirst(e($booking->status ?? '')) }}</span>
                  </td>
                  <td>
                    <a href="{{ route('research.viewBooking', $booking->id) }}" class="btn btn-sm btn-outline-secondary">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted mb-0">No past bookings.</p>
      @endif
    </div>
  </div>

  {{-- Collections --}}
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="fas fa-layer-group me-2"></i>My Collections</span>
      <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#createCollectionModal">
        <i class="fas fa-plus me-1"></i>New Collection
      </button>
    </div>
    <div class="card-body">
      @if(count($collections ?? []) > 0)
        <div class="list-group">
          @foreach($collections as $collection)
            <a href="{{ route('research.viewCollection', $collection->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
              <div>
                <strong>{{ e($collection->name) }}</strong>
                @if($collection->description ?? false)
                  <br><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($collection->description, 80)) }}</small>
                @endif
              </div>
              <span class="badge bg-primary rounded-pill">{{ $collection->items_count ?? 0 }} items</span>
            </a>
          @endforeach
        </div>
      @else
        <p class="text-muted mb-0">No collections yet.</p>
      @endif
    </div>
  </div>

  {{-- Quick Links --}}
  <div class="card mb-4">
    <div class="card-header"><i class="fas fa-link me-2"></i>Quick Links</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-2">
          <a href="{{ route('research.annotations') }}" class="btn btn-outline-warning w-100">
            <i class="fas fa-highlighter me-1"></i>My Annotations
          </a>
        </div>
        <div class="col-md-6 mb-2">
          <a href="{{ route('research.savedSearches') }}" class="btn btn-outline-info w-100">
            <i class="fas fa-search me-1"></i>Saved Searches
          </a>
        </div>
      </div>
    </div>
  </div>

  {{-- Create Collection Modal --}}
  <div class="modal fade" id="createCollectionModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{ route('research.collections.store') }}" method="POST">
          @csrf
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>New Collection</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="collection_name" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="collection_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="collection_description" class="form-label">Description</label>
              <textarea name="description" id="collection_description" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">
              <i class="fas fa-plus me-1"></i>Create
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
