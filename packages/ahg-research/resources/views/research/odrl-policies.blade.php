@extends('theme::layouts.2col')

@section('sidebar')
    @include('ahg-research::research._sidebar', ['sidebarActive' => 'odrlPolicies'])
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
                <label class="form-label form-label-sm mb-0">Target Type</label>
                <select name="filter_target_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['collection', 'project', 'snapshot', 'annotation', 'assertion'] as $tt)
                    <option value="{{ $tt }}" {{ request('filter_target_type') === $tt ? 'selected' : '' }}>{{ ucfirst($tt) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Policy Type</label>
                <select name="filter_policy_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="permission" {{ request('filter_policy_type') === 'permission' ? 'selected' : '' }}>Permission</option>
                    <option value="prohibition" {{ request('filter_policy_type') === 'prohibition' ? 'selected' : '' }}>Prohibition</option>
                    <option value="obligation" {{ request('filter_policy_type') === 'obligation' ? 'selected' : '' }}>Obligation</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Action Type</label>
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
            <tr style="background:var(--ahg-primary);color:#fff">
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
            <label class="form-label">Target Type *</label>
            <select name="target_type" class="form-select" required>
              <option value="">Select...</option>
              <option value="collection">Collection</option>
              <option value="project">Project</option>
              <option value="snapshot">Snapshot</option>
              <option value="annotation">Annotation</option>
              <option value="assertion">Assertion</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Target ID *</label>
            <input type="number" name="target_id" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Policy Type *</label>
            <select name="policy_type" class="form-select">
              <option value="permission">Permission</option>
              <option value="prohibition">Prohibition</option>
              <option value="obligation">Obligation</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Action Type *</label>
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
            <label class="form-label">Constraints (JSON, optional)</label>
            <textarea name="constraints_json" class="form-control" rows="3" placeholder='{"date_from": "2026-01-01", "max_uses": 10}'></textarea>
            <small class="text-muted">Keys: researcher_ids (array), date_from, date_to, max_uses</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn atom-btn-outline-success">Create Policy</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
