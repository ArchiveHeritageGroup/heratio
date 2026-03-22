@extends('theme::layouts.1col')

@section('title', 'PREMIS Events - Preservation')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
    <div class="col-md-3">
        @include('ahg-preservation::_menu')
    </div>
    <div class="col-md-9">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h1 class="mb-0"><i class="fas fa-history"></i> PREMIS Events</h1>
        </div>
        <p class="text-muted mb-3">Preservation metadata events (PREMIS standard)</p>

        @if($digitalObjectId)
            <div class="alert alert-info">
                <i class="fas fa-filter"></i> Filtered to digital object #{{ $digitalObjectId }}
                <a href="{{ route('preservation.events') }}" class="btn btn-sm atom-btn-white ms-2">Clear Filter</a>
            </div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date/Time</th>
                                <th>Type</th>
                                <th>File</th>
                                <th>Outcome</th>
                                <th>Detail</th>
                                <th>Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($events as $event)
                            <tr>
                                <td>{{ $event->id }}</td>
                                <td class="text-nowrap"><small>{{ $event->event_datetime }}</small></td>
                                <td><span class="badge bg-secondary">{{ $event->event_type }}</span></td>
                                <td><small>{{ $event->file_name ?? 'N/A' }}</small></td>
                                <td>
                                    @if($event->event_outcome === 'success')
                                        <span class="badge bg-success">Success</span>
                                    @elseif($event->event_outcome === 'failure')
                                        <span class="badge bg-danger">Failure</span>
                                    @elseif($event->event_outcome === 'warning')
                                        <span class="badge bg-warning text-dark">Warning</span>
                                    @else
                                        <span class="badge bg-info">{{ ucfirst($event->event_outcome ?? 'unknown') }}</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ Str::limit($event->event_detail, 80) }}</small></td>
                                <td><small>{{ $event->linking_agent_value ?? $event->linking_agent_type ?? '-' }}</small></td>
                            </tr>
                            @if($event->event_outcome_detail)
                            <tr>
                                <td colspan="7" class="bg-light border-0 py-1 ps-4">
                                    <small class="text-muted"><i class="fas fa-info-circle"></i> {{ Str::limit($event->event_outcome_detail, 200) }}</small>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No PREMIS events recorded</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
