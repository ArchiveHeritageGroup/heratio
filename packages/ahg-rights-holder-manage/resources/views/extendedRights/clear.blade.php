@extends('theme::layouts.1col')

@section('title', 'Clear Extended Rights')
@section('body-class', 'extended-rights clear')

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-eraser me-2"></i>Clear Extended Rights</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header bg-warning">
    <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Clear Rights</h4>
  </div>
  <div class="card-body">
    <p class="lead">Are you sure you want to clear all extended rights from this record?</p>

    <div class="alert alert-info">
      <strong>The following rights will be removed:</strong>
      <ul class="mb-0 mt-2">
        @if(($currentRights->rights_statement ?? null))
          <li><i class="fas fa-balance-scale me-1"></i>Rights Statement: {{ $currentRights->rights_statement->name ?? '' }}</li>
        @endif
        @if(($currentRights->cc_license ?? null))
          <li><i class="fab fa-creative-commons me-1"></i>Creative Commons License: {{ $currentRights->cc_license->name ?? '' }}</li>
        @endif
        @if(!empty($currentRights->tk_labels ?? []))
          <li><i class="fas fa-hand-holding-heart me-1"></i>Traditional Knowledge Labels: {{ implode(', ', $currentRights->tk_labels ?? []) }}</li>
        @endif
        @if(($currentRights->rights_holder ?? null))
          <li><i class="fas fa-user me-1"></i>Rights Holder: {{ $currentRights->rights_holder->name ?? '' }}</li>
        @endif
        @if(!($currentRights->rights_statement ?? null) && !($currentRights->cc_license ?? null) && empty($currentRights->tk_labels ?? []) && !($currentRights->rights_holder ?? null))
          <li class="text-muted"><em>No extended rights currently assigned</em></li>
        @endif
      </ul>
    </div>

    <p class="text-muted small">Note: This action will not affect embargoes. Use the embargo management to lift embargoes.</p>

    <form method="post" action="{{ route('extended-rights.clear.store', $resource->slug ?? '') }}">
      @csrf
      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-outline-danger"><i class="fas fa-eraser me-2"></i>Yes, clear all rights</button>
        <a href="{{ url()->previous() }}" class="btn atom-btn-outline-light"><i class="fas fa-times me-2"></i>Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
