{{--
  AI Services - Custom NER entity (gazetteer) CRUD (Issue #667 Phase 1).

  Operator-curated entities run as an exact + alias substring pre-pass in
  NerService::extract() before the ML model. Use for domain-specific names
  the ML model would miss: project codenames, micro-locations, organisation
  acronyms, etc.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Custom NER Entities')
@section('body-class', 'admin ai-services ner-custom')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-tag-fill"></i> {{ __('Custom NER Entities') }}</h1>
  <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>{{ __('Back to AI Services') }}
  </a>
</div>

<p class="text-muted">Operator-curated gazetteer that runs as an exact + alias substring pre-pass before the ML extractor. Use for project codenames, micro-locations, organisation acronyms, and other domain-specific labels the ML model is likely to miss.</p>

@if(session('status'))
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i>{{ session('status') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger">
  <strong>{{ __('Could not save') }}</strong>
  <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-3 mb-3">
  <div class="col-md-8">
    <form method="get" class="row g-2 align-items-end mb-0">
      <div class="col-md-6">
        <label for="type" class="form-label small">{{ __('Filter by type') }}</label>
        <select id="type" name="type" class="form-select form-select-sm">
          <option value="">{{ __('All types') }}</option>
          @foreach($types as $t)
          <option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel me-1"></i>{{ __('Apply') }}</button>
      </div>
      <div class="col-md-3">
        <a href="{{ route('admin.ai-services.ner-entities') }}" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>{{ __('Reset') }}</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header bg-white">
    <strong><i class="bi bi-collection me-2"></i>{{ __('Gazetteer entries') }}</strong>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Type') }}</th>
          <th>{{ __('Label') }}</th>
          <th>{{ __('Aliases') }}</th>
          <th>{{ __('Definition') }}</th>
          <th>{{ __('URI') }}</th>
          <th class="text-center">{{ __('Active') }}</th>
          <th class="text-center">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
        @php
          $aliasArr = $row->aliases ? json_decode($row->aliases, true) : [];
          if (!is_array($aliasArr)) { $aliasArr = []; }
        @endphp
        <tr>
          <td><code>{{ $row->entity_type }}</code></td>
          <td><strong>{{ $row->label }}</strong></td>
          <td class="small">{{ implode(', ', array_slice($aliasArr, 0, 4)) }}@if(count($aliasArr) > 4) <span class="text-muted">+{{ count($aliasArr) - 4 }}</span>@endif</td>
          <td class="small text-muted">{{ \Illuminate\Support\Str::limit((string) $row->definition, 80) }}</td>
          <td class="small">
            @if($row->target_uri)
              <a href="{{ $row->target_uri }}" target="_blank" rel="noopener" class="text-truncate d-inline-block" style="max-width: 200px;">{{ $row->target_uri }}</a>
            @else
              <span class="text-muted">-</span>
            @endif
          </td>
          <td class="text-center">
            @if((int) $row->is_active === 1)
              <span class="badge bg-success">{{ __('yes') }}</span>
            @else
              <span class="badge bg-secondary">{{ __('no') }}</span>
            @endif
          </td>
          <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ner-edit-{{ $row->id }}" title="{{ __('Edit') }}">
              <i class="bi bi-pencil"></i>
            </button>
            <form action="{{ route('admin.ai-services.ner-entities.delete') }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('Delete this entity?') }}');">
              @csrf
              <input type="hidden" name="id" value="{{ $row->id }}">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>

        {{-- Edit modal --}}
        <div class="modal fade" id="ner-edit-{{ $row->id }}" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <form action="{{ route('admin.ai-services.ner-entities.save') }}" method="post" class="modal-content">
              @csrf
              <input type="hidden" name="id" value="{{ $row->id }}">
              <div class="modal-header"><h5 class="modal-title">{{ __('Edit entity') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
              <div class="modal-body">
                <div class="row g-3">
                  <div class="col-md-4"><label class="form-label">{{ __('Type') }}</label><input class="form-control" name="entity_type" value="{{ $row->entity_type }}" required></div>
                  <div class="col-md-8"><label class="form-label">{{ __('Label') }}</label><input class="form-control" name="label" value="{{ $row->label }}" required></div>
                  <div class="col-12"><label class="form-label">{{ __('Aliases (one per line)') }}</label><textarea class="form-control" name="aliases" rows="3">{{ implode("\n", $aliasArr) }}</textarea></div>
                  <div class="col-12"><label class="form-label">{{ __('Definition') }}</label><textarea class="form-control" name="definition" rows="2">{{ $row->definition }}</textarea></div>
                  <div class="col-md-9"><label class="form-label">{{ __('Target URI') }}</label><input class="form-control" name="target_uri" value="{{ $row->target_uri }}"></div>
                  <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                      <input id="active-{{ $row->id }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((int) $row->is_active === 1)>
                      <label class="form-check-label" for="active-{{ $row->id }}">{{ __('Active') }}</label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>{{ __('Save') }}</button>
              </div>
            </form>
          </div>
        </div>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No custom entities yet. Add one below.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if(method_exists($rows, 'links'))
  <div class="card-footer bg-white">{{ $rows->withQueryString()->links() }}</div>
  @endif
</div>

<div class="card shadow-sm">
  <div class="card-header bg-white"><strong><i class="bi bi-plus-circle me-2"></i>{{ __('Add new entity') }}</strong></div>
  <div class="card-body">
    <form action="{{ route('admin.ai-services.ner-entities.save') }}" method="post" class="row g-3">
      @csrf
      <div class="col-md-3">
        <label for="new_entity_type" class="form-label">{{ __('Type') }}</label>
        <input id="new_entity_type" name="entity_type" class="form-control" placeholder="{{ __('person, organization, place, ...') }}" required>
      </div>
      <div class="col-md-4">
        <label for="new_label" class="form-label">{{ __('Label') }}</label>
        <input id="new_label" name="label" class="form-control" required>
      </div>
      <div class="col-md-5">
        <label for="new_target_uri" class="form-label">{{ __('Target URI (optional)') }}</label>
        <input id="new_target_uri" name="target_uri" class="form-control" placeholder="{{ __('https://...') }}">
      </div>
      <div class="col-md-6">
        <label for="new_aliases" class="form-label">{{ __('Aliases (one per line)') }}</label>
        <textarea id="new_aliases" name="aliases" class="form-control" rows="3"></textarea>
      </div>
      <div class="col-md-6">
        <label for="new_definition" class="form-label">{{ __('Definition (optional)') }}</label>
        <textarea id="new_definition" name="definition" class="form-control" rows="3"></textarea>
      </div>
      <div class="col-12">
        <div class="form-check d-inline-block me-3">
          <input id="new_is_active" class="form-check-input" type="checkbox" name="is_active" value="1" checked>
          <label class="form-check-label" for="new_is_active">{{ __('Active') }}</label>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('Add entity') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
