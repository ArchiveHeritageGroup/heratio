@extends('theme::layouts.1col')
@section('title', 'Review Contribution')
@section('body-class', 'admin heritage')

@php
$contribution = (array)($contribution ?? []);
$content = $contribution['content'] ?? [];
$item = $contribution['item'] ?? [];
$contributor = $contribution['contributor'] ?? [];
$type = $contribution['type'] ?? [];
$versions = $contribution['versions'] ?? [];
@endphp

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-file me-2"></i>Item Context</h6></div>
      @if(!empty($item['thumbnail']))<img src="{{ $item['thumbnail'] }}" class="card-img-top" alt="{{ $item['title'] ?? 'Item' }}" onerror="this.style.display='none'">@endif
      <div class="card-body">
        <h6 class="card-title">{{ $item['title'] ?? 'Untitled' }}</h6>
        @if(!empty($item['description']))<p class="card-text small text-muted">{{ substr(strip_tags($item['description']),0,200) }}...</p>@endif
      </div>
    </div>
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-user me-2"></i>Contributor</h6></div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <div class="bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width:40px;height:40px"><i class="fas fa-user" style="color:var(--ahg-primary)"></i></div>
          <div><strong>{{ $contributor['display_name'] ?? 'Unknown' }}</strong><div><span class="badge bg-{{ match($contributor['trust_level'] ?? 'new'){'expert'=>'primary','trusted'=>'success','contributor'=>'info',default=>'secondary'} }}">{{ ucfirst($contributor['trust_level'] ?? 'new') }}</span></div></div>
        </div>
        <div class="small text-muted">
          <div class="d-flex justify-content-between mb-1"><span>Approved:</span><strong class="text-success">{{ $contributor['approved_count'] ?? 0 }}</strong></div>
          <div class="d-flex justify-content-between mb-1"><span>Total:</span><strong>{{ $contributor['total_count'] ?? 0 }}</strong></div>
          <div class="d-flex justify-content-between"><span>Approval Rate:</span><strong>{{ $contributor['approval_rate'] ?? 0 }}%</strong></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <h1><i class="fas fa-clipboard-check me-2"></i>Review Contribution</h1>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <div class="d-flex align-items-center"><i class="fas {{ $type['icon'] ?? 'fa-file' }} fs-4 me-2"></i><h5 class="mb-0">{{ $type['name'] ?? 'Contribution' }}</h5></div>
        <span class="badge bg-warning text-dark">Pending Review</span>
      </div>
      <div class="card-body">
        <div class="row mb-4">
          <div class="col-md-4"><small class="text-muted d-block">Submitted</small><strong>{{ date('M d, Y H:i', strtotime($contribution['created_at'] ?? 'now')) }}</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Version</small><strong>v{{ $contribution['version_number'] ?? 1 }}</strong></div>
          <div class="col-md-4"><small class="text-muted d-block">Points Value</small><strong class="text-success">+{{ $type['points_value'] ?? 0 }} pts</strong></div>
        </div>
        <hr>
        <h6 class="text-muted mb-3">Contribution Content</h6>

        @if(($type['code'] ?? '') === 'transcription')
        <div class="bg-light border rounded p-3 font-monospace" style="white-space:pre-wrap">{{ $content['text'] ?? '' }}</div>
        @if(!empty($content['notes']))<div class="mt-2"><small class="text-muted"><strong>Notes:</strong> {{ $content['notes'] }}</small></div>@endif
        @elseif(($type['code'] ?? '') === 'identification')
        <div class="row"><div class="col-md-6 mb-3"><label class="form-label small text-muted">Name <span class="badge bg-secondary ms-1">Optional</span></label><div class="fw-bold">{{ $content['name'] ?? '' }}</div></div><div class="col-md-6 mb-3"><label class="form-label small text-muted">Relationship <span class="badge bg-secondary ms-1">Optional</span></label><div>{{ ucfirst($content['relationship'] ?? 'Not specified') }}</div></div></div>
        @if(!empty($content['source']))<div class="bg-light rounded p-3"><small class="text-muted d-block mb-1">Source/Evidence:</small>{{ $content['source'] }}</div>@endif
        @elseif(($type['code'] ?? '') === 'correction')
        @if(!empty($content['current_value']))<div class="mb-3"><label class="form-label small text-muted">Current Value <span class="badge bg-secondary ms-1">Optional</span></label><div class="bg-danger bg-opacity-10 border border-danger rounded p-2">{{ $content['current_value'] }}</div></div>@endif
        <div class="mb-3"><label class="form-label small text-muted">Suggested Correction <span class="badge bg-secondary ms-1">Optional</span></label><div class="bg-success bg-opacity-10 border border-success rounded p-2">{{ $content['suggestion'] ?? '' }}</div></div>
        @if(!empty($content['reason']))<div><label class="form-label small text-muted">Reason <span class="badge bg-secondary ms-1">Optional</span></label><div class="bg-light rounded p-2">{{ $content['reason'] }}</div></div>@endif
        @else
        <div class="bg-light border rounded p-3">{!! nl2br(e($content['text'] ?? json_encode($content, JSON_PRETTY_PRINT))) !!}</div>
        @endif
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-check-square me-2"></i>Decision</h5></div>
      <div class="card-body">
        <form method="post" action="{{ route('heritage.review-contribution', $contribution['id'] ?? 0) }}">@csrf
          <div class="mb-3"><label for="notes" class="form-label">Review Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" name="notes" rows="3" placeholder="Add any notes for the contributor..."></textarea><div class="form-text">These notes will be visible to the contributor.</div></div>
          <div class="d-flex gap-2">
            <button type="submit" name="decision" value="approve" class="btn atom-btn-outline-success btn-lg flex-fill"><i class="fas fa-check-circle me-2"></i>Approve</button>
            <button type="submit" name="decision" value="reject" class="btn atom-btn-outline-danger btn-lg flex-fill"><i class="fas fa-times-circle me-2"></i>Reject</button>
          </div>
        </form>
        <hr class="my-4">
        <a href="{{ route('heritage.review-queue') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Queue</a>
      </div>
    </div>
  </div>
</div>
@endsection
