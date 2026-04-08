@extends('theme::layouts.1col')

@section('title', 'NER Review Dashboard')

@section('content')
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-brain me-2"></i>NER Review Dashboard</h1>
  </div>

  @if($filterObjectId ?? null)
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-filter me-2"></i>Filtered to: <strong>{{ e($filterIo->title ?? 'Object #' . $filterObjectId) }}</strong>
      </div>
      <a href="{{ route('io.ai.review') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times me-1"></i>Clear Filter</a>
    </div>
  @endif

  {{-- Stat Cards --}}
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card bg-warning text-dark">
        <div class="card-body text-center">
          <h2 class="display-4">{{ $pending->sum('pending_count') }}</h2>
          <p class="mb-0">Entities Pending Review</p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card bg-info text-white">
        <div class="card-body text-center">
          <h2 class="display-4">{{ $pending->count() }}</h2>
          <p class="mb-0">Objects to Review</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Objects Table --}}
  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-list-check me-2"></i>Objects with Pending Entities</h5>
    </div>
    <div class="card-body p-0">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th>Object</th>
            <th class="text-center" style="width: 120px">Pending</th>
            <th class="text-center" style="width: 120px">Approved</th>
            <th style="width: 220px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @if($pending->isNotEmpty())
            @foreach($pending as $obj)
              <tr>
                <td>
                  <a href="{{ route('informationobject.show', $obj->slug ?? $obj->id) }}">
                    {{ $obj->title ?? 'Untitled' }}
                  </a>
                </td>
                <td class="text-center">
                  <span class="badge bg-warning text-dark">{{ $obj->pending_count ?? 0 }}</span>
                </td>
                <td class="text-center">
                  @if(($obj->approved_count ?? 0) > 0)
                    <span class="badge bg-success">{{ $obj->approved_count }}</span>
                  @else
                    <span class="badge bg-secondary">0</span>
                  @endif
                </td>
                <td>
                  <div class="btn-group btn-group-sm">
                    <button class="btn atom-btn-white" onclick="reviewObject({{ $obj->id }})" title="Review pending entities">
                      <i class="fas fa-eye me-1"></i>Review
                    </button>
                    @if(($obj->has_pdf ?? false) && ($obj->approved_count ?? 0) > 0)
                      <a href="{{ route('io.ai.extract', ['id' => $obj->id]) }}"
                         class="btn atom-btn-white" title="View PDF with entity highlights">
                        <i class="fas fa-file-pdf"></i>
                      </a>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          @else
            <tr>
              <td colspan="4" class="text-center text-muted py-4">
                No pending entities to review
              </td>
            </tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

</div>

{{-- Review Modal --}}
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-brain me-2"></i>Review Extracted Entities</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="reviewModalBody"></div>
      <div class="modal-footer">
        <div class="me-auto">
          <button class="btn atom-btn-outline-success btn-sm me-1" onclick="document.querySelectorAll('.action-select').forEach(function(s){ for(var i=0;i<s.options.length;i++){ if(s.options[i].value==='create'||s.options[i].value.indexOf('link_')===0){s.selectedIndex=i;break;} } }); alert('All set to Create/Link')">
            <i class="fas fa-check-double me-1"></i>Create All
          </button>
          <button class="btn atom-btn-outline-danger btn-sm" onclick="document.querySelectorAll('.action-select').forEach(function(s){ for(var i=0;i<s.options.length;i++){ if(s.options[i].value==='reject'){s.selectedIndex=i;break;} } }); alert('All set to Reject')">
            <i class="fas fa-times me-1"></i>Reject All
          </button>
        </div>
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn atom-btn-outline-success" onclick="saveAllDecisions()">
          <i class="fas fa-save me-1"></i>Save All Decisions
        </button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
.entity-row { padding: 12px; border-bottom: 1px solid #eee; }
.entity-row:hover { background: #f8f9fa; }
.entity-row:last-child { border-bottom: none; }
.entity-row.processed { opacity: 0.5; background: #d4edda; }
.action-select { min-width: 160px; }
.badge-type { font-size: 0.7rem; }
.entity-value { font-weight: 500; }
.entity-value:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25); }
</style>
@endpush

@push('js')
<script>
var currentObjectId = null;
var entityData = {};

function reviewObject(objectId) {
    currentObjectId = objectId;
    entityData = {};
    var modal = new bootstrap.Modal(document.getElementById('reviewModal'));
    modal.show();
    document.getElementById('reviewModalBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div><p class="mt-2">Loading entities...</p></div>';

    fetch('/ner/entities/' + objectId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                document.getElementById('reviewModalBody').innerHTML = '<div class="alert alert-danger">Error: ' + (data.error || 'Failed to load') + '</div>';
                return;
            }
            renderEntities(data.entities);
        })
        .catch(function(err) {
            document.getElementById('reviewModalBody').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
        });
}

function renderEntities(entities) {
    var html = '';
    var typeConfig = {
        'PERSON': { icon: 'fa-user', color: 'primary', label: 'People', createAction: 'create_actor' },
        'ORG': { icon: 'fa-building', color: 'success', label: 'Organizations', createAction: 'create_actor' },
        'GPE': { icon: 'fa-map-marker-alt', color: 'info', label: 'Places', createAction: 'create_place' },
        'DATE': { icon: 'fa-calendar', color: 'warning', label: 'Dates', createAction: 'create_date' }
    };

    var totalCount = 0;

    for (var type in entities) {
        var items = entities[type];
        if (!items || !items.length) continue;

        var config = typeConfig[type] || { icon: 'fa-tag', color: 'secondary', label: type, createAction: 'create_subject' };

        html += '<div class="mb-4">';
        html += '<h5 class="text-' + config.color + ' border-bottom pb-2"><i class="fas ' + config.icon + ' me-2"></i>' + config.label;
        html += ' <span class="badge bg-' + config.color + '">' + items.length + '</span></h5>';

        for (var i = 0; i < items.length; i++) {
            var entity = items[i];
            var hasExact = entity.exact_matches && entity.exact_matches.length > 0;
            var hasPartial = entity.partial_matches && entity.partial_matches.length > 0;

            // Store entity data for processing
            entityData[entity.id] = {
                type: type,
                value: entity.value,
                createAction: config.createAction,
                exactMatch: hasExact ? entity.exact_matches[0] : null,
                partialMatches: entity.partial_matches || []
            };

            html += '<div class="entity-row" id="entity-' + entity.id + '">';
            html += '<div class="row align-items-center">';

            // Editable value column
            html += '<div class="col-md-5">';
            html += '<div class="input-group input-group-sm">';
            html += '<input type="text" class="form-control entity-value" id="value-' + entity.id + '" value="' + escapeHtml(entity.value) + '" onchange="updateEntityValue(' + entity.id + ')">';
            html += '<button class="btn atom-btn-white" type="button" onclick="resetValue(' + entity.id + ', \'' + escapeHtml(entity.value) + '\')" title="Reset"><i class="fas fa-undo"></i></button>';
            html += '</div>';
            if (hasExact) {
                html += '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Match: ' + entity.exact_matches[0].name + '</small>';
            } else if (hasPartial) {
                html += '<small class="text-warning"><i class="fas fa-info-circle me-1"></i>Similar: ';
                var partialLinks = [];
                for (var j = 0; j < Math.min(entity.partial_matches.length, 3); j++) {
                    var m = entity.partial_matches[j];
                    partialLinks.push('<a href="#" onclick="useMatch(' + entity.id + ', ' + m.id + ', \'' + escapeHtml(m.name) + '\'); return false;">' + m.name + '</a>');
                }
                html += partialLinks.join(', ') + '</small>';
            }
            html += '</div>';

            // Type selector column
            html += '<div class="col-md-2">';
            html += '<select class="form-select form-select-sm type-select" id="type-' + entity.id + '" onchange="updateEntityType(' + entity.id + ')">';
            html += '<option value="PERSON"' + (type === 'PERSON' ? ' selected' : '') + '>Person</option>';
            html += '<option value="ORG"' + (type === 'ORG' ? ' selected' : '') + '>Organization</option>';
            html += '<option value="GPE"' + (type === 'GPE' ? ' selected' : '') + '>Place</option>';
            html += '<option value="DATE"' + (type === 'DATE' ? ' selected' : '') + '>Date/Subject</option>';
            html += '</select>';
            html += '</div>';

            // Action dropdown column
            html += '<div class="col-md-5">';
            html += '<select class="form-select form-select-sm action-select" id="action-' + entity.id + '">';
            html += '<option value="">-- Select Action --</option>';

            if (hasExact) {
                html += '<option value="link_' + entity.exact_matches[0].id + '" selected>Link to: ' + entity.exact_matches[0].name + '</option>';
            } else if (type === 'DATE') {
                // Check if compound date (contains ; or ,)
                var isCompound = entity.value && (entity.value.indexOf(';') > -1 || (entity.value.match(/,/g) || []).length > 1);
                var dateCount = isCompound ? entity.value.split(/[;,]/).filter(function(d) { return d.trim(); }).length : 1;
                if (isCompound) {
                    html += '<option value="create_date_split" selected>Create ' + dateCount + ' Date Events (split)</option>';
                    html += '<option value="create_date_single">Create 1 Date Event (combined)</option>';
                } else {
                    html += '<option value="create_date" selected>Create Date Event</option>';
                }
            } else {
                var createLabel = type === 'PERSON' || type === 'ORG' ? 'Create Actor' : (type === 'GPE' ? 'Create Place' : 'Create Subject');
                html += '<option value="create" selected>' + createLabel + ' & Link</option>';
            }

            // Add partial matches as link options
            if (hasPartial) {
                for (var j = 0; j < entity.partial_matches.length; j++) {
                    var match = entity.partial_matches[j];
                    html += '<option value="link_' + match.id + '">Link to: ' + match.name + '</option>';
                }
            }

            html += '<option value="approve">Approve (no link)</option>';
            html += '<option value="reject">Reject</option>';
            html += '</select>';
            html += '</div>';

            html += '</div>'; // row
            html += '</div>'; // entity-row

            totalCount++;
        }

        html += '</div>';
    }

    if (!html) {
        html = '<div class="alert alert-info">No pending entities to review.</div>';
    } else {
        html = '<div class="alert alert-info mb-3"><i class="fas fa-info-circle me-2"></i>' +
               '<strong>Edit entities:</strong> Change the name or type before saving. Select action for each, then click <strong>Save All</strong>.</div>' + html;
    }

    document.getElementById('reviewModalBody').innerHTML = html;
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function updateEntityValue(entityId) {
    var newValue = document.getElementById('value-' + entityId).value.trim();
    if (entityData[entityId]) {
        entityData[entityId].editedValue = newValue;
    }
}

function updateEntityType(entityId) {
    var newType = document.getElementById('type-' + entityId).value;
    if (entityData[entityId]) {
        entityData[entityId].editedType = newType;
        // Update create action based on type
        var typeConfig = {
            'PERSON': 'create_actor',
            'ORG': 'create_actor',
            'GPE': 'create_place',
            'DATE': 'create_subject'
        };
        entityData[entityId].createAction = typeConfig[newType] || 'create_subject';

        // Update action dropdown label
        var actionSelect = document.getElementById('action-' + entityId);
        for (var i = 0; i < actionSelect.options.length; i++) {
            if (actionSelect.options[i].value === 'create') {
                var createLabel = newType === 'PERSON' || newType === 'ORG' ? 'Create Actor' : (newType === 'GPE' ? 'Create Place' : 'Create Subject');
                actionSelect.options[i].text = createLabel + ' & Link';
                break;
            }
        }
    }
}

function resetValue(entityId, originalValue) {
    document.getElementById('value-' + entityId).value = originalValue;
    if (entityData[entityId]) {
        delete entityData[entityId].editedValue;
    }
}

function useMatch(entityId, matchId, matchName) {
    var actionSelect = document.getElementById('action-' + entityId);
    // Check if this option exists
    for (var i = 0; i < actionSelect.options.length; i++) {
        if (actionSelect.options[i].value === 'link_' + matchId) {
            actionSelect.selectedIndex = i;
            return;
        }
    }
    // Add new option if doesn't exist
    var opt = document.createElement('option');
    opt.value = 'link_' + matchId;
    opt.text = 'Link to: ' + matchName;
    opt.selected = true;
    actionSelect.add(opt);
}

function saveAllDecisions() {
    var decisions = [];
    var selects = document.querySelectorAll('.action-select');

    selects.forEach(function(select) {
        var entityId = select.id.replace('action-', '');
        var action = select.value;

        if (!action) return;

        var decision = {
            entity_id: entityId,
            action: action
        };

        // Get edited value if any
        var valueInput = document.getElementById('value-' + entityId);
        if (valueInput && entityData[entityId]) {
            var currentValue = valueInput.value.trim();
            if (currentValue !== entityData[entityId].value) {
                decision.edited_value = currentValue;
            }
        }

        // Get edited type if any
        var typeSelect = document.getElementById('type-' + entityId);
        if (typeSelect && entityData[entityId]) {
            var currentType = typeSelect.value;
            if (currentType !== entityData[entityId].type) {
                decision.edited_type = currentType;
            }
        }

        // Parse link actions
        if (action.startsWith('link_')) {
            decision.action = 'link';
            decision.target_id = action.replace('link_', '');
        } else if (action === 'create_date_split') {
            decision.action = 'create_date';
            decision.split_dates = true;
        } else if (action === 'create_date_single' || action === 'create_date') {
            decision.action = 'create_date';
            decision.split_dates = false;
        } else if (action === 'create') {
            decision.action = 'create';
            decision.create_type = entityData[entityId] ? entityData[entityId].createAction : 'create_subject';
            decision.entity_type = entityData[entityId] ? (entityData[entityId].editedType || entityData[entityId].type) : 'UNKNOWN';
        }

        decisions.push(decision);
    });

    if (decisions.length === 0) {
        alert('No actions selected');
        return;
    }

    bulkSaveDecisions(decisions);
}

function bulkSaveDecisions(decisions) {
    var batchSize = 3;  // Process 3 at a time
    var batches = [];

    // Split into batches
    for (var i = 0; i < decisions.length; i += batchSize) {
        batches.push(decisions.slice(i, i + batchSize));
    }

    var results = { success: 0, failed: 0, errors: [] };
    processBatch(batches, 0, results, decisions.length);
}

function processBatch(batches, batchIndex, results, total) {
    var processed = batchIndex * 3;
    var progress = Math.round((processed / total) * 100);

    // Update progress display
    document.getElementById('reviewModalBody').innerHTML =
        '<div class="text-center py-4">' +
        '<div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>' +
        '<h5>Processing Entities...</h5>' +
        '<div class="progress my-3" style="height: 25px;">' +
        '<div class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: ' + progress + '%">' + progress + '%</div>' +
        '</div>' +
        '<p class="text-muted mb-1">' + processed + ' of ' + total + ' processed</p>' +
        '<p class="small"><span class="text-success">' + results.success + ' succeeded</span> | <span class="text-danger">' + results.failed + ' failed</span></p>' +
        '</div>';

    if (batchIndex >= batches.length) {
        // All done
        setTimeout(function() {
            document.getElementById('reviewModalBody').innerHTML =
                '<div class="text-center py-5">' +
                '<i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>' +
                '<h4 class="mt-3">Processing Complete</h4>' +
                '<p class="text-muted">' + results.success + ' succeeded, ' + results.failed + ' failed</p>' +
                (results.errors.length > 0 ? '<details class="text-start"><summary class="text-danger">Show errors</summary><pre class="small bg-light p-2 mt-2">' + results.errors.join('\n') + '</pre></details>' : '') +
                '<button class="btn atom-btn-white mt-3" onclick="location.reload()">Refresh Dashboard</button>' +
                '</div>';
        }, 300);
        return;
    }

    var batch = batches[batchIndex];
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('/ner/bulk-save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ decisions: batch })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.results) {
            results.success += data.results.success || 0;
            results.failed += data.results.failed || 0;
            if (data.results.errors) {
                results.errors = results.errors.concat(data.results.errors);
            }
        }
        // Small delay to prevent MySQL locks
        setTimeout(function() {
            processBatch(batches, batchIndex + 1, results, total);
        }, 200);
    })
    .catch(function(err) {
        results.failed += batch.length;
        results.errors.push('Batch ' + (batchIndex + 1) + ': ' + err.message);
        setTimeout(function() {
            processBatch(batches, batchIndex + 1, results, total);
        }, 200);
    });
}
</script>
@endpush
