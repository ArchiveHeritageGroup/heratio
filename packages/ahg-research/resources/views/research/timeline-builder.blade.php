{{-- Timeline Builder — cloned from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Timeline</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">{{ __('Timeline Builder') }}</h1>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#autoPopulateModal"><i class="fas fa-magic me-1"></i>Auto-populate</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal"><i class="fas fa-plus me-1"></i> Add Event</button>
    </div>
</div>

{{-- vis-timeline container --}}
<div class="card mb-4">
    <div class="card-body p-0">
        <div id="timeline" style="width:100%; height:400px;"></div>
    </div>
</div>

{{-- Events table --}}
<div class="card">
    <div class="card-header"><h5 class="mb-0">Events ({{ count($events) }})</h5></div>
    <div class="card-body">
        @if(empty($events))
            <p class="text-muted">No events yet.</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>{{ __('Label') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Type') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($events as $ev)
                    <tr>
                        <td>{{ e($ev->label ?? '') }}</td>
                        <td>{{ $ev->date_start ?? '' }}</td>
                        <td>{{ $ev->date_end ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark">{{ e($ev->date_type ?? '') }}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary edit-event-btn"
                                data-id="{{ $ev->id }}"
                                data-label="{{ e($ev->label ?? '') }}"
                                data-desc="{{ e($ev->description ?? '') }}"
                                data-start="{{ e($ev->date_start ?? '') }}"
                                data-end="{{ e($ev->date_end ?? '') }}"
                                data-type="{{ e($ev->date_type ?? '') }}"
                                title="{{ __('Edit') }}"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger delete-event-btn" data-id="{{ $ev->id }}" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Add Event Modal --}}
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('Add Event') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Label <span class="text-danger">*</span></label><input type="text" id="eventLabel" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea id="eventDesc" class="form-control" rows="2"></textarea></div>
                <div class="row mb-3">
                    <div class="col"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" id="eventStart" class="form-control" required></div>
                    <div class="col"><label class="form-label">{{ __('End Date') }}</label><input type="date" id="eventEnd" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label">{{ __('Type') }}</label>
                    <select id="eventType" class="form-select">
                        <option value="event">{{ __('Event') }}</option>
                        <option value="creation">{{ __('Creation') }}</option>
                        <option value="accession">{{ __('Accession') }}</option>
                        <option value="publication">{{ __('Publication') }}</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="button" class="btn btn-primary" id="saveEvent">{{ __('Save') }}</button></div>
        </div>
    </div>
</div>

{{-- Edit Event Modal --}}
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('Edit Event') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="editEventId">
                <div class="mb-3"><label class="form-label">Label <span class="text-danger">*</span></label><input type="text" id="editEventLabel" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea id="editEventDesc" class="form-control" rows="2"></textarea></div>
                <div class="row mb-3">
                    <div class="col"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" id="editEventStart" class="form-control" required></div>
                    <div class="col"><label class="form-label">{{ __('End Date') }}</label><input type="date" id="editEventEnd" class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label">{{ __('Type') }}</label>
                    <select id="editEventType" class="form-select">
                        <option value="event">{{ __('Event') }}</option>
                        <option value="creation">{{ __('Creation') }}</option>
                        <option value="accession">{{ __('Accession') }}</option>
                        <option value="publication">{{ __('Publication') }}</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="button" class="btn btn-primary" id="updateEvent">{{ __('Update') }}</button></div>
        </div>
    </div>
</div>

{{-- Auto-populate Modal --}}
<div class="modal fade" id="autoPopulateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('Auto-populate from Collection') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-muted">Select an evidence set (collection) to auto-generate timeline events from its item dates.</p>
                <div class="mb-3"><label class="form-label">{{ __('Collection') }}</label><select id="autoCollectionId"></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="button" class="btn btn-primary" id="autoPopulateBtn">{{ __('Populate') }}</button></div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vis-timeline@7/dist/vis-timeline-graph2d.min.css">
<script src="https://cdn.jsdelivr.net/npm/vis-timeline@7/dist/vis-timeline-graph2d.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var projectId = {{ (int) $project->id }};
    var apiUrl = '{{ route("research.timelineBuilder", $project->id) }}';

    // vis-timeline
    var container = document.getElementById('timeline');
    if (typeof vis !== 'undefined') {
        @php
            $visItems = array_map(function($ev) {
                return ['id' => $ev->id, 'content' => $ev->label, 'start' => $ev->date_start, 'end' => $ev->date_end ?? null, 'style' => ($ev->color ?? null) ? 'background-color:' . $ev->color : ''];
            }, $events);
        @endphp
        var items = new vis.DataSet({!! json_encode($visItems) !!});
        var timeline = new vis.Timeline(container, items, {
            editable: {add: false, updateTime: true, updateGroup: false, remove: false},
            onMove: function(item, callback) {
                var startStr = item.start instanceof Date ? item.start.toISOString().split('T')[0] : item.start;
                var endStr = item.end ? (item.end instanceof Date ? item.end.toISOString().split('T')[0] : item.end) : null;
                // POST update via form
                var form = document.createElement('form');
                form.method = 'POST'; form.action = apiUrl; form.style.display = 'none';
                form.innerHTML = '<input name="_token" value="{{ csrf_token() }}"><input name="form_action" value="update_event"><input name="event_id" value="'+item.id+'"><input name="date_start" value="'+startStr+'">' + (endStr ? '<input name="date_end" value="'+endStr+'">' : '');
                document.body.appendChild(form); form.submit();
            }
        });
    } else {
        container.innerHTML = '<p class="text-muted p-3">Timeline library failed to load.</p>';
    }

    // TomSelect for auto-populate collection
    var tsInit = false;
    document.getElementById('autoPopulateModal').addEventListener('shown.bs.modal', function() {
        if (!tsInit && typeof TomSelect !== 'undefined') {
            new TomSelect('#autoCollectionId', {
                valueField: 'id', labelField: 'name', searchField: ['name'],
                placeholder: 'Type to search collections...',
                maxItems: 1,
                load: function(query, callback) {
                    if (!query.length || query.length < 2) return callback();
                    fetch('{{ url("informationobject/autocomplete") }}?query=' + encodeURIComponent(query) + '&limit=15')
                        .then(function(r) { return r.json(); })
                        .then(function(data) { callback(data); })
                        .catch(function() { callback(); });
                },
                render: {
                    option: function(d, escape) { return '<div>' + escape(d.name) + (d.slug ? '<small class="text-muted ms-2">' + escape(d.slug) + '</small>' : '') + '</div>'; },
                    item: function(d, escape) { return '<div>' + escape(d.name) + '</div>'; }
                }
            });
            tsInit = true;
        }
    });

    // Create event — POST form submit
    document.getElementById('saveEvent')?.addEventListener('click', function() {
        var form = document.createElement('form');
        form.method = 'POST'; form.action = apiUrl; form.style.display = 'none';
        form.innerHTML = '<input name="_token" value="{{ csrf_token() }}">'
            + '<input name="title" value="' + document.getElementById('eventLabel').value + '">'
            + '<input name="description" value="' + document.getElementById('eventDesc').value + '">'
            + '<input name="event_date" value="' + document.getElementById('eventStart').value + '">'
            + '<input name="date_end" value="' + document.getElementById('eventEnd').value + '">'
            + '<input name="event_type" value="' + document.getElementById('eventType').value + '">';
        document.body.appendChild(form); form.submit();
    });

    // Edit event — open modal
    document.querySelectorAll('.edit-event-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editEventId').value = this.dataset.id;
            document.getElementById('editEventLabel').value = this.dataset.label;
            document.getElementById('editEventDesc').value = this.dataset.desc;
            document.getElementById('editEventStart').value = this.dataset.start;
            document.getElementById('editEventEnd').value = this.dataset.end;
            document.getElementById('editEventType').value = this.dataset.type || 'event';
            new bootstrap.Modal(document.getElementById('editEventModal')).show();
        });
    });

    // Update event — POST form submit
    document.getElementById('updateEvent')?.addEventListener('click', function() {
        var form = document.createElement('form');
        form.method = 'POST'; form.action = apiUrl; form.style.display = 'none';
        form.innerHTML = '<input name="_token" value="{{ csrf_token() }}">'
            + '<input name="form_action" value="update_event">'
            + '<input name="event_id" value="' + document.getElementById('editEventId').value + '">'
            + '<input name="label" value="' + document.getElementById('editEventLabel').value + '">'
            + '<input name="description" value="' + document.getElementById('editEventDesc').value + '">'
            + '<input name="date_start" value="' + document.getElementById('editEventStart').value + '">'
            + '<input name="date_end" value="' + document.getElementById('editEventEnd').value + '">'
            + '<input name="date_type" value="' + document.getElementById('editEventType').value + '">';
        document.body.appendChild(form); form.submit();
    });

    // Delete event
    document.querySelectorAll('.delete-event-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Delete this event?')) return;
            var form = document.createElement('form');
            form.method = 'POST'; form.action = apiUrl; form.style.display = 'none';
            form.innerHTML = '<input name="_token" value="{{ csrf_token() }}"><input name="form_action" value="delete_event"><input name="event_id" value="' + this.dataset.id + '">';
            document.body.appendChild(form); form.submit();
        });
    });

    // Auto-populate
    document.getElementById('autoPopulateBtn')?.addEventListener('click', function() {
        var cid = document.getElementById('autoCollectionId').value;
        if (!cid) { alert('Select a collection first'); return; }
        this.disabled = true; this.textContent = 'Populating...';
        var form = document.createElement('form');
        form.method = 'POST'; form.action = apiUrl; form.style.display = 'none';
        form.innerHTML = '<input name="_token" value="{{ csrf_token() }}"><input name="form_action" value="auto_populate"><input name="collection_id" value="' + cid + '">';
        document.body.appendChild(form); form.submit();
    });
});
</script>
@endsection
