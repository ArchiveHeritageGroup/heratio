@extends('theme::layouts.1col')
@section('title', 'My Contributions')
@section('body-class', 'heritage')

@php
$contributionData = (array)($contributionData ?? []);
$profile = (array)($profile ?? []);
$contributions = $contributionData['contributions'] ?? [];
$stats = $contributionData['stats'] ?? [];
$contributor = $profile['contributor'] ?? [];
$badges = $profile['badges'] ?? [];
@endphp

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body text-center">
        <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:80px;height:80px"><i class="fas fa-user display-4" style="color:var(--ahg-primary)"></i></div>
        <h5 class="mb-1">{{ $contributor['display_name'] ?? 'Contributor' }}</h5>
        <span class="badge bg-{{ match($contributor['trust_level'] ?? 'new'){'expert'=>'primary','trusted'=>'success','contributor'=>'info',default=>'secondary'} }}">{{ ucfirst($contributor['trust_level'] ?? 'new') }}</span>
      </div>
    </div>
    <div class="row g-2 mb-4">
      @foreach([['total','Total','primary'],['approved','Approved','success'],['pending','Pending','warning'],['total_points','Points','info']] as [$key,$label,$color])
      <div class="col-6"><div class="card border-0 bg-{{ $color }} bg-opacity-10 text-center"><div class="card-body py-3"><div class="h4 mb-0 text-{{ $color }}">{{ number_format($stats[$key] ?? 0) }}</div><small class="text-muted">{{ $label }}</small></div></div></div>
      @endforeach
    </div>
    @if(!empty($badges))
    <div class="card border-0 shadow-sm"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0"><i class="fas fa-award me-2"></i>Badges</h6></div><div class="card-body"><div class="d-flex flex-wrap gap-2">@foreach($badges as $badge)<span class="badge bg-{{ $badge['color'] ?? 'primary' }}" title="{{ $badge['description'] ?? '' }}"><i class="fas {{ $badge['icon'] ?? 'fa-award' }} me-1"></i>{{ $badge['name'] }}</span>@endforeach</div></div></div>
    @endif
  </div>
  <div class="col-md-8">
    <h1><i class="fas fa-journal-whills me-2"></i>My Contributions</h1>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Contribution History') }}</h5>
        <div class="btn-group btn-group-sm">
          <a href="{{ route('heritage.my-contributions') }}" class="btn btn-outline-light {{ !request('status')?'active':'' }}">All</a>
          @foreach(['pending','approved','rejected'] as $s)<a href="{{ route('heritage.my-contributions', ['status'=>$s]) }}" class="btn btn-outline-light {{ request('status')===$s?'active':'' }}">{{ ucfirst($s) }}</a>@endforeach
        </div>
      </div>
      <div class="card-body p-0">
        @if(empty($contributions))
        <div class="text-center text-muted py-5"><i class="fas fa-inbox display-1 mb-3 d-block"></i><p class="mb-3">No contributions yet.</p><a href="{{ route('heritage.search') }}" class="btn atom-btn-secondary"><i class="fas fa-search me-1"></i>{{ __('Browse Collection') }}</a></div>
        @else
        <div class="list-group list-group-flush">
          @foreach($contributions as $contrib)
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div class="d-flex align-items-center">
                <i class="fas {{ $contrib['type']['icon'] }} fs-4 text-{{ $contrib['type']['color'] }} me-3"></i>
                <div><h6 class="mb-0">{{ $contrib['item']['title'] ?? 'Untitled' }}</h6><small class="text-muted">{!! $contrib['type']['name'] !!} &middot; {{ date('M d, Y', strtotime($contrib['created_at'])) }}</small></div>
              </div>
              <div class="text-end">
                <span class="badge bg-{{ match($contrib['status']){'approved'=>'success','rejected'=>'danger','pending'=>'warning',default=>'secondary'} }}">{{ ucfirst($contrib['status']) }}</span>
                @if(($contrib['points_awarded'] ?? 0) > 0)<div class="small text-success mt-1">+{{ $contrib['points_awarded'] }} pts</div>@endif
              </div>
            </div>
            <div class="bg-light rounded p-2 small mb-2">
              @php
              $content = $contrib['content'];
              if(!empty($content['text'])) echo e(substr($content['text'],0,200)) . (strlen($content['text'] ?? '')>200?'...':'');
              elseif(!empty($content['name'])) echo 'Identified: '.e($content['name']);
              elseif(!empty($content['suggestion'])) echo 'Correction: '.e(substr($content['suggestion'],0,100));
              elseif(!empty($content['tags'])) echo 'Tags: '.e(implode(', ',$content['tags']));
              @endphp
            </div>
            @if(!empty($contrib['review_notes']))<div class="alert alert-{{ $contrib['status']==='approved'?'success':'danger' }} py-2 small mb-0"><strong>{{ __('Reviewer:') }}</strong> {{ $contrib['review_notes'] }}</div>@endif
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
