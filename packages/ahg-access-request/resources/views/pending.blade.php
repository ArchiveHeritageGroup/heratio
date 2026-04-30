@extends('theme::layouts.1col')

@section('title', 'Pending Access Requests')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item active">Pending Access Requests</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-clock me-2"></i>Pending Access Requests</h4>
                <a href="{{ route('accessRequest.approvers') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-user-shield me-1"></i>Manage Approvers
                </a>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    @if($requests->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <p>No pending access requests.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('User') }}</th>
                                        <th>{{ __('Subject') }}</th>
                                        <th>{{ __('Type') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th>{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($requests as $req)
                                        <tr>
                                            <td>{{ $req->id }}</td>
                                            <td>{{ $req->user_name ?? 'Unknown' }}</td>
                                            <td>{{ $req->subject ?? 'N/A' }}</td>
                                            <td>{{ $req->request_type ?? 'N/A' }}</td>
                                            <td>{{ $req->created_at ?? '' }}</td>
                                            <td>
                                                <a href="{{ route('accessRequest.view', $req->id) }}" class="btn btn-outline-secondary btn-sm">Review</a>
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
