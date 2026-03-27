@extends('theme::layout')

@section('title', 'Federation Harvest')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item active">Harvest</li>
                </ol>
            </nav>
            <h4 class="mb-0"><i class="bi bi-cloud-download me-2"></i>Harvest Records</h4>
        </div>
        <a href="{{ route('federation.log') }}" class="atom-btn-white">
            <i class="bi bi-journal-text me-1"></i>View Logs
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Run Harvest</h6>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('federation.runHarvest') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="peer_id" class="form-label">Select Peer <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                        <select class="form-select" id="peer_id" name="peer_id" required>
                            <option value="">-- Select peer --</option>
                            <option value="all">All active peers</option>
                            @foreach($peers as $peer)
                                @if($peer->is_active ?? false)
                                    <option value="{{ $peer->id }}">{{ $peer->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="from_date" class="form-label">From Date (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="date" class="form-control" id="from_date" name="from_date">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="until_date" class="form-label">Until Date (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="date" class="form-control" id="until_date" name="until_date">
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="full_harvest" name="full_harvest" value="1">
                    <label class="form-check-label" for="full_harvest">
                        Full harvest (ignore last harvest date, re-fetch everything) <span class="badge bg-secondary ms-1">Optional</span>
                    </label>
                </div>

                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-cloud-download me-1"></i>Start Harvest
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
