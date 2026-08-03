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
      <a href="{{ route('archaeology.contexts.import', $site->id) }}" class="btn btn-outline-primary btn-sm">
        {{ __('Import CSV') }}
      </a>
      <a href="{{ route('archaeology.context.create', ['site_id' => $site->id]) }}" class="btn btn-primary btn-sm">
        + {{ __('Add context') }}
      </a>
    </div>
  </div>

  @if(session('status'))
    <div class="alert alert-success py-2">{{ session('status') }}</div>
  @endif

  {{-- Harris Matrix (server-side tiering; latest at top, earliest at bottom) --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <span>{{ __('Harris Matrix') }}</span>
      <span class="text-muted small">{{ $matrix['context_count'] }} {{ __('contexts') }}, {{ $matrix['relationship_count'] }} {{ __('relationships') }}</span>
    </div>
    <div class="card-body">
      @if($matrix['has_cycle'])
        <div class="alert alert-warning py-2 mb-0">{{ __('The stratigraphy contains a contradiction (a loop through same-as links) and cannot be laid out. Review the relationships.') }}</div>
      @elseif(empty($matrix['tiers']) || $matrix['relationship_count'] === 0)
        <p class="text-muted mb-0">{{ __('No stratigraphic relationships recorded yet. Add relationships on each context sheet to build the matrix.') }}</p>
      @else
        <div class="text-muted small mb-2">{{ __('Latest (top) to earliest (bottom).') }}</div>
        <div class="harris-matrix">
          @foreach($matrix['tiers'] as $level => $cells)
            <div class="d-flex flex-wrap gap-3 justify-content-center mb-3">
              @foreach($cells as $members)
                <div class="border rounded px-3 py-2 bg-light text-center" style="min-width:5rem">
                  <div>
                    @foreach($members as $m)<a href="{{ route('archaeology.context', $m->id) }}" class="fw-semibold text-decoration-none">{{ $m->context_number }}</a>@if(!$loop->last) <span class="text-muted">=</span> @endif @endforeach
                  </div>
                  @if(($members[0]->type_name ?? null))<div class="text-muted" style="font-size:.72rem">{{ $members[0]->type_name }}</div>@endif
                </div>
              @endforeach
            </div>
            @if(!$loop->last)<div class="text-center text-muted mb-2" style="line-height:.5">&darr;</div>@endif
          @endforeach
        </div>
        <details class="mt-3">
          <summary class="small text-muted">{{ __('Mermaid source (for export to a diagram tool)') }}</summary>
          <pre class="small bg-body-tertiary border rounded p-2 mt-2 mb-0" style="white-space:pre-wrap">{{ $matrix['mermaid'] }}</pre>
        </details>
      @endif
    </div>
  </div>

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
    {{ __('Each context is a stratigraphic unit (layer). Its plan and section drawings attach to its own descriptive record; finds are catalogued to their context. Record stratigraphic relationships on each context sheet, or import a whole sequence with "Import CSV"; the Harris Matrix above is built from those relationships.') }}
  </p>

</div>
@endsection
