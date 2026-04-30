@extends('theme::layouts.1col')

@section('title', 'Access Requests')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item active">Access Requests</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('notice'))
                <div class="alert alert-info alert-dismissible fade show">
                    {{ session('notice') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="fas fa-lock me-2"></i>Access Requests</h4>
                <div>
                    <a href="{{ route('accessRequest.pending') }}" class="atom-btn-white">
                        <i class="fas fa-clock me-1"></i>{{ __('Pending') }}
                    </a>
                    <a href="{{ route('accessRequest.create') }}" class="atom-btn-white">
                        <i class="fas fa-plus me-1"></i>{{ __('New Request') }}
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    @if($requests->isEmpty())
                        <div class="p-4 text-center text-muted">
                            <p>No access requests found.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('ID') }}</th>
                                        <th>{{ __('User') }}</th>
                                        <th>{{ __('Subject') }}</th>
                                        <th>{{ __('Status') }}</th>
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
                                            <td>
                                                @if($req->status === 'approved')
                                                    <span class="badge bg-success">{{ __('Approved') }}</span>
                                                @elseif($req->status === 'denied')
                                                    <span class="badge bg-danger">{{ __('Denied') }}</span>
                                                @elseif($req->status === 'pending')
                                                    <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($req->status ?? 'Unknown') }}</span>
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
