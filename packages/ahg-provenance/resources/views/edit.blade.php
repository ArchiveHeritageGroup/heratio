@extends('ahg-theme-b5::layout')

@section('title', 'Edit Provenance - ' . ($resource->title ?? $resource->slug))

@section('content')
<div class="container-fluid py-3">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('informationobject.show', $resource->slug) }}">{{ $resource->title ?? $resource->slug }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('provenance.view', $resource->slug) }}">Provenance</a></li>
            <li class="breadcrumb-item active">Edit</li>
        </ol>
    </nav>

    <form method="post" id="provenanceForm" enctype="multipart/form-data" action="{{ route('provenance.update', $resource->slug) }}">
        @csrf
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>Edit Provenance</h4>
                <p class="text-muted mb-0">{{ $resource->title ?? $resource->slug }}</p>
            </div>
            <div>
                <a href="{{ route('informationobject.show', $resource->slug) }}" class="atom-btn-white me-2"><i class="bi bi-arrow-left me-1"></i>Back to Record</a>
                <a href="{{ route('provenance.view', $resource->slug) }}" class="atom-btn-white me-2">Cancel</a>
                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-check-lg me-1"></i>Save Provenance
                </button>
            </div>
        </div>

        @php $record = $provenance['record'] ?? null; @endphp

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header" style="background-color: var(--ahg-primary); color: #fff;">
                        <h6 class="mb-0"><i class="bi bi-file-text me-2"></i>Provenance Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="summary" class="form-label">Summary <span class="badge bg-secondary ms-1">Optional</span></label>
                            <textarea class="form-control" id="summary" name="summary" rows="4">{{ $record->summary ?? '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="acquisition_method" class="form-label">Acquisition Method <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="acquisition_method" name="acquisition_method">
                                <option value="">-- Select --</option>
                                <option value="purchase" {{ ($record->acquisition_method ?? '') === 'purchase' ? 'selected' : '' }}>Purchase</option>
                                <option value="donation" {{ ($record->acquisition_method ?? '') === 'donation' ? 'selected' : '' }}>Donation</option>
                                <option value="bequest" {{ ($record->acquisition_method ?? '') === 'bequest' ? 'selected' : '' }}>Bequest</option>
                                <option value="transfer" {{ ($record->acquisition_method ?? '') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                                <option value="deposit" {{ ($record->acquisition_method ?? '') === 'deposit' ? 'selected' : '' }}>Deposit</option>
                                <option value="other" {{ ($record->acquisition_method ?? '') === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="acquisition_date" class="form-label">Acquisition Date <span class="badge bg-secondary ms-1">Optional</span></label>
                                <input type="date" class="form-control" id="acquisition_date" name="acquisition_date" value="{{ $record->acquisition_date ?? '' }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="source" class="form-label">Source <span class="badge bg-secondary ms-1">Optional</span></label>
                                <input type="text" class="form-control" id="source" name="source" value="{{ $record->source ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Provenance Events</h6>
                    </div>
                    <div class="card-body">
                        @if(isset($provenance['events']) && $provenance['events']->count())
                            @foreach($provenance['events'] as $event)
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ $event->event_type ?? 'Event' }} - {{ $event->event_date ?? '' }}</strong>
                                        <form method="post" action="{{ route('provenance.deleteEvent', [$resource->slug, $event->id]) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this event?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                    <p class="mb-0 text-muted">{{ $event->description ?? '' }}</p>
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted">No events yet.</p>
                        @endif

                        <hr>
                        <h6>Add Event</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Event Type <span class="badge bg-secondary ms-1">Optional</span></label>
                                <select class="form-select" name="new_event_type">
                                    <option value="">-- Select --</option>
                                    <option value="creation">Creation</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="acquisition">Acquisition</option>
                                    <option value="custody_change">Custody Change</option>
                                    <option value="conservation">Conservation</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date <span class="badge bg-secondary ms-1">Optional</span></label>
                                <input type="date" class="form-control" name="new_event_date">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agent <span class="badge bg-secondary ms-1">Optional</span></label>
                                <input type="text" class="form-control" name="new_event_agent">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                            <textarea class="form-control" name="new_event_description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Notes</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">Internal Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                            <textarea class="form-control" id="notes" name="notes" rows="4">{{ $record->notes ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
