@extends('ahg-theme-b5::layout')

@section('title', 'My Access Requests')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item active">My Access Requests</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-list me-2"></i>My Access Requests</h4>
                <a href="{{ route('accessRequest.create') }}" class="atom-btn-white">
                    <i class="fas fa-plus me-1"></i>New Request
                </a>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    @if($requests->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <p>You have no access requests.</p>
                            <a href="{{ route('accessRequest.create') }}" class="atom-btn-white">Create your first request</a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $req)
                                        <tr>
                                            <td>{{ $req->id }}</td>
                                            <td>{{ $req->subject ?? 'N/A' }}</td>
                                            <td>{{ $req->request_type ?? 'N/A' }}</td>
                                            <td>
                                                @if($req->status === 'approved')
                                                    <span class="badge bg-success">Approved</span>
                                                @elseif($req->status === 'denied')
                                                    <span class="badge bg-danger">Denied</span>
                                                @elseif($req->status === 'cancelled')
                                                    <span class="badge bg-secondary">Cancelled</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                @endif
                                            </td>
                                            <td>{{ $req->created_at ?? '' }}</td>
                                            <td>
                                                <a href="{{ route('accessRequest.view', $req->id) }}" class="atom-btn-white btn-sm">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3">
                            {{ $requests->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
