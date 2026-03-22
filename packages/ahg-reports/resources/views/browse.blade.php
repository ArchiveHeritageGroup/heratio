@extends('theme::layouts.1col')
@section('title', 'Reports Browse')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-list me-2"></i>Browse Reports</h1>
    <p class="text-muted">Select a report from the sidebar menu, or use the filters below to narrow your search.</p>

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>Strong Rooms / Location Filter</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Strong Rooms</label>
            <select name="strongroom" id="strongroomSelect" class="form-select form-select-sm">
              <option value="">Select</option>
              @foreach($strongrooms ?? [] as $room)
                <option value="{{ $room }}">{{ $room }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Location</label>
            <select name="location" id="locationSelect" class="form-select form-select-sm">
              <option value="">Select</option>
              @foreach($locations ?? [] as $loc)
                <option value="{{ $loc }}">{{ $loc }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end gap-2">
            <a href="{{ route('reports.browse-publish') }}" class="btn btn-sm atom-btn-outline-success"><i class="fas fa-eye me-1"></i>Publish</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection