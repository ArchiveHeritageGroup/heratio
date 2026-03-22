@extends('theme::layouts.1col')
@section('title', 'Edit OAIS Package')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-edit me-2"></i>Edit OAIS Package</h1>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction ?? '#' }}">
      @csrf
      @if(isset($package)) @method('PUT') @endif

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">Package Details</div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3"><label class="form-label">Package Type</label>
              <select name="package_type" class="form-select">
                <option value="SIP" {{ old('package_type', $package->package_type ?? '') == 'SIP' ? 'selected' : '' }}>SIP (Submission)</option>
                <option value="AIP" {{ old('package_type', $package->package_type ?? '') == 'AIP' ? 'selected' : '' }}>AIP (Archival)</option>
                <option value="DIP" {{ old('package_type', $package->package_type ?? '') == 'DIP' ? 'selected' : '' }}>DIP (Dissemination)</option>
              </select>
            </div>
            <div class="col-md-6 mb-3"><label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="pending" {{ old('status', $package->status ?? '') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="active" {{ old('status', $package->status ?? '') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="quarantined" {{ old('status', $package->status ?? '') == 'quarantined' ? 'selected' : '' }}>Quarantined</option>
              </select>
            </div>
            <div class="col-12 mb-3"><label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3">{{ old('description', $package->description ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
        <a href="{{ route('preservation.packages') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection