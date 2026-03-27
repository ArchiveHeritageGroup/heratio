@extends('theme::layout')

@section('title', $isNew ? 'Add Federation Peer' : 'Edit Peer')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('federation.peers') }}">Peers</a></li>
                    <li class="breadcrumb-item active">{{ $isNew ? 'Add Peer' : 'Edit Peer' }}</li>
                </ol>
            </nav>
            <h4 class="mb-0">
                <i class="bi bi-{{ $isNew ? 'plus-circle' : 'pencil' }} me-2"></i>
                {{ $isNew ? 'Add Federation Peer' : 'Edit: ' . ($peer->name ?? '') }}
            </h4>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('federation.savePeer') }}">
        @csrf
        @if(!$isNew)
            <input type="hidden" name="id" value="{{ $peer->id }}">
        @endif

        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="{{ $peer->name ?? old('name', '') }}" required>
                            <div class="form-text">A descriptive name for this peer repository</div>
                        </div>

                        <div class="mb-3">
                            <label for="base_url" class="form-label">OAI-PMH Base URL <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                            <input type="url" class="form-control" id="base_url" name="base_url"
                                   value="{{ $peer->base_url ?? old('base_url', '') }}" required
                                   placeholder="https://example.com/oai">
                            <div class="form-text">The OAI-PMH endpoint URL of the peer repository</div>
                        </div>

                        <div class="mb-3">
                            <label for="metadata_prefix" class="form-label">Metadata Prefix <span class="badge bg-secondary ms-1">Optional</span></label>
                            <select class="form-select" id="metadata_prefix" name="metadata_prefix">
                                <option value="oai_dc" {{ ($peer->metadata_prefix ?? '') === 'oai_dc' ? 'selected' : '' }}>oai_dc (Dublin Core)</option>
                                <option value="oai_ead" {{ ($peer->metadata_prefix ?? '') === 'oai_ead' ? 'selected' : '' }}>oai_ead (EAD)</option>
                                <option value="oai_eac" {{ ($peer->metadata_prefix ?? '') === 'oai_eac' ? 'selected' : '' }}>oai_eac (EAC-CPF)</option>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                   {{ ($peer->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active <span class="badge bg-secondary ms-1">Optional</span></label>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Harvest Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="set_spec" class="form-label">Set Spec (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" class="form-control" id="set_spec" name="set_spec"
                                   value="{{ $peer->set_spec ?? old('set_spec', '') }}">
                            <div class="form-text">Restrict harvesting to a specific OAI set</div>
                        </div>

                        <div class="mb-3">
                            <label for="harvest_interval" class="form-label">Harvest Interval (hours) <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="number" class="form-control" id="harvest_interval" name="harvest_interval"
                                   value="{{ $peer->harvest_interval ?? old('harvest_interval', 24) }}" min="1">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-save me-2"></i>Actions</h6>
                    </div>
                    <div class="card-body">
                        <button type="submit" class="atom-btn-white w-100 mb-2">
                            <i class="bi bi-check-lg me-1"></i>{{ $isNew ? 'Create Peer' : 'Save Changes' }}
                        </button>
                        <a href="{{ route('federation.peers') }}" class="atom-btn-white w-100">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
