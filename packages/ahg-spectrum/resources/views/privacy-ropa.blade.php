@extends('theme::layouts.1col')

@section('title', __('Record of Processing Activities (ROPA)'))

@section('content')
<h1 class="h3 mb-4">{{ __('Record of Processing Activities (ROPA)') }}</h1>
<div class="mb-3">
    <a href="{{ route('ahgspectrum.privacy-admin') }}" class="btn btn-secondary">{{ __('Back') }}</a>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRopaModal">
        <i class="fas fa-plus me-1"></i>{{ __('Add Processing Activity') }}
    </button>
</div>

<div class="card">
    <div class="card-body">
        @if(!empty($activities))
            <table class="table table-striped">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Lawful Basis') }}</th><th>{{ __('DPIA') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($activities as $a)
                    <tr>
                        <td><strong>{{ $a->name ?? '' }}</strong></td>
                        <td>{{ substr($a->purpose ?? '', 0, 50) }}</td>
                        <td>{{ $a->lawful_basis ?? '' }}</td>
                        <td>
                            @if($a->dpia_required ?? false)
                                <span class="badge bg-{{ ($a->dpia_completed ?? false) ? 'success' : 'warning' }}">
                                    {{ ($a->dpia_completed ?? false) ? 'Complete' : 'Required' }}
                                </span>
                            @else
                                <span class="badge bg-secondary">N/A</span>
                            @endif
                        </td>
                        <td><span class="badge bg-success">{{ ucfirst($a->status ?? 'active') }}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editRopa({{ $a->id }})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRopa({{ $a->id }})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted text-center py-4">{{ __('No processing activities recorded. Click "Add Processing Activity" to create one.') }}</p>
        @endif
    </div>
</div>

<!-- Add ROPA Modal -->
<div class="modal fade" id="addRopaModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgspectrum.privacy-ropa') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i>{{ __('Add Processing Activity') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Activity Name') }} *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Lawful Basis (POPIA S11)') }}</label>
                            <select name="lawful_basis" class="form-select">
                                <option value="">-- Select --</option>
                                <option value="consent">{{ __('Consent (S11(1)(a))') }}</option>
                                <option value="contract">{{ __('Contract (S11(1)(b))') }}</option>
                                <option value="legal_obligation">{{ __('Legal Obligation (S11(1)(c))') }}</option>
                                <option value="legitimate_interest">{{ __('Legitimate Interest (S11(1)(d))') }}</option>
                                <option value="vital_interest">{{ __('Vital Interest (S11(1)(d))') }}</option>
                                <option value="public_interest">{{ __('Public Interest (S11(1)(e))') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Purpose of Processing') }} *</label>
                        <textarea name="purpose" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Data Categories') }}</label>
                            <textarea name="data_categories" class="form-control" rows="2" placeholder="{{ __('e.g., Names, ID numbers, Contact details') }}"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Data Subjects') }}</label>
                            <textarea name="data_subjects" class="form-control" rows="2" placeholder="{{ __('e.g., Researchers, Donors, Staff') }}"></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Recipients') }}</label>
                            <input type="text" name="recipients" class="form-control" placeholder="{{ __('Who receives this data?') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Retention Period') }}</label>
                            <input type="text" name="retention_period" class="form-control" placeholder="{{ __('e.g., 7 years') }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="dpia_required" value="1" class="form-check-input" id="dpiaRequired">
                                <label class="form-check-label" for="dpiaRequired">{{ __('DPIA Required') }}</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select">
                                <option value="active">{{ __('Active') }}</option>
                                <option value="inactive">{{ __('Inactive') }}</option>
                                <option value="under_review">{{ __('Under Review') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Security Measures') }}</label>
                        <textarea name="security_measures" class="form-control" rows="2" placeholder="{{ __('Describe technical and organizational security measures') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
