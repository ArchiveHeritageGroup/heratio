@extends('theme::layouts.1col')
@section('title', 'My Fines')
@section('content')
<div class="container py-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('opac.patron.account') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>{{ __('My Fines') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="cmd-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body text-center">
            <h2 class="{{ $finesTotal > 0 ? 'text-danger' : '' }}">
                R {{ number_format($finesTotal, 2) }}
            </h2>
            <small class="text-muted">{{ __('Outstanding balance') }}</small>
        </div>
    </div>

    @if($finesTotal > 0)
        <div class="alert alert-warning">
            <i class="fas fa-info-circle me-2"></i>
            {{ __('Please settle your outstanding fines at the library circulation desk.') }}
        </div>
    @else
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            {{ __('You have no outstanding fines. Thank you!') }}
        </div>
    @endif

</div>
@endsection
