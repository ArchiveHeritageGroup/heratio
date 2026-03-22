@extends('ahg-theme-b5::layout')

@section('title', 'Request Object Access')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item active">Request Object Access</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                    <h5 class="mb-0"><i class="fas fa-lock-open me-2"></i>Request Access to Object</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">You are requesting access to the record: <strong>{{ $slug }}</strong></p>

                    <form method="post" action="{{ route('accessRequest.store') }}">
                        @csrf
                        <input type="hidden" name="object_slug" value="{{ $slug }}">

                        <div class="mb-3">
                            <label for="reason" class="form-label">Reason for Access <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="4" required>{{ old('reason') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="access_type" class="form-label">Access Type</label>
                            <select class="form-select" id="access_type" name="access_type">
                                <option value="view">View only</option>
                                <option value="download">Download</option>
                                <option value="copy">Physical copy</option>
                            </select>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ url()->previous() }}" class="atom-btn-white">Cancel</a>
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
