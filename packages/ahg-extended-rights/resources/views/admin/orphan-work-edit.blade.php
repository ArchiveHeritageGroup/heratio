@extends('theme::layouts.2col')

@section('title', isset($orphanWork) ? 'Edit Orphan Work Search' : 'New Orphan Work Search')
@section('body-class', 'admin rights-admin orphan-work-edit')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">
    <i class="fas fa-search me-2"></i>
    {{ isset($orphanWork) ? 'Orphan Work Due Diligence' : 'New Orphan Work Search' }}
  </h1>
@endsection

@section('content')
  <div class="row">
    <div class="col-lg-8">
      {{-- Main Form --}}
      <form method="post" action="{{ isset($orphanWork) ? route('ext-rights-admin.orphan-work-update', $orphanWork->id) : route('ext-rights-admin.orphan-work-create') }}">
        @csrf
        <div class="card mb-4">
          <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <h5 class="mb-0">Work Details</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Object ID <span class="text-danger">*</span></label>
                <input type="number" name="object_id" class="form-control" required
                       value="{{ old('object_id', $orphanWork->object_id ?? request('object_id', '')) }}"
                       {{ isset($orphanWork) ? 'readonly' : '' }}>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Work Type <span class="text-danger">*</span></label>
                <select name="work_type" class="form-select" required>
                  @foreach($formOptions['work_type_options'] as $value => $label)
                  <option value="{{ $value }}" {{ (old('work_type', $orphanWork->work_type ?? '')) === $value ? 'selected' : '' }}>
                    {{ $label }}
                  </option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Search Jurisdiction</label>
                <input type="text" name="search_jurisdiction" class="form-control"
                       value="{{ old('search_jurisdiction', $orphanWork->search_jurisdiction ?? 'ZA') }}" placeholder="ISO 3166-1 alpha-2">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Proposed Fee (if any)</label>
                <div class="input-group">
                  <span class="input-group-text">R</span>
                  <input type="number" name="proposed_fee" class="form-control" step="0.01"
                         value="{{ old('proposed_fee', $orphanWork->proposed_fee ?? '') }}">
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Intended Use</label>
              <textarea name="intended_use" class="form-control" rows="3">{{ old('intended_use', $orphanWork->intended_use ?? '') }}</textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3">{{ old('notes', $orphanWork->notes ?? '') }}</textarea>
            </div>
          </div>
        </div>

        <section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
          <a href="{{ route('ext-rights-admin.orphan-works') }}" class="btn atom-btn-outline-light">Cancel</a>
          <button type="submit" class="btn atom-btn-outline-light"><i class="fas fa-save me-1"></i>Save</button>
        </section>
      </form>

      @if(isset($orphanWork))
      {{-- Search Steps --}}
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">Search Steps</h5>
          <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addStepModal">
            <i class="fas fa-plus me-1"></i>Add Step
          </button>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr><th>Date</th><th>Source</th><th>Search Terms</th><th>Results</th></tr>
            </thead>
            <tbody>
              @forelse($searchSteps as $step)
              <tr>
                <td>{{ \Carbon\Carbon::parse($step->search_date)->format('d M Y') }}</td>
                <td>
                  <strong>{{ ucfirst(str_replace('_', ' ', $step->source_type)) }}</strong>
                  <br><small>{{ $step->source_name }}</small>
                  @if($step->source_url)
                    <br><a href="{{ $step->source_url }}" target="_blank" class="small">
                      <i class="fas fa-external-link-alt"></i> Link
                    </a>
                  @endif
                </td>
                <td>{{ $step->search_terms ?: '-' }}</td>
                <td>
                  @if($step->results_found)
                    <span class="badge bg-success">Found</span>
                    @if($step->results_description)
                      <br><small>{{ $step->results_description }}</small>
                    @endif
                  @else
                    <span class="badge bg-secondary">No results</span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="4" class="text-center text-muted py-4">No search steps documented yet.</td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @endif
    </div>

    {{-- Sidebar --}}
    <div class="col-lg-4">
      @if(isset($orphanWork))
      <div class="card mb-4">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">Status</h5>
        </div>
        <div class="card-body">
          @php
            $statusColor = match($orphanWork->status ?? '') {
              'in_progress' => 'warning', 'completed' => 'success', 'rights_holder_found' => 'info', 'abandoned' => 'secondary', default => 'light'
            };
          @endphp
          <p><span class="badge bg-{{ $statusColor }} fs-6">{{ ucfirst(str_replace('_', ' ', $orphanWork->status ?? '')) }}</span></p>

          <dl class="mb-0">
            <dt>Search Started</dt>
            <dd>{{ $orphanWork->search_started_date ? \Carbon\Carbon::parse($orphanWork->search_started_date)->format('d M Y') : '-' }}</dd>
            @if($orphanWork->search_completed_date ?? null)
            <dt>Search Completed</dt>
            <dd>{{ \Carbon\Carbon::parse($orphanWork->search_completed_date)->format('d M Y') }}</dd>
            @endif
          </dl>

          @if(($orphanWork->status ?? '') === 'in_progress')
          <hr>
          <a href="{{ route('ext-rights-admin.complete-orphan-search', $orphanWork->id) }}"
             class="btn btn-success btn-sm w-100 mb-2"
             onclick="return confirm('Mark search as complete (no rights holder found)?');">
            <i class="fas fa-check me-1"></i>Complete Search
          </a>
          <a href="{{ route('ext-rights-admin.complete-orphan-search', ['id' => $orphanWork->id, 'rights_holder_found' => 1]) }}"
             class="btn btn-info btn-sm w-100"
             onclick="return confirm('Mark as rights holder found?');">
            <i class="fas fa-user-check me-1"></i>Rights Holder Found
          </a>
          @endif
        </div>
      </div>
      @endif

      <div class="card">
        <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <h5 class="mb-0">Recommended Sources</h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2"><i class="fas fa-database me-2 text-primary"></i>Copyright registries</li>
            <li class="mb-2"><i class="fas fa-users me-2 text-primary"></i>Author/artist societies</li>
            <li class="mb-2"><i class="fas fa-book me-2 text-primary"></i>Publisher records</li>
            <li class="mb-2"><i class="fas fa-archive me-2 text-primary"></i>Library catalogs</li>
            <li class="mb-2"><i class="fas fa-globe me-2 text-primary"></i>Internet searches</li>
            <li><i class="fas fa-newspaper me-2 text-primary"></i>Newspaper archives</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
@endsection

@if(isset($orphanWork))
{{-- Add Step Modal --}}
@push('modals')
<div class="modal fade" id="addStepModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="{{ route('ext-rights-admin.add-search-step', $orphanWork->id) }}" method="post">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Add Search Step</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Source Type <span class="text-danger">*</span></label>
              <select name="source_type" class="form-select" required>
                @foreach($formOptions['search_source_options'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Search Date <span class="text-danger">*</span></label>
              <input type="date" name="search_date" class="form-control" required value="{{ date('Y-m-d') }}">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Source Name <span class="text-danger">*</span></label>
            <input type="text" name="source_name" class="form-control" required placeholder="e.g., DALRO, SAMRO, National Library">
          </div>
          <div class="mb-3">
            <label class="form-label">Source URL</label>
            <input type="url" name="source_url" class="form-control" placeholder="https://...">
          </div>
          <div class="mb-3">
            <label class="form-label">Search Terms Used</label>
            <input type="text" name="search_terms" class="form-control" placeholder="Keywords, names, titles searched">
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input type="checkbox" name="results_found" class="form-check-input" id="results_found" value="1">
              <label class="form-check-label" for="results_found">Results found</label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Results Description</label>
            <textarea name="results_description" class="form-control" rows="3" placeholder="Describe what was found or document negative result"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Step</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endpush
@endif
