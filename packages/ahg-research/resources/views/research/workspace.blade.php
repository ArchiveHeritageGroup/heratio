{{-- Researcher Workspace - Migrated from AtoM ahgResearchPlugin --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'workspace'])
@endsection

@section('title', 'My Workspace')

@section('content')
@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<h1><i class="fas fa-briefcase text-primary me-2"></i>{{ __('My Workspace') }}</h1>

{{-- Status Alerts --}}
@php
  $status = $researcher->status ?? 'pending';
  $expiresAt = $researcher->expires_at ?? null;
  $isExpired = $status === 'expired' || ($status === 'approved' && $expiresAt && strtotime($expiresAt) < time());
  $isExpiringSoon = $status === 'approved' && $expiresAt && !$isExpired && strtotime($expiresAt) < strtotime('+30 days');
@endphp

@if($isExpired)
<div class="alert alert-danger d-flex justify-content-between align-items-center mb-4">
  <div>
    <i class="fas fa-exclamation-circle me-2"></i>
    <strong>{{ __('Your researcher registration has expired.') }}</strong> Please request a renewal to continue using research features.
  </div>
  <a href="{{ route('research.register') }}" class="btn btn-danger"><i class="fas fa-sync-alt me-1"></i>{{ __('Request Renewal') }}</a>
</div>
@elseif($isExpiringSoon)
<div class="alert alert-warning d-flex justify-content-between align-items-center mb-4">
  <div>
    <i class="fas fa-clock me-2"></i>
    <strong>Your registration expires on {{ date('M j, Y', strtotime($expiresAt)) }}</strong>
  </div>
  <a href="{{ route('research.register') }}" class="btn btn-warning"><i class="fas fa-sync-alt me-1"></i>{{ __('Request Renewal') }}</a>
</div>
@elseif($status === 'rejected')
<div class="alert alert-danger d-flex justify-content-between align-items-center mb-4">
  <div>
    <i class="fas fa-times-circle me-2"></i>
    <strong>{{ __('Your registration was rejected.') }}</strong>
    @if($researcher->rejection_reason ?? false)
      <br><small>Reason: {{ e($researcher->rejection_reason) }}</small>
    @endif
  </div>
  <a href="{{ route('research.register') }}" class="btn btn-primary"><i class="fas fa-redo me-1"></i>{{ __('Re-apply') }}</a>
</div>
@endif

{{-- Profile Summary Bar --}}
<div class="card bg-light mb-4">
  <div class="card-body py-3">
    <div class="row align-items-center">
      <div class="col-md-6">
        <h5 class="mb-1">
          <i class="fas fa-user-graduate me-2 text-primary"></i>{{ e(($researcher->title ? $researcher->title . ' ' : '') . ($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) }}
        </h5>
        <small class="text-muted">
          {{ e($researcher->institution ?: 'Independent Researcher') }}
          @if($researcher->affiliation_type ?? false)
            <span class="badge bg-secondary ms-2">{{ ucfirst($researcher->affiliation_type) }}</span>
          @endif
        </small>
      </div>
      <div class="col-md-6 text-md-end mt-2 mt-md-0">
        <a href="{{ route('research.profile') }}" class="btn btn-outline-secondary btn-sm me-1"><i class="fas fa-user-edit me-1"></i>{{ __('Edit Profile') }}</a>
        @if($canUseFeatures)
          <a href="{{ route('research.book') }}" class="btn btn-primary btn-sm"><i class="fas fa-calendar-plus me-1"></i>{{ __('Book Reading Room') }}</a>
        @else
          <button class="btn btn-secondary btn-sm" disabled><i class="fas fa-calendar-plus me-1"></i>{{ __('Book Reading Room') }}</button>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Stats Row --}}
<div class="row mb-4">
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center h-100 border-primary">
      <div class="card-body py-3">
        <h3 class="text-primary mb-0">{{ number_format($stats['total_collections'] ?? 0) }}</h3>
        <small class="text-muted">{{ __('Evidence Sets') }}</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center h-100 border-success">
      <div class="card-body py-3">
        <h3 class="text-success mb-0">{{ number_format($stats['total_items'] ?? 0) }}</h3>
        <small class="text-muted">{{ __('Saved Items') }}</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center h-100 border-info">
      <div class="card-body py-3">
        <h3 class="text-info mb-0">{{ number_format($stats['total_saved_searches'] ?? 0) }}</h3>
        <small class="text-muted">{{ __('Saved Searches') }}</small>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3 mb-2">
    <div class="card text-center h-100 border-warning">
      <div class="card-body py-3">
        <h3 class="text-warning mb-0">{{ number_format($stats['total_bookings'] ?? 0) }}</h3>
        <small class="text-muted">{{ __('Total Visits') }}</small>
      </div>
    </div>
  </div>
</div>

{{-- Weekly Activity --}}
@if(!empty($weeklyActivity))
<div class="card mb-4">
  <div class="card-body py-2">
    <div class="d-flex justify-content-between align-items-center">
      <small class="text-muted"><i class="fas fa-chart-bar me-1"></i>Activity (last 7 days)</small>
      <canvas id="weeklyActivityChart" width="300" height="40"></canvas>
    </div>
  </div>
</div>
@endif

{{-- 3-Column Layout --}}
<div class="row">
  {{-- Left Column: Bookings --}}
  <div class="col-md-4 mb-4">
    <div class="card mb-4">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-2">
        <span><i class="fas fa-calendar-check me-2"></i>{{ __('Upcoming Visits') }}</span>
        @if($canUseFeatures)
          <a href="{{ route('research.book') }}" class="btn btn-sm btn-light py-0"><i class="fas fa-plus"></i></a>
        @endif
      </div>
      @if(!empty($upcomingBookings))
        <ul class="list-group list-group-flush">
          @foreach($upcomingBookings as $booking)
            <li class="list-group-item py-2">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <strong>{{ date('D, M j', strtotime($booking->booking_date)) }}</strong><br>
                  <small class="text-muted">{{ substr($booking->start_time, 0, 5) }} - {{ substr($booking->end_time, 0, 5) }}</small><br>
                  <small>{{ e($booking->room_name) }}</small>
                </div>
                <span class="badge bg-{{ $booking->status === 'confirmed' ? 'success' : 'warning' }}">{{ ucfirst($booking->status) }}</span>
              </div>
            </li>
          @endforeach
        </ul>
      @else
        <div class="card-body text-center text-muted py-4">
          <i class="fas fa-calendar fa-2x mb-2 opacity-50"></i>
          <p class="mb-2">No upcoming visits</p>
          @if($canUseFeatures)
            <a href="{{ route('research.book') }}" class="btn btn-sm btn-outline-primary">Book a visit</a>
          @else
            <button class="btn btn-sm btn-outline-secondary" disabled>{{ __('Book a visit') }}</button>
          @endif
        </div>
      @endif
    </div>

    <div class="card">
      <div class="card-header bg-secondary text-white py-2"><i class="fas fa-history me-2"></i>{{ __('Recent Visits') }}</div>
      @if(!empty($pastBookings))
        <ul class="list-group list-group-flush">
          @foreach(array_slice($pastBookings, 0, 3) as $booking)
            <li class="list-group-item py-2">
              <small>
                <strong>{{ date('M j, Y', strtotime($booking->booking_date)) }}</strong> - {{ e($booking->room_name) }}
                <span class="badge bg-{{ $booking->status === 'completed' ? 'success' : 'secondary' }} float-end">{{ ucfirst($booking->status) }}</span>
              </small>
            </li>
          @endforeach
        </ul>
      @else
        <div class="card-body text-muted small py-3">No visit history yet</div>
      @endif
    </div>
  </div>

  {{-- Middle Column: Evidence Sets --}}
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-2">
        <span><i class="fas fa-layer-group me-2"></i>{{ __('Evidence Sets') }}</span>
        @if($canUseFeatures)
          <button type="button" class="btn btn-sm btn-light py-0" data-bs-toggle="modal" data-bs-target="#newCollectionModal"><i class="fas fa-plus"></i></button>
        @endif
      </div>
      @if(!empty($collections) && count($collections) > 0)
        <ul class="list-group list-group-flush">
          @foreach($collections as $collection)
            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
              <a href="{{ route('research.viewCollection') }}?id={{ $collection->id }}">
                <i class="fas fa-folder-open me-2 text-muted"></i>{{ e($collection->name) }}
              </a>
              <div>
                <span class="badge bg-secondary me-1">{{ $collection->item_count ?? $collection->items_count ?? 0 }}</span>
              </div>
            </li>
          @endforeach
        </ul>
        <div class="card-footer text-center py-2">
          <a href="{{ route('research.collections') }}" class="small">Manage all evidence sets <i class="fas fa-arrow-right"></i></a>
        </div>
      @else
        <div class="card-body text-center text-muted py-4">
          <i class="fas fa-folder-open fa-2x mb-2 opacity-50"></i>
          <p class="mb-2">No evidence sets yet</p>
          @if($canUseFeatures)
            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#newCollectionModal">{{ __('Create your first evidence set') }}</button>
          @else
            <button class="btn btn-sm btn-outline-secondary" disabled>{{ __('Create your first evidence set') }}</button>
          @endif
        </div>
      @endif
    </div>
  </div>

  {{-- Right Column: Saved Searches & Notes --}}
  <div class="col-md-4 mb-4">
    <div class="card mb-4">
      <div class="card-header bg-info text-white d-flex justify-content-between align-items-center py-2">
        <span><i class="fas fa-search me-2"></i>{{ __('Saved Searches') }}</span>
        <a href="{{ route('research.savedSearches') }}" class="btn btn-sm btn-light py-0"><i class="fas fa-cog"></i></a>
      </div>
      @if(!empty($savedSearches) && count($savedSearches) > 0)
        <ul class="list-group list-group-flush">
          @foreach(array_slice(is_array($savedSearches) ? $savedSearches : $savedSearches->toArray(), 0, 5) as $search)
            <li class="list-group-item py-2">
              <a href="{{ url('/informationobject/browse') }}?{{ $search->search_query ?? '' }}" class="text-decoration-none">
                <i class="fas fa-redo me-2 text-info"></i><strong>{{ e($search->name ?? 'Unnamed Search') }}</strong>
              </a>
              <br><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($search->search_query ?? '', 40)) }}</small>
            </li>
          @endforeach
        </ul>
      @else
        <div class="card-body text-center text-muted py-3">
          <i class="fas fa-search fa-2x mb-2 opacity-50"></i>
          <p class="mb-0 small">Save searches from browse results</p>
        </div>
      @endif
    </div>

    <div class="card">
      <div class="card-header bg-warning d-flex justify-content-between align-items-center py-2">
        <span><i class="fas fa-sticky-note me-2"></i>{{ __('My Notes') }}</span>
        <a href="{{ route('research.annotations') }}" class="btn btn-sm btn-light py-0"><i class="fas fa-list"></i></a>
      </div>
      @if(!empty($annotations) && count($annotations) > 0)
        <ul class="list-group list-group-flush">
          @foreach(array_slice(is_array($annotations) ? $annotations : $annotations->toArray(), 0, 4) as $annotation)
            <li class="list-group-item py-2">
              <small>
                <strong>{{ e($annotation->title ?: \Illuminate\Support\Str::limit($annotation->content ?? '', 30) . '...') }}</strong>
                <br><span class="text-muted">on: {{ e($annotation->object_title ?? 'Unknown item') }}</span>
              </small>
            </li>
          @endforeach
        </ul>
      @else
        <div class="card-body text-center text-muted py-3">
          <i class="fas fa-sticky-note fa-2x mb-2 opacity-50"></i>
          <p class="mb-0 small">Add notes to items while browsing</p>
        </div>
      @endif
    </div>
  </div>
</div>

{{-- Quick Tips --}}
<div class="card border-info mt-2">
  <div class="card-body py-3">
    <h6 class="card-title"><i class="fas fa-lightbulb text-info me-2"></i>{{ __('Research Tips') }}</h6>
    <div class="row small text-muted">
      <div class="col-md-4"><i class="fas fa-plus-circle me-1"></i> {{ __('Use "Add to Evidence Set" button while browsing to save items') }}</div>
      <div class="col-md-4"><i class="fas fa-bookmark me-1"></i> {{ __('Save searches to quickly re-run them later') }}</div>
      <div class="col-md-4"><i class="fas fa-file-pdf me-1"></i> {{ __('Generate finding aids from your evidence sets') }}</div>
    </div>
  </div>
</div>

{{-- New Evidence Set Modal --}}
<div class="modal fade" id="newCollectionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        @csrf
        <input type="hidden" name="booking_action" value="create_collection">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>{{ __('Create New Evidence Set') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">{{ __('Evidence Set Name *') }}</label>
            <input type="text" name="collection_name" class="form-control" required placeholder="{{ __('e.g., My Research Project') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="collection_description" class="form-control" rows="2" placeholder="{{ __('Optional description...') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-success"><i class="fas fa-plus me-1"></i>{{ __('Create') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Chart.js (activity sparkline) --}}
@if(!empty($weeklyActivity))
@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var ctx = document.getElementById('weeklyActivityChart');
  if (ctx) {
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: {!! json_encode(array_map(fn($d) => date('D', strtotime($d->date ?? 'now')), $weeklyActivity)) !!},
        datasets: [{ data: {!! json_encode(array_map(fn($d) => (int)($d->count ?? 0), $weeklyActivity)) !!}, backgroundColor: '#0d6efd', borderRadius: 2 }]
      },
      options: {
        responsive: false, plugins: { legend: { display: false }, tooltip: { enabled: true } },
        scales: { x: { display: false }, y: { display: false, beginAtZero: true } }
      }
    });
  }
});
</script>
@endpush
@endif
@endsection
