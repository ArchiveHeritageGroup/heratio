@extends('theme::layouts.1col')
@section('title', 'My Access Requests')
@section('body-class', 'heritage')

@php
$requests = $requestData['requests'] ?? [];
$total = $requestData['total'] ?? 0;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    <div class="list-group mb-4">
      <a href="?status=" class="list-group-item list-group-item-action {{ !request('status') ? 'active' : '' }}">All Requests</a>
      <a href="?status=pending" class="list-group-item list-group-item-action {{ request('status')==='pending' ? 'active' : '' }}">Pending</a>
      <a href="?status=approved" class="list-group-item list-group-item-action {{ request('status')==='approved' ? 'active' : '' }}">Approved</a>
      <a href="?status=denied" class="list-group-item list-group-item-action {{ request('status')==='denied' ? 'active' : '' }}">Denied</a>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-key me-2"></i>My Access Requests</h1>

    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Your Access Requests</h5></div>
      <div class="card-body p-0">
        @if(empty($requests))
        <div class="text-center text-muted py-5"><i class="fas fa-inbox fs-1 mb-3 d-block"></i><p>No access requests found.</p></div>
        @else
        <div class="list-group list-group-flush">
          @foreach($requests as $request)
          @php $color = ['pending'=>'warning','approved'=>'success','denied'=>'danger','expired'=>'secondary'][$request->status] ?? 'secondary'; @endphp
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div><h6 class="mb-1">{{ $request->object_title ?? $request->slug ?? 'Item' }}</h6><small class="text-muted">Purpose: {{ $request->purpose_name ?? 'Not specified' }}</small></div>
              <div class="text-end"><span class="badge bg-{{ $color }}">{{ ucfirst($request->status) }}</span><br><small class="text-muted">{{ date('M d, Y', strtotime($request->created_at)) }}</small></div>
            </div>
            @if($request->status === 'approved' && $request->valid_until)<div class="mt-2"><small class="text-success"><i class="fas fa-check-circle me-1"></i>Access valid until {{ date('M d, Y', strtotime($request->valid_until)) }}</small></div>
            @elseif($request->status === 'denied' && $request->decision_notes)<div class="mt-2"><small class="text-danger"><i class="fas fa-info-circle me-1"></i>{{ $request->decision_notes }}</small></div>@endif
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
