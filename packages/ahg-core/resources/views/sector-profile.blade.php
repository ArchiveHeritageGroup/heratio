@extends('theme::layouts.1col')

@section('title', __('Sector Site Profile'))
@section('body-class', 'admin settings')

@section('content')
<div class="d-flex align-items-center mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Sector Site Profile') }}</h1>
  <span class="small text-muted ms-3">{{ __('Apply an opinionated theme + identifier mask for your sector. Re-applicable any time.') }}</span>
</div>

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="alert alert-info small d-flex align-items-center">
  <i class="fas fa-info-circle me-2"></i>
  <div>{{ __('A profile sets a theme colour palette, the default identifier mask, and the install sector marker. It does NOT remove any package and changes no records — switch or re-apply freely. Jurisdiction-neutral (sector only).') }}</div>
</div>

<div class="card">
  <div class="card-body">
    <p class="mb-3">
      {{ __('Current sector') }}:
      @if ($current)
        <span class="badge bg-primary">{{ $profiles[$current]['label'] ?? $current }}</span>
      @else
        <span class="badge bg-secondary">{{ __('none applied') }}</span>
      @endif
    </p>

    <form method="POST" action="{{ route('admin.sector-profile.apply') }}" class="row g-2 align-items-end">
      @csrf
      <div class="col-md-6">
        <label for="sector" class="form-label fw-bold">{{ __('Sector') }}</label>
        <select name="sector" id="sector" class="form-select">
          @foreach ($profiles as $code => $p)
            <option value="{{ $code }}" @selected($current === $code)>{{ $p['label'] }} — {{ __('mask') }} {{ $p['mask'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-auto">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="with_sample" id="with_sample" value="1">
          <label class="form-check-label" for="with_sample">{{ __('Also load sample content') }}</label>
          <div class="form-text">{{ __('A few representative published records for the sector (idempotent).') }}</div>
        </div>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>{{ __('Apply profile') }}</button>
      </div>
    </form>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header">{{ __('Available profiles') }}</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>{{ __('Sector') }}</th><th>{{ __('Palette') }}</th><th>{{ __('Identifier mask') }}</th><th>{{ __('Theme keys') }}</th></tr></thead>
      <tbody>
        @foreach ($profiles as $code => $p)
          <tr>
            <td>{{ $p['label'] }}@if ($current === $code) <span class="badge bg-primary ms-1">{{ __('active') }}</span>@endif</td>
            <td><span class="d-inline-block rounded" style="width:20px;height:20px;background:{{ $p['theme']['ahg_primary_color'] ?? '#999' }};vertical-align:middle;"></span>
                <code class="small ms-1">{{ $p['theme']['ahg_primary_color'] ?? '' }}</code></td>
            <td><code>{{ $p['mask'] }}</code></td>
            <td class="small text-muted">{{ count($p['theme']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
