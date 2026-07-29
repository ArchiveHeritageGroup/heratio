{{-- Stratigraphic contexts (layers) for a site - #1428 Phase 1 --}}
@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h4 mb-1">{{ __('Stratigraphy') }}</h1>
      <div class="text-muted small">{{ $site->title ?: __('Untitled site') }} &middot; {{ $site->site_number }}</div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('archaeology.site', $site->id) }}" class="btn btn-outline-secondary btn-sm">&larr; {{ __('Site') }}</a>
      <a href="{{ route('archaeology.context.create', ['site_id' => $site->id]) }}" class="btn btn-primary btn-sm">
        + {{ __('Add context') }}
      </a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success py-2">{{ session('status') }}</div>
  @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between">
      <span>{{ __('Contexts') }}</span>
      <span class="text-muted small">{{ $contexts->count() }} {{ __('recorded') }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>{{ __('Context') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Phase') }}</th>
            <th class="text-end">{{ __('Top (m)') }}</th>
            <th class="text-end">{{ __('Bottom (m)') }}</th>
            <th class="text-end">{{ __('Finds') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($contexts as $c)
            <tr>
              <td><a href="{{ route('archaeology.context', $c->id) }}"><strong>{{ $c->context_number }}</strong></a></td>
              <td>{{ $c->type_name ?: '-' }}</td>
              <td>{{ $c->phase_name ?: '-' }}</td>
              <td class="text-end">{{ $c->top_elevation_m !== null ? number_format($c->top_elevation_m, 3) : '-' }}</td>
              <td class="text-end">{{ $c->bottom_elevation_m !== null ? number_format($c->bottom_elevation_m, 3) : '-' }}</td>
              <td class="text-end">{{ $c->find_count }}</td>
              <td class="text-end">
                <a href="{{ route('archaeology.context.edit', $c->id) }}" class="btn btn-outline-secondary btn-sm py-0">{{ __('Edit') }}</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-muted text-center py-4">{{ __('No contexts recorded yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-muted small mt-2">
    {{ __('Each context is a stratigraphic unit (layer). Its plan and section drawings attach to its own descriptive record; finds are catalogued to their context. Stratigraphic relationships and the Harris Matrix arrive in a later phase.') }}
  </p>

</div>
@endsection
