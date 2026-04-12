@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'entityResolution'])
@endsection

@section('title', 'Entity Resolution')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Entity Resolution</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Entity Resolution</h1>
    <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#proposeMatchModal"><i class="fas fa-plus me-1"></i>Propose Match</button>
</div>

<!-- Filter bar -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Status <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="proposed" {{ request('status') === 'proposed' ? 'selected' : '' }}>Proposed</option>
                    <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Entity Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="entity_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="actor" {{ request('entity_type') === 'actor' ? 'selected' : '' }}>Actor</option>
                    <option value="information_object" {{ request('entity_type') === 'information_object' ? 'selected' : '' }}>Information Object</option>
                    <option value="repository" {{ request('entity_type') === 'repository' ? 'selected' : '' }}>Repository</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Relationship <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="relationship_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="sameAs" {{ request('relationship_type') === 'sameAs' ? 'selected' : '' }}>sameAs</option>
                    <option value="relatedTo" {{ request('relationship_type') === 'relatedTo' ? 'selected' : '' }}>relatedTo</option>
                    <option value="partOf" {{ request('relationship_type') === 'partOf' ? 'selected' : '' }}>partOf</option>
                    <option value="memberOf" {{ request('relationship_type') === 'memberOf' ? 'selected' : '' }}>memberOf</option>
                </select>
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button></div>
        </form>
    </div>
</div>

@if(empty($proposals['items'] ?? []))
    <div class="alert alert-info">No entity resolution proposals matching your filters.</div>
@else
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead>
            <tr>
                <th>Entity A</th>
                <th></th>
                <th>Entity B</th>
                <th>Relationship</th>
                <th>Confidence</th>
                <th>Method</th>
                <th>Evidence</th>
                <th>Resolver</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($proposals['items'] as $p)
            <tr>
                <td>
                    <strong>{{ e($p->entity_a_label ?? ($p->entity_a_type . ':' . $p->entity_a_id)) }}</strong>
                    <br><small class="text-muted">{{ $p->entity_a_type }} #{{ (int) $p->entity_a_id }}</small>
                </td>
                <td><i class="fas fa-exchange-alt text-muted"></i></td>
                <td>
                    <strong>{{ e($p->entity_b_label ?? ($p->entity_b_type . ':' . $p->entity_b_id)) }}</strong>
                    <br><small class="text-muted">{{ $p->entity_b_type }} #{{ (int) $p->entity_b_id }}</small>
                </td>
                <td><span class="badge bg-info">{{ $p->relationship_type ?? 'sameAs' }}</span></td>
                <td>
                    @if($p->confidence !== null)
                    @php $pct = round((float)$p->confidence * 100); $color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'); @endphp
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress" style="width:60px;height:6px">
                            <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                        </div>
                        <small>{{ $pct }}%</small>
                    </div>
                    @else - @endif
                </td>
                <td><small class="text-muted">{{ $p->match_method ?? '-' }}</small></td>
                <td>
                    @if(!empty($p->evidence))
                        <button class="btn btn-sm btn-outline-secondary evidence-btn" data-evidence="{{ e(json_encode($p->evidence)) }}" title="View evidence"><i class="fas fa-file-alt"></i> {{ count($p->evidence) }}</button>
                    @else
                        <small class="text-muted">-</small>
                    @endif
                </td>
                <td>
                    @if(!empty($p->resolver_first_name))
                        <small>{{ $p->resolver_first_name }} {{ $p->resolver_last_name }}</small>
                    @else
                        <small class="text-muted">-</small>
                    @endif
                </td>
                <td><span class="badge bg-{{ match($p->status) { 'proposed' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', default => 'secondary' } }}">{{ ucfirst($p->status) }}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        @if($p->status === 'proposed')
                        <button class="btn btn-outline-secondary check-conflicts-btn" data-id="{{ (int) $p->id }}" title="Check conflicts"><i class="fas fa-exclamation-triangle"></i></button>
                        <button class="btn btn-success resolve-btn" data-id="{{ (int) $p->id }}" data-status="accepted" title="Accept"><i class="fas fa-check"></i></button>
                        <button class="btn btn-outline-danger resolve-btn" data-id="{{ (int) $p->id }}" data-status="rejected" title="Reject"><i class="fas fa-times"></i></button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- Propose Match Modal -->
<div class="modal fade" id="proposeMatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Propose Entity Match</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form method="post" action="{{ url('/research/entityResolution') }}" id="proposeForm">
                    @csrf
                    <input type="hidden" name="form_action" value="propose">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Entity A</h6>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                                <select name="entity_a_type" class="form-select form-select-sm">
                                    <option value="actor">Actor</option>
                                    <option value="information_object">Information Object</option>
                                    <option value="repository">Repository</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Entity A ID * <span class="badge bg-danger ms-1">Required</span></label>
                                <input type="number" name="entity_a_id" class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Entity B</h6>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                                <select name="entity_b_type" class="form-select form-select-sm">
                                    <option value="actor">Actor</option>
                                    <option value="information_object">Information Object</option>
                                    <option value="repository">Repository</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label form-label-sm">Entity B ID * <span class="badge bg-danger ms-1">Required</span></label>
                                <input type="number" name="entity_b_id" class="form-control form-control-sm" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Relationship Type <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="relationship_type" class="form-select form-select-sm">
                            <option value="sameAs">sameAs (identical entities)</option>
                            <option value="relatedTo">relatedTo (associated entities)</option>
                            <option value="partOf">partOf (hierarchical)</option>
                            <option value="memberOf">memberOf (group membership)</option>
                        </select>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Match Method <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select name="match_method" class="form-select form-select-sm">
                                <option value="manual">Manual</option>
                                <option value="name_similarity">Name Similarity</option>
                                <option value="identifier_match">Identifier Match</option>
                                <option value="authority_record">Authority Record</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Confidence (0-1) <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="number" name="confidence" class="form-control form-control-sm" min="0" max="1" step="0.01" value="0.8">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">{{ __('Evidence') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                        <textarea name="evidence" class="form-control form-control-sm font-monospace" rows="3"
                                  placeholder="authority_record | 123 | VIAF match confirmed&#10;document | 456 | Cross-reference in finding aid"></textarea>
                        <small class="text-muted">{{ __('One line per evidence item: type | id | description') }}</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="proposeForm" class="btn btn-success">Propose Match</button>
            </div>
        </div>
    </div>
</div>

<!-- Evidence Preview Modal -->
<div class="modal fade" id="evidenceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Evidence</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="evidenceBody"></div>
        </div>
    </div>
</div>

<!-- Conflict Warning Modal -->
<div class="modal fade" id="conflictModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Conflict Check</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="conflictBody"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.querySelectorAll('.resolve-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var status = this.dataset.status;
            if (status === 'rejected') {
                var reason = prompt('Rejection reason:');
                if (reason === null) return;
            }
            fetch('/research/entity-resolution/' + this.dataset.id + '/resolve', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify({status: status})
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success) location.reload();
                else alert(d.error || 'Error');
            });
        });
    });

    document.querySelectorAll('.evidence-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var evidence = JSON.parse(this.dataset.evidence);
            var html = '<table class="table table-bordered table-sm"><thead><tr><th>Source Type</th><th>Source ID</th><th>Note</th></tr></thead><tbody>';
            evidence.forEach(function(e) {
                html += '<tr><td>' + (e.source_type || '-') + '</td><td>' + (e.source_id || '-') + '</td><td>' + (e.note || '-') + '</td></tr>';
            });
            html += '</tbody></table>';
            document.getElementById('evidenceBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('evidenceModal')).show();
        });
    });

    document.querySelectorAll('.check-conflicts-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var resolutionId = this.dataset.id;
            document.getElementById('conflictBody').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Checking...</div>';
            new bootstrap.Modal(document.getElementById('conflictModal')).show();
            fetch('/research/entity-resolution/' + resolutionId + '/conflicts', {
                method: 'GET', headers: {'X-CSRF-TOKEN': csrfToken}
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.conflicts && d.conflicts.length > 0) {
                    var html = '<div class="alert alert-warning"><strong>' + d.conflicts.length + ' conflicting assertion(s) found:</strong></div><ul class="list-group">';
                    d.conflicts.forEach(function(c) {
                        html += '<li class="list-group-item"><strong>' + (c.predicate || 'unknown') + '</strong> — ' + (c.subject_type || '') + ' #' + (c.subject_id || '') + ' → ' + (c.object_type || '') + ' #' + (c.object_id || '') + ' <span class="badge bg-' + (c.status === 'accepted' ? 'success' : 'warning') + '">' + (c.status || '') + '</span></li>';
                    });
                    html += '</ul>';
                    document.getElementById('conflictBody').innerHTML = html;
                } else {
                    document.getElementById('conflictBody').innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>No conflicting assertions found. Safe to accept.</div>';
                }
            });
        });
    });
});
</script>
@endsection
