{{-- Artwork placement request - detail (#1459) --}}
@extends('theme::layouts.1col')

@section('title', 'Request '.$requestRow->request_number)

@section('content')
<div class="container-fluid py-4">

  @include('ahg-artwork-request::_flash')

  @php
    $anyApproved = collect($works)->contains(fn($w) => in_array($w->status, ['approved','issued']));
  @endphp

  <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h3 mb-1">{{ $requestRow->request_number }}</h1>
      <span class="badge bg-{{ in_array($requestRow->status, ['approved','fulfilled']) ? 'success' : ($requestRow->status === 'declined' ? 'danger' : 'secondary') }}">{{ $requestRow->status }}</span>
      @if($requestRow->decision_channel === 'offline')<span class="badge bg-info text-dark">decided offline</span>@endif
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('artwork-request.index') }}" class="btn btn-outline-secondary btn-sm">My requests</a>
      @if($canReview && $anyApproved && !$requestRow->loan_id)
        <form method="post" action="{{ route('artwork-request.create-loan', ['id' => $requestRow->id]) }}"
              onsubmit="return confirm('Create a loan record for the approved works? Movement and condition reports are tracked there.');">
          @csrf
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-exchange-alt me-1"></i> Create loan record</button>
        </form>
      @endif
      @if($requestRow->loan_id)
        <a href="{{ url('/loan/'.$requestRow->loan_id) }}" class="btn btn-outline-primary btn-sm">View loan</a>
      @endif
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-header">Works</div>
        <ul class="list-group list-group-flush">
          @foreach($works as $w)
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <span>{{ $w->object_title ?: '#'.$w->information_object_id }}
                @if($w->object_identifier)<span class="small text-muted">· {{ $w->object_identifier }}</span>@endif
                @if($w->conflict_note)<span class="small text-warning d-block">{{ $w->conflict_note }}</span>@endif
              </span>
              <span class="badge bg-{{ in_array($w->status, ['approved','issued']) ? 'success' : ($w->status === 'declined' ? 'danger' : 'secondary') }}">{{ $w->status }}</span>
            </li>
          @endforeach
        </ul>
      </div>

      <div class="card mb-3">
        <div class="card-header">Details</div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-3">Requester</dt><dd class="col-sm-9">{{ $requestRow->requester_name ?: '-' }} {{ $requestRow->requester_email ? '('.$requestRow->requester_email.')' : '' }}</dd>
            <dt class="col-sm-3">Department</dt><dd class="col-sm-9">{{ $requestRow->department ?: '-' }}</dd>
            <dt class="col-sm-3">Period</dt><dd class="col-sm-9">{{ $requestRow->requested_from }} to {{ $requestRow->requested_to }}</dd>
            <dt class="col-sm-3">Purpose</dt><dd class="col-sm-9">{{ $requestRow->purpose ?: '-' }}</dd>
            <dt class="col-sm-3">Placement</dt><dd class="col-sm-9">{{ trim(($requestRow->placement_building ?? '').' '.($requestRow->placement_floor ?? '').' '.($requestRow->placement_room ?? '')) ?: '-' }}
              @if($requestRow->placement_occupant)<span class="text-muted">({{ $requestRow->placement_occupant }})</span>@endif</dd>
            @if($requestRow->justification)<dt class="col-sm-3">Justification</dt><dd class="col-sm-9">{{ $requestRow->justification }}</dd>@endif
            @if($requestRow->review_notes)<dt class="col-sm-3">Review notes</dt><dd class="col-sm-9">{{ $requestRow->review_notes }}</dd>@endif
          </dl>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">History</div>
        <ul class="list-group list-group-flush">
          @forelse($log as $l)
            <li class="list-group-item">
              <div class="d-flex justify-content-between">
                <strong class="text-capitalize">{{ str_replace('_', ' ', $l->event) }}</strong>
                <span class="small text-muted">{{ \Illuminate\Support\Str::of($l->created_at)->substr(0, 16) }}</span>
              </div>
              @if($l->actor_name)<div class="small text-muted">{{ $l->actor_name }}</div>@endif
              @if($l->detail)<div class="small">{{ $l->detail }}</div>@endif
            </li>
          @empty
            <li class="list-group-item text-muted">No history yet.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection
