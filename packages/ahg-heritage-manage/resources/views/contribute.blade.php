@extends('theme::layouts.1col')
@section('title', 'Contribute')
@section('body-class', 'heritage')

@section('content')
<div class="row">
  <div class="col-md-4">
    @if(isset($item))
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">Item Context</h6></div>
      @if($thumbnail ?? false)<img src="{{ $thumbnail }}" class="card-img-top" alt="{{ $item->title ?? 'Item' }}" onerror="this.style.display='none'">@endif
      <div class="card-body">
        <h5 class="card-title">{{ $item->title ?? 'Untitled' }}</h5>
        @if(!empty($item->scope_and_content))<p class="card-text small text-muted">{{ substr(strip_tags($item->scope_and_content),0,200) }}...</p>@endif
      </div>
    </div>
    @endif
    @if(!empty($existingContributions ?? []))
    <div class="card border-0 shadow-sm">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">Previous Contributions</h6></div>
      <ul class="list-group list-group-flush">
        @foreach(array_slice($existingContributions,0,5) as $contrib)
        <li class="list-group-item"><div class="d-flex align-items-center mb-1"><i class="fas {{ $contrib['type']['icon'] }} text-{{ $contrib['type']['color'] }} me-2"></i><small class="fw-bold">{{ $contrib['type']['name'] }}</small></div><small class="text-muted">by {{ $contrib['contributor']['display_name'] }}</small></li>
        @endforeach
      </ul>
    </div>
    @endif
  </div>
  <div class="col-md-8">
    <h1><i class="fas fa-edit me-2"></i>Contribute</h1>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        @if(!($contributorId ?? false))
        <div class="text-center py-5">
          <i class="fas fa-user-lock display-1 text-muted"></i>
          <h3 class="h4 mt-3">Sign In to Contribute</h3>
          <p class="text-muted mb-4">You need a contributor account to submit contributions to our heritage collection.</p>
          <div class="d-flex justify-content-center gap-2">
            <a href="{{ route('heritage.contributor-login') }}" class="btn atom-btn-secondary"><i class="fas fa-sign-in-alt me-2"></i>Sign In</a>
            <a href="{{ route('heritage.contributor-register') }}" class="btn atom-btn-white"><i class="fas fa-user-plus me-2"></i>Create Account</a>
          </div>
        </div>
        @else
        <div class="mb-4"><p class="text-muted mb-0">Contributing as <strong>{{ $contributorName ?? 'Contributor' }}</strong></p></div>

        @if(!empty($opportunities ?? []))
        <ul class="nav nav-pills mb-4" role="tablist">
          @foreach($opportunities as $opp)
          <li class="nav-item" role="presentation">
            <button class="nav-link {{ ($selectedType ?? '')===$opp['code']?'active':'' }} {{ !$opp['available']?'disabled':'' }}" data-bs-toggle="pill" data-bs-target="#form-{{ $opp['code'] }}" type="button" role="tab" {!! !$opp['available']?'disabled title="'.e($opp['reason']).'"':'' !!}>
              <i class="fas {{ $opp['icon'] }} me-1"></i>{{ $opp['name'] }}@if($opp['existing_count']>0)<span class="badge bg-secondary ms-1">{{ $opp['existing_count'] }}</span>@endif
            </button>
          </li>
          @endforeach
        </ul>

        <div class="tab-content">
          @foreach($opportunities as $opp)
          <div class="tab-pane fade {{ ($selectedType ?? '')===$opp['code']?'show active':'' }}" id="form-{{ $opp['code'] }}" role="tabpanel">
            @if(!$opp['available'])<div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i>{{ $opp['reason'] }}</div>
            @else
            <form class="contribution-form">@csrf
              <input type="hidden" name="item_id" value="{{ $item->id ?? '' }}">
              <input type="hidden" name="type_code" value="{{ $opp['code'] }}">
              <div class="mb-3"><p class="text-muted">{{ $opp['description'] }}</p><p class="small text-success"><i class="fas fa-gift me-1"></i>Earn {{ $opp['points_value'] }} points</p></div>

              @if($opp['code']==='transcription')
              <div class="mb-3"><label class="form-label">Transcription <span class="text-danger">*</span></label><textarea class="form-control font-monospace" name="content[text]" rows="12" required minlength="10" placeholder="Type the text exactly as it appears..."></textarea><div class="form-text">Use [...] for unclear words and [illegible] for unreadable sections.</div></div>
              @elseif($opp['code']==='identification')
              <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="content[name]" required minlength="2" placeholder="Full name of the person identified"></div>
              <div class="row mb-3"><div class="col-md-6"><label class="form-label">Relationship to Image</label><select class="form-select" name="content[relationship]"><option value="">Select...</option><option value="subject">Subject (pictured)</option><option value="photographer">Photographer</option><option value="owner">Owner/Donor</option></select></div><div class="col-md-6"><label class="form-label">Position in Image</label><input type="text" class="form-control" name="content[position]" placeholder="e.g., Front row, left"></div></div>
              <div class="mb-3"><label class="form-label">How confident are you?</label><select class="form-select" name="content[confidence]"><option value="certain">Certain</option><option value="likely">Likely</option><option value="possible">Possible</option></select></div>
              @elseif($opp['code']==='context')
              <div class="mb-3"><label class="form-label">Type of Context</label><select class="form-select" name="content[context_type]"><option value="historical">Historical Background</option><option value="personal">Personal Memory</option><option value="location">Location Information</option><option value="event">Event Details</option></select></div>
              <div class="mb-3"><label class="form-label">Your Context <span class="text-danger">*</span></label><textarea class="form-control" name="content[text]" rows="8" required minlength="20" placeholder="Share what you know..."></textarea></div>
              @elseif($opp['code']==='correction')
              <div class="mb-3"><label class="form-label">Field to Correct <span class="text-danger">*</span></label><select class="form-select" name="content[field]" required><option value="">Select field...</option><option value="title">Title</option><option value="date">Date</option><option value="description">Description</option><option value="names">Names</option><option value="location">Location</option></select></div>
              <div class="mb-3"><label class="form-label">Suggested Correction <span class="text-danger">*</span></label><textarea class="form-control" name="content[suggestion]" rows="3" required></textarea></div>
              <div class="mb-3"><label class="form-label">Reason <span class="text-danger">*</span></label><textarea class="form-control" name="content[reason]" rows="2" required></textarea></div>
              @elseif($opp['code']==='translation')
              <div class="mb-3"><label class="form-label">Target Language <span class="text-danger">*</span></label><select class="form-select" name="content[target_language]" required><option value="">Select language...</option>@foreach(['af'=>'Afrikaans','zu'=>'Zulu','xh'=>'Xhosa','st'=>'Sesotho','tn'=>'Setswana','en'=>'English'] as $code=>$name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select></div>
              <div class="mb-3"><label class="form-label">Translation <span class="text-danger">*</span></label><textarea class="form-control" name="content[text]" rows="8" required placeholder="Your translation..."></textarea></div>
              @endif

              <hr>
              <div class="d-flex justify-content-between align-items-center">
                <a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Cancel</a>
                <button type="submit" class="btn atom-btn-secondary"><i class="fas fa-paper-plane me-1"></i>Submit Contribution</button>
              </div>
            </form>
            @endif
          </div>
          @endforeach
        </div>
        @endif
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
