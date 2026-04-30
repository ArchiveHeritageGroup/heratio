@extends('theme::layouts.1col')
@section('title', 'Integrity - Generate Destruction Certificate')
@section('body-class', 'admin integrity certificates generate')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Generate Destruction Certificate') }}</h1><span class="small text-muted">{{ __('Digital object integrity management') }}</span></div>
  </div>
@endsection
@section('content')

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Certificate Details') }}</h5></div>
  <div class="card-body">
    <div class="alert alert-info">
      <strong>Disposition Queue #{{ $disposition->id }}</strong><br>
      Information Object: <strong>{{ $ioTitle }}</strong> (ID: {{ $disposition->information_object_id }})<br>
      Status: <span class="badge bg-secondary">{{ ucfirst($disposition->status) }}</span>
    </div>

    <form method="post" action="{{ route('integrity.certificates.store') }}">
      @csrf
      <input type="hidden" name="disposition_id" value="{{ $disposition->id }}">

      <div class="mb-3">
        <label for="authorized_by" class="form-label">{{ __('Authorized By') }}</label>
        <input type="text" name="authorized_by" id="authorized_by" class="form-control" value="{{ old('authorized_by', '') }}" required maxlength="255">
        @error('authorized_by')
        <div class="text-danger small">{{ $message }}</div>
        @enderror
      </div>

      <div class="mb-3">
        <label for="destruction_method" class="form-label">{{ __('Method of Destruction') }}</label>
        <select name="destruction_method" id="destruction_method" class="form-select" required>
          <option value="">-- Select method --</option>
          <option value="secure_delete" {{ old('destruction_method') === 'secure_delete' ? 'selected' : '' }}>{{ __('Secure Delete (Digital)') }}</option>
          <option value="overwrite" {{ old('destruction_method') === 'overwrite' ? 'selected' : '' }}>{{ __('Overwrite (Digital)') }}</option>
          <option value="degaussing" {{ old('destruction_method') === 'degaussing' ? 'selected' : '' }}>{{ __('Degaussing') }}</option>
          <option value="shredding" {{ old('destruction_method') === 'shredding' ? 'selected' : '' }}>{{ __('Shredding (Physical)') }}</option>
          <option value="incineration" {{ old('destruction_method') === 'incineration' ? 'selected' : '' }}>{{ __('Incineration (Physical)') }}</option>
          <option value="pulping" {{ old('destruction_method') === 'pulping' ? 'selected' : '' }}>{{ __('Pulping (Physical)') }}</option>
          <option value="chemical" {{ old('destruction_method') === 'chemical' ? 'selected' : '' }}>{{ __('Chemical Destruction') }}</option>
          <option value="other" {{ old('destruction_method') === 'other' ? 'selected' : '' }}>{{ __('Other') }}</option>
        </select>
        @error('destruction_method')
        <div class="text-danger small">{{ $message }}</div>
        @enderror
      </div>

      <div class="mb-3">
        <label for="witness" class="form-label">{{ __('Witness (optional)') }}</label>
        <input type="text" name="witness" id="witness" class="form-control" value="{{ old('witness', '') }}" maxlength="255">
        @error('witness')
        <div class="text-danger small">{{ $message }}</div>
        @enderror
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-danger"><i class="fas fa-certificate me-1"></i>{{ __('Generate Certificate') }}</button>
        <a href="{{ route('integrity.certificates') }}" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>

<div class="mt-3"><a href="{{ route('integrity.certificates') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Certificates') }}</a></div>
@endsection
