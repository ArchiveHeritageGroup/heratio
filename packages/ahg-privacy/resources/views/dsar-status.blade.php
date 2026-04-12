@extends('theme::layouts.1col')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgprivacy.index') }}">{{ __('Privacy') }}</a></li>
            <li class="breadcrumb-item active">{{ __('Check Status') }}</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="fas fa-search me-2"></i>{{ __('Check Request Status') }}</h1>

    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(isset($dsar))
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('Request Details') }}: {{ $dsar->reference_number }}</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>{{ __('Status:') }}</strong>
                        @php
$statusClasses = [
                            'received' => 'secondary',
                            'verified' => 'info',
                            'in_progress' => 'primary',
                            'pending_info' => 'warning',
                            'completed' => 'success',
                            'rejected' => 'danger',
                            'withdrawn' => 'dark'
                        ];
                        $statusClass = $statusClasses[$dsar->status] ?? 'secondary';
@endphp
                        <span class="badge bg-{{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $dsar->status)) }}</span>
                    </p>
                    <p><strong>{{ __('Request Type:') }}</strong> {{ ucfirst(str_replace('_', ' ', $dsar->request_type)) }}</p>
                    <p><strong>{{ __('Submitted:') }}</strong> {{ $dsar->received_date }}</p>
                    <p><strong>{{ __('Due Date:') }}</strong> {{ $dsar->due_date }}</p>
                </div>
                <div class="col-md-6">
                    @if($dsar->status === 'completed')
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>{{ __('Your request has been completed.') }}
                    </div>
                    @elseif($dsar->status === 'rejected')
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>{{ __('Your request was not approved.') }}
                        @if($dsar->refusal_reason)
                        <p class="mb-0 mt-2"><strong>{{ __('Reason:') }}</strong> {{ $dsar->refusal_reason }}</p>
                        @endif
                    </div>
                    @else
                    <div class="alert alert-info">
                        <i class="fas fa-clock me-2"></i>{{ __('Your request is being processed.') }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('ahgprivacy.dsar-status') }}">
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label">{{ __('Reference Number') }}</label>
                        <input type="text" name="reference" class="form-control" placeholder="DSAR-202501-0001" value="{{ request('reference') }}">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label">{{ __('Email Address') }}</label>
                        <input type="email" name="email" class="form-control" value="{{ request('email') }}">
                    </div>
                    <div class="col-md-2 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>{{ __('Check') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
