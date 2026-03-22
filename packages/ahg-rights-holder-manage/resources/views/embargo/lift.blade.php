@extends('theme::layouts.1col')

@section('title', 'Lift Embargo')
@section('body-class', 'embargo lift')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">Lift Embargo</h1>
    <span class="small" id="heading-label">{{ $resource->title ?? $resource->slug ?? '' }}</span>
  </div>
@endsection

@section('content')
<div class="alert alert-info mb-4">
  <i class="fas fa-info-circle me-2"></i>
  Lifting this embargo will immediately restore access to the record.
</div>

<form method="post" action="{{ route('embargo.lift', $embargo->id) }}">
  @csrf
  <div class="card mb-4">
    <div class="card-header bg-success text-white">
      <h4 class="mb-0"><i class="fas fa-unlock me-2"></i>Confirm Lift Embargo</h4>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-4"><strong>Embargo Type:</strong></div>
        <div class="col-md-8">{{ ucfirst(str_replace('_', ' ', $embargo->embargo_type ?? 'full')) }}</div>
      </div>
      <div class="row mb-3">
        <div class="col-md-4"><strong>Start Date:</strong></div>
        <div class="col-md-8">{{ $embargo->start_date }}</div>
      </div>
      @if($embargo->end_date)
        <div class="row mb-3">
          <div class="col-md-4"><strong>End Date:</strong></div>
          <div class="col-md-8">{{ $embargo->end_date }}</div>
        </div>
      @endif
      <hr>
      <div class="mb-3">
        <label for="lift_reason" class="form-label">Reason for lifting (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="lift_reason" id="lift_reason" class="form-control" rows="3" placeholder="e.g., Embargo period completed, Permission granted, Error correction"></textarea>
      </div>
    </div>
  </div>

  <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
    <a href="{{ url()->previous() }}" class="btn atom-btn-outline-light">Cancel</a>
    <button type="submit" class="btn atom-btn-white"><i class="fas fa-unlock me-1"></i> Lift Embargo</button>
  </section>
</form>
@endsection
