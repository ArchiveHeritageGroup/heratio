@extends('theme::layouts.1col')

@section('title', 'New Access Request')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
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
                    <h5 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('New Access Request') }}</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('accessRequest.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" required value="{{ old('subject') }}">
                        </div>

                        <div class="mb-3">
                            <label for="request_type" class="form-label">Request Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <select class="form-select" id="request_type" name="request_type" required>
                                <option value="">-- Select type --</option>
                                <option value="view">{{ __('View restricted material') }}</option>
                                <option value="copy">{{ __('Request copies') }}</option>
                                <option value="publish">{{ __('Permission to publish') }}</option>
                                <option value="research">{{ __('Research access') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                            <textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description') }}</textarea>
                            <div class="form-text">Describe the materials you need access to and the purpose of your request.</div>
                        </div>

                        <div class="mb-3">
                            <label for="justification" class="form-label">Justification <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <textarea class="form-control" id="justification" name="justification" rows="3">{{ old('justification') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="urgency" class="form-label">Urgency <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select class="form-select" id="urgency" name="urgency">
                                <option value="low" {{ old('urgency') === 'low' ? 'selected' : '' }}>{{ __('Low — no fixed deadline') }}</option>
                                <option value="normal" {{ (old('urgency') ?? 'normal') === 'normal' ? 'selected' : '' }}>{{ __('Normal — within standard turnaround') }}</option>
                                <option value="high" {{ old('urgency') === 'high' ? 'selected' : '' }}>{{ __('High — needed soon') }}</option>
                                <option value="urgent" {{ old('urgency') === 'urgent' ? 'selected' : '' }}>{{ __('Urgent — needed ASAP') }}</option>
                            </select>
                            <div class="form-text">Helps reviewers prioritise the queue.</div>
                        </div>

                        @if(isset($classifications) && $classifications->isNotEmpty())
                        <div class="mb-3">
                            <label for="requested_classification_id" class="form-label">Requested classification level <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                            <select class="form-select" id="requested_classification_id" name="requested_classification_id">
                                <option value="">-- Default (lowest level) --</option>
                                @foreach($classifications as $cls)
                                    <option value="{{ $cls->id }}" {{ (int) old('requested_classification_id') === (int) $cls->id ? 'selected' : '' }}>
                                        {{ $cls->name }} (level {{ $cls->level }})
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">The security classification level you need access to.</div>
                        </div>
                        @endif

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('accessRequest.myRequests') }}" class="atom-btn-white">Cancel</a>
                            <button type="submit" class="atom-btn-white">
                                <i class="fas fa-paper-plane me-1"></i>{{ __('Submit Request') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
