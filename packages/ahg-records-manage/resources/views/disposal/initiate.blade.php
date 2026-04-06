@extends('theme::layouts.1col')

@section('title', 'Initiate Disposal')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-3">Initiate Disposal</h1>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($hasLegalHold)
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Warning:</strong> This information object is currently under an active legal hold. Disposal cannot be initiated.
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <strong>Information Object:</strong> {{ $io->title ?? 'Untitled (IO #' . $io->id . ')' }}
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('records.disposal.initiate.store') }}">
                @csrf
                <input type="hidden" name="information_object_id" value="{{ $io->id }}">

                <div class="mb-3">
                    <label class="form-label fw-bold">Action Type</label>
                    @foreach ([
                        'destroy' => ['Destroy', 'Permanently delete all digital objects and generate destruction certificate.'],
                        'transfer_archives' => ['Transfer to Archives', 'Transfer records to an archival institution.'],
                        'transfer_external' => ['Transfer External', 'Transfer records to an external organization.'],
                        'retain_permanent' => ['Retain Permanently', 'Mark records for permanent retention.'],
                        'review' => ['Review', 'Flag for review before determining disposal action.'],
                    ] as $value => [$label, $desc])
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="action_type" id="action_{{ $value }}" value="{{ $value }}"
                                {{ old('action_type') === $value ? 'checked' : '' }}
                                {{ $hasLegalHold ? 'disabled' : '' }}
                                onchange="toggleTransferDest()">
                            <label class="form-check-label" for="action_{{ $value }}">
                                <strong>{{ $label }}</strong>
                                <br><small class="text-muted">{{ $desc }}</small>
                            </label>
                        </div>
                    @endforeach
                    @error('action_type')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3" id="transfer-destination-group" style="display: none;">
                    <label for="transfer_destination" class="form-label fw-bold">Transfer Destination</label>
                    <input type="text" name="transfer_destination" id="transfer_destination" class="form-control"
                        value="{{ old('transfer_destination') }}" placeholder="Name of the receiving institution or organization">
                    @error('transfer_destination')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                @if (count($disposalClasses) > 0)
                    <div class="mb-3">
                        <label for="disposal_class_id" class="form-label fw-bold">Retention Policy (Disposal Class)</label>
                        <select name="disposal_class_id" id="disposal_class_id" class="form-select">
                            <option value="">-- None --</option>
                            @foreach ($disposalClasses as $class)
                                <option value="{{ $class->id }}" {{ old('disposal_class_id') == $class->id ? 'selected' : '' }}>
                                    {{ $class->name }} ({{ $class->retention_period_days }} days)
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="mb-3">
                    <label for="reason" class="form-label fw-bold">Reason</label>
                    <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Reason for initiating disposal...">{{ old('reason') }}</textarea>
                    @error('reason')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" {{ $hasLegalHold ? 'disabled' : '' }}>
                        Initiate Disposal
                    </button>
                    <a href="{{ route('records.disposal.queue') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleTransferDest() {
    var checked = document.querySelector('input[name="action_type"]:checked');
    var group = document.getElementById('transfer-destination-group');
    if (checked && (checked.value === 'transfer_archives' || checked.value === 'transfer_external')) {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleTransferDest);
</script>
@endsection
