@extends('theme::layouts.1col')
@section('title', 'Request Access')
@section('body-class', 'heritage')

@section('content')
<div class="row">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h6 class="mb-0">{{ __('Requesting Access To') }}</h6></div>
      <div class="card-body">
        <h5>{{ $resource->title ?? $resource->slug ?? 'Item' }}</h5>
        @if($resource->slug ?? false)<a href="{{ route('informationobject.show', $resource->slug) }}" target="_blank" class="btn btn-sm atom-btn-white mt-2"><i class="fas fa-eye me-1"></i>View Item</a>@endif
      </div>
    </div>
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i><strong>Why request access?</strong><br>Some items may have restricted access due to privacy, copyright, or cultural sensitivity. Your request will be reviewed by our team.</div>
  </div>
  <div class="col-md-8">
    <h1><i class="fas fa-key me-2"></i>Request Access</h1>

    @if(!auth()->check())
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Login Required</strong><br>You must be logged in to request access. <a href="{{ route('user.login') }}" class="alert-link">Login here</a></div>
    @else
    <form method="post">@csrf
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Access Request Details') }}</h5></div>
        <div class="card-body">
          <div class="mb-3"><label for="purpose_id" class="form-label">Purpose of Access <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><select class="form-select" name="purpose_id" required><option value="">{{ __('Select a purpose...') }}</option>@foreach($purposes ?? [] as $purpose)<option value="{{ $purpose->id }}">{{ $purpose->name }}@if($purpose->requires_approval) (Requires Approval)@endif</option>@endforeach</select></div>
          <div class="mb-3"><label for="institution_affiliation" class="form-label">Institution/Organization <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="institution_affiliation" placeholder="{{ __('e.g., University of Cape Town') }}"></div>
          <div class="mb-3"><label for="research_description" class="form-label">Research Project/Description <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" name="research_description" rows="3" placeholder="{{ __('Briefly describe your research project...') }}"></textarea></div>
          <div class="mb-3"><label for="justification" class="form-label">Justification <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><textarea class="form-control" name="justification" rows="4" required placeholder="{{ __('Explain why you need access...') }}"></textarea><div class="form-text">Please provide sufficient detail to help us evaluate your request.</div></div>
        </div>
      </div>
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Terms & Conditions') }}</h5></div>
        <div class="card-body">
          <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="agree_terms" required><label class="form-check-label" for="agree_terms">I agree to use this material only for the stated purpose and will comply with any usage restrictions. <span class="badge bg-danger ms-1">Required</span></label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="agree_attribution" required><label class="form-check-label" for="agree_attribution">I agree to provide proper attribution when using or citing this material. <span class="badge bg-danger ms-1">Required</span></label></div>
        </div>
      </div>
      <div class="d-flex justify-content-between">
        <a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Cancel</a>
        <button type="submit" class="btn atom-btn-secondary"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
      </div>
    </form>
    @endif
  </div>
</div>
@endsection
