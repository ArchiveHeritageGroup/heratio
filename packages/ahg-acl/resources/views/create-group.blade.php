@extends('theme::layouts.1col')

@section('title', __('Add group'))

@section('content')
<div class="container py-4">

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Admin</a></li>
      <li class="breadcrumb-item"><a href="{{ route('acl.groups') }}">ACL Groups</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ __('Add group') }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users-cog me-2"></i> {{ __('Add group') }}</h2>
    <a href="{{ route('acl.groups') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> {{ __('Back to Groups') }}
    </a>
  </div>

  @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> {{ __('Profile') }}</h5>
        </div>
        <div class="card-body">
          <form action="{{ route('acl.store-group') }}" method="POST" autocomplete="off">
            @csrf
            <div class="mb-3">
              <label for="name" class="form-label">{{ __('Name') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" name="name" id="name" class="form-control" autocomplete="off" value="{{ old('name') }}" required>
            </div>
            <div class="mb-3">
              <label for="description" class="form-label">{{ __('Description') }}</label>
              <textarea name="description" id="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
            <fieldset class="mb-3">
              <legend class="form-label small">{{ __('Translate') }}</legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="translate" id="translate_yes" value="1" {{ old('translate') === '1' ? 'checked' : '' }}>
                <label class="form-check-label" for="translate_yes">{{ __('Yes') }}</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="translate" id="translate_no" value="0" {{ old('translate') === '1' ? '' : 'checked' }}>
                <label class="form-check-label" for="translate_no">{{ __('No') }}</label>
              </div>
            </fieldset>
            <button type="submit" class="btn atom-btn-outline-success">
              <i class="fas fa-save me-1"></i> {{ __('Create group') }}
            </button>
          </form>
          <p class="text-muted small mt-3 mb-0">{{ __('Members and permissions can be set after the group is created.') }}</p>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
