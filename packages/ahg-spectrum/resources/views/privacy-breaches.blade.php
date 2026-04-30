@extends('theme::layouts.1col')

@section('title', __('Data Breach Register'))

@section('content')
<h1 class="h3 mb-4">{{ __('Data Breach Register') }}</h1>
<div class="mb-3">
    <a href="{{ route('ahgspectrum.privacy-admin') }}" class="btn btn-secondary">{{ __('Back') }}</a>
    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#reportBreachModal">
        <i class="fas fa-exclamation-triangle me-1"></i>{{ __('Report Breach') }}
    </button>
</div>

<div class="card">
    <div class="card-body">
        @if(!empty($breaches))
            <table class="table table-striped">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Affected') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Regulator') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($breaches as $b)
                    <tr>
                        <td><code>{{ $b->reference ?? '' }}</code></td>
                        <td>{{ substr($b->incident_date ?? '', 0, 10) }}</td>
                        <td>{{ $b->breach_type ?? '' }}</td>
                        <td>{{ number_format($b->individuals_affected ?? 0) }}</td>
                        <td>
                            @php
                            $sevClass = match($b->severity ?? 'medium') {
                                'critical', 'high' => 'danger',
                                'medium' => 'warning',
                                'low' => 'info',
                                default => 'secondary'
                            };
                            @endphp
                            <span class="badge bg-{{ $sevClass }}">{{ ucfirst($b->severity ?? '') }}</span>
                        </td>
                        <td>
                            @if($b->regulator_notified ?? false)
                                <span class="badge bg-info" title="{{ $b->notification_date ?? '' }}">Notified</span>
                            @else
                                <span class="badge bg-secondary">No</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ ($b->status ?? '') == 'closed' ? 'success' : 'danger' }}">
                                {{ ucfirst($b->status ?? 'open') }}
                            </span>
                        </td>
                        <td>
                            @if(($b->status ?? 'open') !== 'closed')
                                <button class="btn btn-sm btn-outline-success" onclick="closeBreach({{ $b->id }})" title="{{ __('Close') }}"><i class="fas fa-check"></i></button>
                                @if(!($b->regulator_notified ?? false))
                                    <button class="btn btn-sm btn-outline-info" onclick="notifyRegulator({{ $b->id }})" title="{{ __('Mark Notified') }}"><i class="fas fa-bell"></i></button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <p class="text-muted">{{ __('No breach incidents recorded') }}</p>
            </div>
        @endif
    </div>
</div>

<!-- Report Breach Modal -->
<div class="modal fade" id="reportBreachModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgspectrum.privacy-breaches') }}">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Report Data Breach') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-clock me-2"></i>
                        POPIA requires notification to the Information Regulator within <strong>72 hours</strong> if the breach poses a risk to data subjects.
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Incident Date/Time') }} *</label>
                            <input type="datetime-local" name="incident_date" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Discovery Date/Time') }} *</label>
                            <input type="datetime-local" name="discovered_date" class="form-control" value="{{ date('Y-m-d\TH:i') }}" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Breach Type') }} *</label>
                            <select name="breach_type" class="form-select" required>
                                <option value="unauthorized_access">{{ __('Unauthorized Access') }}</option>
                                <option value="data_theft">{{ __('Data Theft') }}</option>
                                <option value="accidental_disclosure">{{ __('Accidental Disclosure') }}</option>
                                <option value="loss_of_equipment">{{ __('Loss of Equipment') }}</option>
                                <option value="cyber_attack">{{ __('Cyber Attack') }}</option>
                                <option value="ransomware">{{ __('Ransomware') }}</option>
                                <option value="phishing">{{ __('Phishing') }}</option>
                                <option value="other">{{ __('Other') }}</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Severity') }} *</label>
                            <select name="severity" class="form-select" required>
                                <option value="low">{{ __('Low - Minor impact') }}</option>
                                <option value="medium" selected>{{ __('Medium - Moderate impact') }}</option>
                                <option value="high">{{ __('High - Significant impact') }}</option>
                                <option value="critical">{{ __('Critical - Severe impact') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }} *</label>
                        <textarea name="description" class="form-control" rows="3" required placeholder="{{ __('What happened?') }}"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Data Affected') }}</label>
                            <textarea name="data_affected" class="form-control" rows="2" placeholder="{{ __('What types of data were compromised?') }}"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Individuals Affected') }}</label>
                            <input type="number" name="individuals_affected" class="form-control" min="0" placeholder="{{ __('Estimated number') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Root Cause') }}</label>
                        <textarea name="root_cause" class="form-control" rows="2" placeholder="{{ __('What caused the breach?') }}"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Containment Actions Taken') }}</label>
                        <textarea name="containment_actions" class="form-control" rows="2" placeholder="{{ __('What steps were taken to contain the breach?') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Report Breach') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function closeBreach(id) {
    if (confirm('Close this breach incident?')) {
        fetch('{{ route('ahgspectrum.privacy-breaches') }}/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: 'id=' + id + '&status=closed'
        }).then(() => location.reload());
    }
}
function notifyRegulator(id) {
    if (confirm('Mark as notified to Information Regulator?')) {
        fetch('{{ route('ahgspectrum.privacy-breaches') }}/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: 'id=' + id + '&regulator_notified=1'
        }).then(() => location.reload());
    }
}
</script>
@endsection
