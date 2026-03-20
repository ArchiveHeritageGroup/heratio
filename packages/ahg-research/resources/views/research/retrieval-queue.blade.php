@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-dolly me-2"></i>Material Retrieval Queue</h1>@endsection
@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Pending &amp; In-Transit Requests</h5>
        <span class="badge bg-primary">{{ count($requests) }} request(s)</span>
    </div>
    <div class="card-body p-0">
        @if(count($requests) > 0)
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Request ID</th>
                        <th>Researcher</th>
                        <th>Item Reference</th>
                        <th>Call Number</th>
                        <th>Status</th>
                        <th>Booking Date</th>
                        <th>Start Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                    <tr>
                        <td><span class="fw-bold">#{{ $req->id }}</span></td>
                        <td>{{ e($req->first_name) }} {{ e($req->last_name) }}</td>
                        <td>{{ e($req->object_title ?? 'N/A') }}</td>
                        <td>{{ e($req->call_number ?? '-') }}</td>
                        <td>
                            @if($req->status === 'requested')
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                            @elseif($req->status === 'in_transit')
                                <span class="badge bg-info"><i class="fas fa-truck me-1"></i>In Transit</span>
                            @elseif($req->status === 'delivered')
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Delivered</span>
                            @elseif($req->status === 'returned')
                                <span class="badge bg-secondary"><i class="fas fa-undo me-1"></i>Returned</span>
                            @else
                                <span class="badge bg-secondary">{{ $req->status }}</span>
                            @endif
                        </td>
                        <td>{{ $req->booking_date }}</td>
                        <td>{{ $req->start_time ?? '-' }}</td>
                        <td>
                            @if($req->status === 'requested')
                            <form method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ $req->id }}">
                                <input type="hidden" name="form_action" value="mark_in_transit">
                                <button type="submit" class="btn btn-info btn-sm" title="Mark In Transit"><i class="fas fa-truck"></i></button>
                            </form>
                            @endif
                            @if($req->status === 'in_transit')
                            <form method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ $req->id }}">
                                <input type="hidden" name="form_action" value="mark_delivered">
                                <button type="submit" class="btn btn-success btn-sm" title="Mark Delivered"><i class="fas fa-check"></i></button>
                            </form>
                            @endif
                            @if($req->status === 'delivered')
                            <form method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="request_id" value="{{ $req->id }}">
                                <input type="hidden" name="form_action" value="mark_returned">
                                <button type="submit" class="btn btn-secondary btn-sm" title="Mark Returned"><i class="fas fa-undo"></i></button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="fas fa-dolly fa-3x mb-3 d-block"></i>
            No pending retrieval requests.
        </div>
        @endif
    </div>
</div>
@endsection
