@extends('theme::layouts.1col')

@section('title', 'Create Group')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create Group') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-users me-2"></i>{{ __('Create Group') }}</h1>

<div class="card">
  <div class="card-body">
    <form method="post">
      @csrf
      <div class="mb-3">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('Description') }} <span class="badge bg-secondary ms-1">Optional</span></label>
        <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
        <a href="{{ route('registry.groupBrowse') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>

@endsection
