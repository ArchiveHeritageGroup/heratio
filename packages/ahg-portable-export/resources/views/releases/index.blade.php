@extends('theme::layouts.1col')
@section('title', __('Versioned releases'))
@section('body-class', 'admin portable-export')
@section('content')
<div class="container-fluid py-4">
  <a href="{{ route('portable-export.index') }}" class="btn btn-outline-secondary btn-sm mb-3"><i class="fas fa-arrow-left me-1"></i>{{ __('Portable Export') }}</a>
  <h1 class="h3">{{ __('Versioned releases') }}</h1>
  <p class="text-muted">{{ __('Publish a Portable Export as a numbered, frozen version with a DOI, plus a concept DOI that always leads to the newest version.') }}</p>
  @include('ahg-portable-export::releases._flash')

  <div class="card mb-4">
    <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('Release series') }}</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
      <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Scope') }}</th><th>{{ __('Versions') }}</th><th>{{ __('Concept DOI') }}</th></tr></thead>
      <tbody>
        @forelse ($series as $s)
          <tr><td><a href="{{ route('portable-export.releases.series', $s->id) }}">{{ $s->title }}</a></td><td>{{ $s->scope_type }}</td><td>{{ $s->versions }}</td><td>{{ $s->concept_doi ?: '-' }}</td></tr>
        @empty
          <tr><td colspan="4" class="text-muted">{{ __('No release series yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table></div>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-4"><div class="card h-100">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('New series') }}</div>
      <div class="card-body">
        @if ($exports->isEmpty())
          <p class="text-muted mb-0">{{ __('Build a Portable Export first.') }}</p>
        @else
        <form method="POST" action="{{ route('portable-export.releases.series.store') }}">@csrf
          <div class="mb-3"><label class="form-label" for="export_id">{{ __('Export that defines the scope') }}</label>
            <select class="form-select" id="export_id" name="export_id" required>@foreach ($exports as $x)<option value="{{ $x->id }}">#{{ $x->id }} - {{ $x->title }} ({{ $x->scope_type }}, {{ $x->total_descriptions }})</option>@endforeach</select></div>
          <div class="mb-3"><label class="form-label" for="title">{{ __('Title') }}</label><input class="form-control" id="title" name="title" maxlength="500" required value="{{ old('title') }}"></div>
          <div class="mb-3"><label class="form-label" for="description">{{ __('Description') }}</label><textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea></div>
          <div class="mb-3"><label class="form-label" for="creator_name">{{ __('Creator (as cited)') }}</label><input class="form-control" id="creator_name" name="creator_name" maxlength="500" required value="{{ old('creator_name') }}"></div>
          <div class="mb-3"><label class="form-label" for="publisher">{{ __('Publisher') }}</label><input class="form-control" id="publisher" name="publisher" maxlength="500" required value="{{ old('publisher', $defaultPublisher) }}"></div>
          <div class="mb-3"><label class="form-label" for="license">{{ __('Licence identifier') }}</label><input class="form-control" id="license" name="license" maxlength="64" value="{{ old('license') }}"><div class="form-text">{{ __('For example cc-by-4.0. Required for an external deposit.') }}</div></div>
          <button class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Create series') }}</button>
        </form>
        @endif
      </div>
    </div></div>

    <div class="col-lg-6 mb-4"><div class="card h-100">
      <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">{{ __('Deposit targets') }}</div>
      <div class="card-body">
        <ul class="list-unstyled">
          @forelse ($targets as $t)
            <li class="d-flex justify-content-between align-items-center mb-2">
              <span>{{ $t->name }} <span class="text-muted">({{ $targetTypes[$t->target_type] ?? $t->target_type }}{{ $t->base_url ? ', '.$t->base_url : '' }})</span>@unless ($t->is_active) <span class="badge bg-secondary">{{ __('Inactive') }}</span>@endunless</span>
              <form method="POST" action="{{ route('portable-export.releases.targets.toggle', $t->id) }}">@csrf<button class="btn btn-sm atom-btn-white">{{ $t->is_active ? __('Deactivate') : __('Activate') }}</button></form>
            </li>
          @empty
            <li class="text-muted">{{ __('No deposit targets yet.') }}</li>
          @endforelse
        </ul>
        <form method="POST" action="{{ route('portable-export.releases.targets.store') }}" autocomplete="off">@csrf
          <div class="mb-3"><label class="form-label" for="t_name">{{ __('Name') }}</label><input class="form-control" id="t_name" name="name" maxlength="255" required></div>
          <div class="mb-3"><label class="form-label" for="t_type">{{ __('Type') }}</label><select class="form-select" id="t_type" name="target_type" required>@foreach ($targetTypes as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
          <div class="mb-3"><label class="form-label" for="t_url">{{ __('API address (external repository)') }}</label><input class="form-control" id="t_url" name="base_url" type="url" maxlength="255" placeholder="https://sandbox.zenodo.org"></div>
          <div class="mb-3"><label class="form-label" for="t_token">{{ __('API token (external repository)') }}</label><input class="form-control" id="t_token" name="api_token" type="password" maxlength="500" autocomplete="new-password"><div class="form-text">{{ __('Stored encrypted and never shown again.') }}</div></div>
          <div class="mb-3"><label class="form-label" for="t_community">{{ __('Community (optional)') }}</label><input class="form-control" id="t_community" name="community" maxlength="100"></div>
          <button class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Add target') }}</button>
        </form>
      </div>
    </div></div>
  </div>
</div>
@endsection
