@extends('theme::layouts.1col')

@section('title', 'Embargo Status')
@section('body-class', 'extended-rights embargo-status')

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-lock me-2"></i>Embargo Status</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h4 class="mb-0">Embargo Status</h4>
  </div>
  <div class="card-body">
    @if(isset($objectId) && $objectId)
      @include('ahg-rights-holder-manage::extendedRights._embargo-status', ['embargo' => $embargo ?? null])
    @else
      <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>No object specified. Please select a record to view its embargo status.
      </div>
      <h5 class="mt-4">View All Embargoes</h5>
      <p>You can view and manage all embargoes from the embargoes list.</p>
      <a href="{{ route('extended-rights.embargoes') }}" class="btn atom-btn-white"><i class="fas fa-list me-1"></i>View All Embargoes</a>
    @endif
  </div>
</div>
@endsection
