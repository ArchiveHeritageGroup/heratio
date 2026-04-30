@extends('theme::layouts.1col')

@section('title', 'View Access Request')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('accessRequest.myRequests') }}">My Requests</a></li>
                    <li class="breadcrumb-item active">Request #{{ $accessRequest->id }}</li>
                </ol>
            </nav>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                            <h5 class="mb-0"><i class="fas fa-key me-2"></i>Access Request #{{ $accessRequest->id }}</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 200px;">{{ __('Subject') }}</th>
                                    <td>{{ $accessRequest->subject ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Requester') }}</th>
                                    <td>{{ $accessRequest->user_name ?? 'Unknown' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Request Type') }}</th>
                                    <td>{{ $accessRequest->request_type ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Status') }}</th>
                                    <td>
                                        @if($accessRequest->status === 'approved')
                                            <span class="badge bg-success">{{ __('Approved') }}</span>
                                        @elseif($accessRequest->status === 'denied')
                                            <span class="badge bg-danger">{{ __('Denied') }}</span>
                                        @elseif($accessRequest->status === 'cancelled')
                                            <span class="badge bg-secondary">{{ __('Cancelled') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>{{ __('Created') }}</th>
                                    <td>{{ $accessRequest->created_at ?? '' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Description') }}</th>
                                    <td>{{ $accessRequest->description ?? '' }}</td>
                                </tr>
                                <tr>
                                    <th>{{ __('Justification') }}</th>
                                    <td>{{ $accessRequest->justification ?? '' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if(auth()->user() && $accessRequest->status === 'pending')
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">{{ __('Actions') }}</h6>
                            </div>
                            <div class="card-body d-flex gap-2">
                                <form method="post" action="{{ route('accessRequest.approve', $accessRequest->id) }}">
                                    @csrf
                                    <button type="submit" class="btn atom-btn-white">
                                        <i class="fas fa-check me-1"></i>{{ __('Approve') }}
                                    </button>
                                </form>
                                <form method="post" action="{{ route('accessRequest.deny', $accessRequest->id) }}">
                                    @csrf
                                    <button type="submit" class="btn atom-btn-outline-danger">
                                        <i class="fas fa-times me-1"></i>{{ __('Deny') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Request Info</h6>
                        </div>
                        <div class="card-body small">
                            <p><strong>{{ __('Object:') }}</strong> {{ $accessRequest->object_slug ?? 'General request' }}</p>
                            <p><strong>{{ __('Updated:') }}</strong> {{ $accessRequest->updated_at ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
