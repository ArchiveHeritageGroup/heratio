@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'odrlPolicies'])
@endsection

@section('title', 'ODRL Policies')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">ODRL Policies</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">ODRL Policies</h1>
    <button class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#createPolicyModal"><i class="fas fa-plus me-1"></i>Create Policy</button>
</div>

<!-- Filter bar -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Target Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="filter_target_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['archival_description', 'collection', 'project', 'snapshot', 'annotation', 'assertion'] as $tt)
                    <option value="{{ $tt }}" {{ request('filter_target_type') === $tt ? 'selected' : '' }}>{{ ucfirst($tt) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Policy Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="filter_policy_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="permission" {{ request('filter_policy_type') === 'permission' ? 'selected' : '' }}>Permission</option>
                    <option value="prohibition" {{ request('filter_policy_type') === 'prohibition' ? 'selected' : '' }}>Prohibition</option>
                    <option value="obligation" {{ request('filter_policy_type') === 'obligation' ? 'selected' : '' }}>Obligation</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Action Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="filter_action_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['use', 'reproduce', 'distribute', 'modify', 'archive', 'display'] as $at)
                    <option value="{{ $at }}" {{ request('filter_action_type') === $at ? 'selected' : '' }}>{{ ucfirst($at) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn atom-btn-outline-light btn-sm">Filter</button></div>
        </form>
    </div>
</div>

@if(empty($policies['items'] ?? []))
    <div class="alert alert-info">No ODRL policies found. Create one to get started.</div>
@else
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Target</th>
                <th>Policy Type</th>
                <th>Action</th>
                <th>Constraints</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($policies['items'] as $p)
            <tr>
                <td>{{ (int) $p->id }}</td>
                <td>
                    <span class="badge bg-secondary">{{ $p->target_type }}</span>
                    #{{ (int) $p->target_id }}
                </td>
                <td>
                    @php
                    $badgeClass = 'bg-info';
                    if ($p->policy_type === 'permission') { $badgeClass = 'bg-success'; }
                    elseif ($p->policy_type === 'prohibition') { $badgeClass = 'bg-danger'; }
                    elseif ($p->policy_type === 'obligation') { $badgeClass = 'bg-warning text-dark'; }
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $p->policy_type }}</span>
                </td>
                <td><code>{{ $p->action_type }}</code></td>
                <td>
                    @if(!empty($p->constraints_json))
                        @php $constraints = json_decode($p->constraints_json, true); @endphp
                        @if(is_array($constraints))
                            @foreach($constraints as $ck => $cv)
                                <small class="d-block text-muted">{{ $ck }}: {{ is_array($cv) ? implode(', ', $cv) : $cv }}</small>
                            @endforeach
                        @else
                            <small class="text-muted">-</small>
                        @endif
                    @else
                        <small class="text-muted">None</small>
                    @endif
                </td>
                <td><small>{{ $p->created_at ?? '' }}</small></td>
                <td>
                    <form method="post" action="{{ url('/research/odrlPolicies') }}" class="d-inline" onsubmit="return confirm('Delete this policy? This cannot be undone.')">
                        @csrf
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="policy_id" value="{{ (int) $p->id }}">
                        <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if(($policies['total'] ?? 0) > 25)
@php $totalPages = ceil($policies['total'] / 25); $currentPage = (int) request('page', 1); @endphp
<nav>
    <ul class="pagination justify-content-center">
        @for($i = 1; $i <= $totalPages; $i++)
        <li class="page-item {{ $i === $currentPage ? 'active' : '' }}">
            <a class="page-link" href="?page={{ $i }}{{ request('filter_target_type') ? '&filter_target_type=' . urlencode(request('filter_target_type')) : '' }}{{ request('filter_policy_type') ? '&filter_policy_type=' . urlencode(request('filter_policy_type')) : '' }}{{ request('filter_action_type') ? '&filter_action_type=' . urlencode(request('filter_action_type')) : '' }}">{{ $i }}</a>
        </li>
        @endfor
    </ul>
</nav>
@endif
@endif

<!-- Create Policy Modal -->
<div class="modal fade" id="createPolicyModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="{{ url('/research/odrlPolicies') }}">
        @csrf
        <input type="hidden" name="form_action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Create ODRL Policy</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Target Type * <span class="badge bg-danger ms-1">Required</span></label>
            <select name="target_type" id="policy-target-type" class="form-select" required>
              <option value="">Select...</option>
              <option value="archival_description">Archival Description</option>
              <option value="collection">Collection</option>
              <option value="project">Project</option>
              <option value="snapshot">Snapshot</option>
              <option value="annotation">Annotation</option>
              <option value="assertion">Assertion</option>
            </select>
          </div>
          <input type="hidden" name="target_id" id="target-id-hidden" required>
          <div class="mb-3" id="target-id-plain">
            <label class="form-label" id="target-id-label">Target ID * <span class="badge bg-danger ms-1">Required</span></label>
            <input type="number" id="target-id-input" class="form-control" required placeholder="Enter the ID of the target">
          </div>
          <div class="mb-3 d-none" id="target-id-autocomplete">
            <label class="form-label">Archival Description * <span class="badge bg-danger ms-1">Required</span></label>
            <select id="target-id-tomselect" placeholder="Type to search archival descriptions..."></select>
          </div>
          <div class="mb-3">
            <label class="form-label">Policy Type * <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="policy_type" class="form-select">
              <option value="permission">Permission</option>
              <option value="prohibition">Prohibition</option>
              <option value="obligation">Obligation</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Action Type * <span class="badge bg-secondary ms-1">Optional</span></label>
            <select name="action_type" class="form-select">
              <option value="use">Use</option>
              <option value="reproduce">Reproduce</option>
              <option value="distribute">Distribute</option>
              <option value="modify">Modify</option>
              <option value="archive">Archive</option>
              <option value="display">Display</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Constraints <span class="badge bg-secondary ms-1">Optional</span></label>
          </div>
          <div class="mb-3">
            <label class="form-label small">Restrict to Researchers</label>
            <select id="constraint-researchers" multiple placeholder="Search researchers..."></select>
            <small class="text-muted">Leave empty to apply to all researchers</small>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label small">Date From</label>
              <input type="date" id="constraint-date-from" class="form-control form-control-sm">
            </div>
            <div class="col-md-6">
              <label class="form-label small">Date To</label>
              <input type="date" id="constraint-date-to" class="form-control form-control-sm">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Max Uses</label>
            <input type="number" id="constraint-max-uses" class="form-control form-control-sm" min="1" placeholder="Unlimited">
          </div>
          <input type="hidden" name="constraints_json" id="constraints-json-hidden">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn atom-btn-outline-success">Create Policy</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('css')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@push('js')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var targetType = document.getElementById('policy-target-type');
    var plainDiv = document.getElementById('target-id-plain');
    var acDiv = document.getElementById('target-id-autocomplete');
    var plainInput = document.getElementById('target-id-input');
    var hiddenInput = document.getElementById('target-id-hidden');
    var acSelect = document.getElementById('target-id-tomselect');
    var tsInstance = null;
    var isAutocomplete = false;

    // Sync plain input → hidden
    plainInput.addEventListener('input', function() {
        if (!isAutocomplete) hiddenInput.value = this.value;
    });

    function initTomSelect() {
        if (tsInstance) return;
        tsInstance = new TomSelect(acSelect, {
            valueField: 'id',
            labelField: 'name',
            searchField: ['name'],
            maxOptions: 20,
            loadThrottle: 300,
            load: function(query, callback) {
                if (query.length < 2) return callback();
                fetch('/informationobject/autocomplete?query=' + encodeURIComponent(query) + '&limit=20')
                    .then(function(r) { return r.json(); })
                    .then(function(data) { callback(data); })
                    .catch(function() { callback(); });
            },
            onChange: function(value) {
                hiddenInput.value = value || '';
            },
            render: {
                option: function(item) {
                    return '<div><strong>' + (item.name || '[Untitled]') + '</strong> <small class="text-muted">#' + item.id + '</small></div>';
                },
                item: function(item) {
                    return '<div>' + (item.name || '[Untitled]') + ' <small>#' + item.id + '</small></div>';
                }
            }
        });
    }

    targetType.addEventListener('change', function() {
        if (this.value === 'archival_description') {
            isAutocomplete = true;
            plainDiv.classList.add('d-none');
            plainInput.removeAttribute('required');
            acDiv.classList.remove('d-none');
            hiddenInput.value = '';
            initTomSelect();
            if (tsInstance) tsInstance.focus();
        } else {
            isAutocomplete = false;
            acDiv.classList.add('d-none');
            plainDiv.classList.remove('d-none');
            plainInput.setAttribute('required', 'required');
            hiddenInput.value = plainInput.value;
        }
    });

    // Initial sync
    hiddenInput.value = plainInput.value;

    // Researcher TomSelect (multi)
    var researcherTs = new TomSelect('#constraint-researchers', {
        valueField: 'id',
        labelField: 'name',
        searchField: ['name', 'email'],
        maxOptions: 20,
        loadThrottle: 300,
        plugins: ['remove_button'],
        load: function(query, callback) {
            if (query.length < 2) return callback();
            fetch('/research/researcher-autocomplete?query=' + encodeURIComponent(query))
                .then(function(r) { return r.json(); })
                .then(function(data) { callback(data); })
                .catch(function() { callback(); });
        },
        render: {
            option: function(item) {
                return '<div><strong>' + item.name + '</strong> <small class="text-muted">' + (item.email || '') + '</small></div>';
            },
            item: function(item) {
                return '<div>' + item.name + '</div>';
            }
        }
    });

    // Build constraints JSON on form submit
    var policyForm = document.querySelector('#createPolicyModal form');
    policyForm.addEventListener('submit', function() {
        var constraints = {};
        var researcherIds = researcherTs.getValue();
        if (researcherIds && researcherIds.length > 0) {
            constraints.researcher_ids = Array.isArray(researcherIds) ? researcherIds.map(Number) : [Number(researcherIds)];
        }
        var df = document.getElementById('constraint-date-from').value;
        if (df) constraints.date_from = df;
        var dt = document.getElementById('constraint-date-to').value;
        if (dt) constraints.date_to = dt;
        var mu = document.getElementById('constraint-max-uses').value;
        if (mu) constraints.max_uses = parseInt(mu);

        document.getElementById('constraints-json-hidden').value = Object.keys(constraints).length > 0 ? JSON.stringify(constraints) : '';
    });
});
</script>
@endpush
@endsection
