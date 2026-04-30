@extends('theme::layouts.1col')

@section('title', __('Data Subject Access Requests (DSAR)'))

@section('content')
<h1 class="h3 mb-4">{{ __('Data Subject Access Requests (DSAR)') }}</h1>
<div class="mb-3">
    <a href="{{ route('ahgspectrum.privacy-admin') }}" class="btn btn-secondary">{{ __('Back') }}</a>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDsarModal">
        <i class="fas fa-plus me-1"></i>{{ __('New DSAR') }}
    </button>
</div>

<!-- Stats -->
<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-light"><div class="card-body text-center"><h4>{{ $stats['total'] ?? 0 }}</h4><small>{{ __('Total') }}</small></div></div></div>
    <div class="col-md-3"><div class="card bg-warning"><div class="card-body text-center"><h4>{{ $stats['pending'] ?? 0 }}</h4><small>{{ __('Pending') }}</small></div></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white"><div class="card-body text-center"><h4>{{ $stats['overdue'] ?? 0 }}</h4><small>{{ __('Overdue') }}</small></div></div></div>
    <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center"><h4>{{ $stats['completed'] ?? 0 }}</h4><small>{{ __('Completed') }}</small></div></div></div>
</div>

<div class="card">
    <div class="card-body">
        @if(!empty($requests))
            <table class="table table-striped">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Type') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Received') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($requests as $r)
                    @php
                    $isOverdue = ($r->status ?? '') !== 'completed' && strtotime($r->deadline_date ?? '') < time();
                    @endphp
                    <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                        <td><code>{{ $r->reference ?? '' }}</code></td>
                        <td>{{ $r->request_type ?? '' }}</td>
                        <td>{{ $r->data_subject_name ?? '' }}</td>
                        <td>{{ $r->received_date ?? '' }}</td>
                        <td>
                            {{ $r->deadline_date ?? '' }}
                            @if($isOverdue)<span class="badge bg-danger">{{ __('OVERDUE') }}</span>@endif
                        </td>
                        <td>
                            @php
                            $statusClass = match($r->status ?? 'pending') {
                                'completed' => 'success',
                                'in_progress' => 'info',
                                'pending' => 'warning',
                                default => 'secondary'
                            };
                            @endphp
                            <span class="badge bg-{{ $statusClass }}">{{ ucfirst($r->status ?? 'pending') }}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-success" onclick="updateDsarStatus({{ $r->id }}, 'completed')" title="{{ __('Mark Complete') }}"><i class="fas fa-check"></i></button>
                            <button class="btn btn-sm btn-outline-info" onclick="updateDsarStatus({{ $r->id }}, 'in_progress')" title="{{ __('In Progress') }}"><i class="fas fa-spinner"></i></button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted text-center py-4">{{ __('No DSAR requests. Click "New DSAR" to log one.') }}</p>
        @endif
    </div>
</div>

<!-- Add DSAR Modal -->
<div class="modal fade" id="addDsarModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgspectrum.privacy-dsar') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-clock me-2"></i>{{ __('Log New DSAR') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        POPIA requires response within <strong>30 days</strong>. Deadline will be calculated automatically.
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Request Type') }} *</label>
                            <select name="request_type" class="form-select" required>
                                <option value="access">{{ __('Access (POPIA S23 / GDPR Art.15)') }}</option>
                                <option value="rectification">{{ __('Rectification (POPIA S24 / GDPR Art.16)') }}</option>
                                <option value="erasure">{{ __('Erasure (POPIA S24 / GDPR Art.17)') }}</option>
                                <option value="restriction">{{ __('Restriction (GDPR Art.18)') }}</option>
                                <option value="portability">{{ __('Portability (GDPR Art.20)') }}</option>
                                <option value="objection">{{ __('Objection (POPIA S11(3) / GDPR Art.21)') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Date Received') }} *</label>
                            <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Data Subject Name') }} *</label>
                            <input type="text" name="data_subject_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Email') }}</label>
                            <input type="email" name="data_subject_email" class="form-control">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('ID Verification Type') }}</label>
                            <select name="data_subject_id_type" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="id_document">{{ __('ID Document') }}</option>
                                <option value="passport">{{ __('Passport') }}</option>
                                <option value="drivers_license">{{ __("Driver's License") }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Assigned To') }}</label>
                            <input type="text" name="assigned_to_name" class="form-control" placeholder="{{ __('Staff member name') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Notes') }}</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="{{ __('Details of the request...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Log Request') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDsarStatus(id, status) {
    if (confirm('Update status to ' + status + '?')) {
        fetch('{{ route('ahgspectrum.privacy-dsar') }}/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: 'id=' + id + '&status=' + status
        }).then(() => location.reload());
    }
}
</script>
@endsection
