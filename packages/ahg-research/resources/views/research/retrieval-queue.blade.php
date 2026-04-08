@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'retrievalQueue'])@endsection
@section('title', 'Material Retrieval Queue')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{!! session('success') !!}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<h1 class="mb-4"><i class="fas fa-boxes-stacked me-2"></i>Material Retrieval Queue</h1>

{{-- Dashboard Summary Cards --}}
@php
    $allRequests = $requests ?? [];
    $statusCounts = [
        'requested' => ['name' => 'Requested', 'icon' => 'clock', 'color' => '#ffc107', 'count' => collect($allRequests)->where('status', 'requested')->count()],
        'in_transit' => ['name' => 'In Transit', 'icon' => 'truck', 'color' => '#17a2b8', 'count' => collect($allRequests)->where('status', 'in_transit')->count()],
        'delivered' => ['name' => 'Delivered', 'icon' => 'check-circle', 'color' => '#28a745', 'count' => collect($allRequests)->whereIn('status', ['delivered'])->count()],
        'in_use' => ['name' => 'In Use', 'icon' => 'book-open', 'color' => '#007bff', 'count' => collect($allRequests)->where('status', 'in_use')->count()],
        'returned' => ['name' => 'Returned', 'icon' => 'undo', 'color' => '#6c757d', 'count' => collect($allRequests)->where('status', 'returned')->count()],
        'unavailable' => ['name' => 'Unavailable', 'icon' => 'ban', 'color' => '#dc3545', 'count' => collect($allRequests)->where('status', 'unavailable')->count()],
    ];
@endphp
<div class="row mb-4">
    @foreach($statusCounts as $code => $q)
    <div class="col-md-2 col-sm-4 mb-3">
        <a href="?status={{ $code }}" class="card text-decoration-none h-100 {{ request('status') === $code ? 'border-primary border-2' : '' }}">
            <div class="card-body text-center py-3">
                <i class="fas fa-{{ $q['icon'] }} fa-2x mb-2" style="color:{{ $q['color'] }}"></i>
                <h3 class="mb-0">{{ $q['count'] }}</h3>
                <small class="text-muted">{{ $q['name'] }}</small>
            </div>
        </a>
    </div>
    @endforeach
</div>

{{-- Queue Table --}}
@php
    $filtered = collect($allRequests);
    if (request('status')) $filtered = $filtered->where('status', request('status'));
    $filtered = $filtered->values();
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            @if(request('status'))
                {{ ucfirst(str_replace('_', ' ', request('status'))) }} Requests ({{ $filtered->count() }})
            @else
                All Requests ({{ $filtered->count() }})
            @endif
        </h5>
        <div>
            @if(request('status'))
                <a href="{{ route('research.retrievalQueue') }}" class="btn btn-sm btn-outline-secondary me-1">Show All</a>
            @endif
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print List</button>
        </div>
    </div>
    <div class="card-body p-0">
        @if($filtered->count() > 0)
        <form method="POST" id="queueForm">
            @csrf
            <input type="hidden" name="form_action" value="batch_update">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>Request</th>
                            <th>Item</th>
                            <th>Location</th>
                            <th>Researcher</th>
                            <th>Booking</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($filtered as $req)
                        <tr>
                            <td><input type="checkbox" name="request_ids[]" value="{{ $req->id }}" class="form-check-input request-cb"></td>
                            <td><strong>#{{ $req->id }}</strong></td>
                            <td>
                                <div class="fw-medium">{{ e($req->object_title ?? 'Untitled') }}</div>
                                @if($req->call_number ?? null)<small class="text-muted">{{ e($req->call_number) }}</small>@endif
                            </td>
                            <td>
                                @if($req->shelf_location ?? null)
                                    <small>{{ e($req->shelf_location) }}
                                    @if($req->box_number ?? null)<br>Box: {{ e($req->box_number) }}@endif
                                    @if($req->folder_number ?? null) / Folder: {{ e($req->folder_number) }}@endif
                                    </small>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ e(($req->first_name ?? '') . ' ' . ($req->last_name ?? '')) }}</td>
                            <td>
                                {{ date('M j', strtotime($req->booking_date)) }}
                                <br><small>{{ substr($req->start_time ?? '', 0, 5) }}</small>
                            </td>
                            <td>
                                @php $pc = ['rush'=>'danger','high'=>'warning']; @endphp
                                <span class="badge bg-{{ $pc[$req->priority ?? ''] ?? 'secondary' }}">
                                    <i class="fas fa-{{ match($req->priority ?? '') { 'rush' => 'bolt', 'high' => 'arrow-up', default => 'minus' } }} me-1"></i>{{ ucfirst($req->priority ?? 'normal') }}
                                </span>
                            </td>
                            <td>
                                @php $sc = ['requested'=>'warning','in_transit'=>'info','delivered'=>'success','in_use'=>'primary','returned'=>'secondary','unavailable'=>'danger']; @endphp
                                <span class="badge bg-{{ $sc[$req->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $req->status)) }}</span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($req->status === 'requested')
                                        <button type="button" class="btn btn-outline-info action-btn" data-id="{{ $req->id }}" data-action="mark_in_transit" title="In Transit"><i class="fas fa-truck"></i></button>
                                    @endif
                                    @if($req->status === 'in_transit')
                                        <button type="button" class="btn btn-outline-success action-btn" data-id="{{ $req->id }}" data-action="mark_delivered" title="Delivered"><i class="fas fa-check"></i></button>
                                    @endif
                                    @if(in_array($req->status, ['delivered', 'in_use']))
                                        <button type="button" class="btn btn-outline-secondary action-btn" data-id="{{ $req->id }}" data-action="mark_returned" title="Return"><i class="fas fa-undo"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Batch Actions --}}
            <div class="card bg-light m-3">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small">Update Status</label>
                            <select name="new_status" class="form-select form-select-sm">
                                <option value="">-- Select --</option>
                                <option value="requested">Requested</option>
                                <option value="in_transit">In Transit</option>
                                <option value="delivered">Delivered</option>
                                <option value="in_use">In Use</option>
                                <option value="returned">Returned</option>
                                <option value="unavailable">Unavailable</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Notes</label>
                            <input type="text" name="batch_notes" class="form-control form-control-sm" placeholder="Optional notes">
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex flex-wrap gap-1">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Update Selected</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        @else
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-dolly fa-3x mb-3"></i>
            <p>No requests {{ request('status') ? 'with status "' . ucfirst(str_replace('_', ' ', request('status'))) . '"' : 'in the queue' }}.</p>
        </div>
        @endif
    </div>
</div>

{{-- Hidden form for individual actions (outside the batch form) --}}
<form method="POST" id="singleActionForm" style="display:none;">
    @csrf
    <input type="hidden" name="form_action" id="singleActionType">
    <input type="hidden" name="request_id" id="singleActionId">
</form>

@push('js')
<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.request-cb').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});

document.querySelectorAll('.action-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('singleActionType').value = this.dataset.action;
        document.getElementById('singleActionId').value = this.dataset.id;
        document.getElementById('singleActionForm').submit();
    });
});
</script>
@endpush
@endsection
