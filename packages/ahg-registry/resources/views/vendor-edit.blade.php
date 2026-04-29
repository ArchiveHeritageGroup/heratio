@extends('ahg-registry::layouts.registry')

@section('title', __('Edit Vendor'))

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.vendorView', ['id' => $vendor->id]) }}">{{ $vendor->name ?? __('Vendor') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-edit me-2"></i>{{ __('Edit Vendor') }}</h1>

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
  </div>
@endif

<div class="card">
  <div class="card-body">
    <form method="post" action="{{ route('registry.vendorUpdate', ['id' => $vendor->id]) }}" enctype="multipart/form-data">
      @csrf

      <div class="mb-3">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $vendor->name) }}" required>
      </div>

      <div class="row g-3">
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('City') }}</label>
          <input type="text" name="city" class="form-control" value="{{ old('city', $vendor->city) }}">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Country') }}</label>
          <input type="text" name="country" class="form-control" value="{{ old('country', $vendor->country) }}">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Short description') }}</label>
        <input type="text" name="short_description" class="form-control" value="{{ old('short_description', $vendor->short_description) }}" maxlength="255">
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Description') }}</label>
        <textarea name="description" class="form-control" rows="4">{{ old('description', $vendor->description) }}</textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Logo') }}</label>
        @if (! empty($vendor->logo_path))
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="{{ $vendor->logo_path }}" alt="" class="rounded border" style="max-height: 64px; max-width: 64px; object-fit: contain;">
            <form method="post" action="{{ route('registry.vendorLogoDelete', ['id' => $vendor->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove this logo?') }}');">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Remove') }}</button>
            </form>
          </div>
        @endif
        <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.gif,.svg,.webp">
        <div class="form-text">{{ __('Max 5 MB. Permitted: JPG, PNG, GIF, SVG, WebP.') }}</div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
        <a href="{{ route('registry.vendorView', ['id' => $vendor->id]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>

@endsection
