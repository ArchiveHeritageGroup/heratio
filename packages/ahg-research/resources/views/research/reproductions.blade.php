@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-copy me-2"></i>Reproduction Requests</h1>@endsection
@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <form method="GET" class="d-inline-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </form>
    </div>
    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#newRequestModal"><i class="fas fa-plus me-1"></i>New Request</button>
</div>

@if(count($requests) > 0)
<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Item</th>
                <th>Type</th>
                <th>Status</th>
                <th>Requested</th>
                <th>Cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($requests as $req)
            <tr>
                <td><span class="text-muted">#{{ $req->id }}</span></td>
                <td>
                    @if($req->item_title ?? null)
                        {{ e($req->item_title) }}
                    @elseif($req->object_id ?? null)
                        Item #{{ $req->object_id }}
                    @else
                        -
                    @endif
                </td>
                <td>
                    @php
                        $typeIcons = ['photocopy' => 'fa-copy', 'scan' => 'fa-scanner', 'photograph' => 'fa-camera', 'digital' => 'fa-laptop'];
                        $icon = $typeIcons[$req->reproduction_type ?? ''] ?? 'fa-file';
                    @endphp
                    <i class="fas {{ $icon }} me-1"></i>{{ ucfirst($req->reproduction_type ?? 'Unknown') }}
                </td>
                <td>
                    @php
                        $statusColors = ['pending' => 'warning', 'approved' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary'];
                        $statusColor = $statusColors[$req->status ?? 'pending'] ?? 'secondary';
                    @endphp
                    <span class="badge bg-{{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $req->status ?? 'pending')) }}</span>
                </td>
                <td>{{ \Carbon\Carbon::parse($req->created_at)->format('j M Y') }}</td>
                <td>
                    @if(isset($req->cost) && $req->cost !== null)
                        R {{ number_format($req->cost, 2) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="text-center text-muted py-4">
    <i class="fas fa-copy fa-3x mb-3 d-block"></i>
    No reproduction requests yet. Submit a request to obtain copies of archival materials.
</div>
@endif

{{-- New Request Modal --}}
<div class="modal fade" id="newRequestModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="create">
    <div class="modal-header"><h5 class="modal-title">New Reproduction Request</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Archive Item ID <span class="text-danger">*</span></label><input type="number" class="form-control" name="object_id" required placeholder="Enter the item ID"></div>
        <div class="mb-3"><label class="form-label">Reproduction Type <span class="text-danger">*</span></label>
            <select name="reproduction_type" class="form-select" required>
                <option value="photocopy">Photocopy</option>
                <option value="scan">Scan</option>
                <option value="photograph">Photograph</option>
                <option value="digital">Digital Copy</option>
            </select>
        </div>
        <div class="mb-3"><label class="form-label">Specifications</label><textarea class="form-control" name="specifications" rows="2" placeholder="e.g. 300dpi colour scan, A3 size"></textarea></div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" placeholder="Additional information or special instructions"></textarea></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>Submit Request</button></div>
    </form>
</div></div></div>
@endsection
