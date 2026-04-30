@extends('ahg-registry::layouts.registry')

@section('title', __('Edit Institution'))

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.institutionView', ['id' => $institution->id]) }}">{{ $institution->name ?? __('Institution') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4"><i class="fas fa-edit me-2"></i>{{ __('Edit Institution') }}</h1>

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
    <form method="post" action="{{ route('registry.institutionUpdate', ['id' => $institution->id]) }}" enctype="multipart/form-data">
      @csrf

      <div class="mb-3">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $institution->name) }}" required>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Type') }}</label>
        @php $type = old('institution_type', $institution->institution_type); @endphp
        <select name="institution_type" class="form-select">
          @foreach (['archive','library','museum','gallery','dam','heritage_site','research_centre','government','university','academic','community','private'] as $t)
            <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
          @endforeach
        </select>
      </div>

      <div class="row g-3">
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('City') }}</label>
          <input type="text" name="city" class="form-control" value="{{ old('city', $institution->city) }}">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">{{ __('Country') }}</label>
          <input type="text" name="country" class="form-control" value="{{ old('country', $institution->country) }}">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Description') }}</label>
        <textarea name="description" class="form-control" rows="4">{{ old('description', $institution->description) }}</textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">{{ __('Logo') }}</label>
        @if (! empty($institution->logo_path))
          <div class="d-flex align-items-center gap-3 mb-2">
            <img src="{{ $institution->logo_path }}" alt="" class="rounded border" style="max-height: 64px; max-width: 64px; object-fit: contain;">
            <form method="post" action="{{ route('registry.institutionLogoDelete', ['id' => $institution->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove this logo?') }}');">
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
        <a href="{{ route('registry.institutionView', ['id' => $institution->id]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>

@endsection
