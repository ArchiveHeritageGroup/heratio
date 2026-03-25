@extends('theme::layouts.1col')

@section('title', 'New Access Request')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('accessRequest.myRequests') }}">My Requests</a></li>
                    <li class="breadcrumb-item active">New Request</li>
                </ol>
            </nav>

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>New Access Request</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('accessRequest.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required value="{{ old('subject') }}">
                        </div>

                        <div class="mb-3">
                            <label for="request_type" class="form-label">Request Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <select class="form-select" id="request_type" name="request_type" required>
                                <option value="">-- Select type --</option>
                                <option value="view">View restricted material</option>
                                <option value="copy">Request copies</option>
                                <option value="publish">Permission to publish</option>
                                <option value="research">Research access</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                            <div class="form-text">Describe the materials you need access to and the purpose of your request.</div>
                        </div>

                        <div class="mb-3">
                            <label for="justification" class="form-label">Justification <span class="badge bg-secondary ms-1">Optional</span></label>
                            <textarea class="form-control" id="justification" name="justification" rows="3">{{ old('justification') }}</textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accessRequest.myRequests') }}" class="atom-btn-white">Cancel</a>
                            <button type="submit" class="atom-btn-white">
                                <i class="fas fa-paper-plane me-1"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
