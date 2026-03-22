@extends('ahg-theme-b5::layout')

@section('title', 'Manage Approvers')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('accessRequest.pending') }}">Access Requests</a></li>
                    <li class="breadcrumb-item active">Manage Approvers</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                            <h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Current Approvers</h5>
                        </div>
                        <div class="card-body p-0">
                            @if($approvers->isEmpty())
                                <div class="p-4 text-center text-muted">
                                    <p>No approvers configured.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>User</th>
                                                <th>Email</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($approvers as $approver)
                                                <tr>
                                                    <td>{{ $approver->user_name ?? 'Unknown' }}</td>
                                                    <td>{{ $approver->email ?? '' }}</td>
                                                    <td>
                                                        <form method="post" action="{{ route('accessRequest.removeApprover', $approver->id) }}" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this approver?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-plus me-2"></i>Add Approver</h6>
                        </div>
                        <div class="card-body">
                            <form method="post" action="{{ route('accessRequest.addApprover') }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="user_id" class="form-label">Select User</label>
                                    <select class="form-select" id="user_id" name="user_id" required>
                                        <option value="">-- Select user --</option>
                                    </select>
                                </div>
                                <button type="submit" class="atom-btn-white w-100">
                                    <i class="fas fa-plus me-1"></i>Add Approver
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
