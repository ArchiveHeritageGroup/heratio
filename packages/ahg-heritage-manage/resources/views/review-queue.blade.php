@extends('theme::layouts.1col')
@section('title', 'Review Queue')
@section('body-class', 'admin heritage')

@php
$queueData = (array)($queueData ?? []);
$contributions = $queueData['contributions'] ?? [];
$countsByType = $queueData['counts_by_type'] ?? [];
$total = $queueData['total'] ?? 0;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-heritage-manage::partials._admin-sidebar')
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter by Type</h6></div>
      <div class="list-group list-group-flush">
        <a href="{{ route('heritage.review-queue') }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ !request('type')?'active':'' }}">All Types <span class="badge bg-primary">{{ $total }}</span></a>
        @foreach($countsByType as $type)
        <a href="{{ route('heritage.review-queue', ['type'=>$type['code']]) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ request('type')===$type['code']?'active':'' }}"><span><i class="fas {{ $type['icon'] }} me-2"></i>{{ $type['name'] }}</span><span class="badge bg-warning">{{ $type['count'] }}</span></a>
        @endforeach
      </div>
    </div>
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-inbox me-2"></i>Review Queue</h1>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Pending Contributions') }}</h5><span class="badge bg-warning text-dark">{{ $total }} pending</span>
      </div>
      <div class="card-body p-0">
        @if(empty($contributions))
        <div class="text-center text-muted py-5"><i class="fas fa-check-circle display-1 text-success mb-3 d-block"></i><p class="mb-0">All caught up! No pending contributions to review.</p></div>
        @else
        <div class="list-group list-group-flush">
          @foreach($contributions as $contrib)
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div class="d-flex align-items-center">
                <i class="fas {{ $contrib['type']['icon'] }} fs-4 text-{{ $contrib['type']['color'] }} me-3"></i>
                <div><h6 class="mb-0"><a href="{{ route('heritage.review-contribution', $contrib['id']) }}" class="text-decoration-none">{{ $contrib['item']['title'] }}</a></h6><small class="text-muted">{!! $contrib['type']['name'] !!} &middot; Submitted {{ date('M d, Y H:i', strtotime($contrib['created_at'])) }}</small></div>
              </div>
              <span class="badge bg-{{ match($contrib['contributor']['trust_level'] ?? 'new'){'expert'=>'primary','trusted'=>'success','contributor'=>'info',default=>'secondary'} }}">{{ ucfirst($contrib['contributor']['trust_level'] ?? 'new') }}</span>
            </div>
            <div class="d-flex align-items-center mb-2 small text-muted">
              <span class="me-3"><i class="fas fa-user me-1"></i>{{ $contrib['contributor']['display_name'] }}</span>
              <span class="me-3"><i class="fas fa-check-circle text-success me-1"></i>{{ $contrib['contributor']['approved_count'] }} approved</span>
              <span><i class="fas fa-gift me-1" style="color:var(--ahg-primary)"></i>+{{ $contrib['type']['points_value'] }} pts</span>
            </div>
            <div class="bg-light rounded p-2 small mb-2">
              @php
              $content = $contrib['content'];
              if(!empty($content['text'])) echo e(substr($content['text'],0,200)).(strlen($content['text'] ?? '')>200?'...':'');
              elseif(!empty($content['name'])) echo 'Identified: <strong>'.e($content['name']).'</strong>';
              elseif(!empty($content['suggestion'])) echo 'Field: <strong>'.e($content['field'] ?? 'unknown').'</strong> &rarr; '.e(substr($content['suggestion'],0,100));
              @endphp
            </div>
            <div class="d-flex gap-2">
              <a href="{{ route('heritage.review-contribution', $contrib['id']) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>{{ __('Review') }}</a>
            </div>
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
