@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.paia-list') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h2 mb-0"><i class="fas fa-file-contract me-2"></i>{{ __('New PAIA Request') }}</h1>
    </div>

    <form method="post" action="{{ route('ahgprivacy.paia-add') }}">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Request Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('PAIA Section') }} <span class="text-danger">*</span></label>
                                <select name="paia_section" class="form-select" required>
                                    @foreach($paiaTypes as $code => $info)
                                    <option value="{{ $code }}">
                                        {{ $info['code'] }} - {{ $info['label'] }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Received Date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Description of Records Requested') }} <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="4" required placeholder="{{ __('Describe the records being requested...') }}"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Requestor Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="requestor_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email Address') }}</label>
                                <input type="email" name="requestor_email" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone Number') }}</label>
                                <input type="tel" name="requestor_phone" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('ID Number') }}</label>
                                <input type="text" name="requestor_id" class="form-control" placeholder="{{ __('SA ID or Passport') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Address') }}</label>
                            <textarea name="requestor_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Request Options') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Preferred Format') }}</label>
                                <select name="preferred_format" class="form-select">
                                    <option value="copy">{{ __('Copy of record') }}</option>
                                    <option value="inspection">{{ __('Inspection of record') }}</option>
                                    <option value="both">{{ __('Copy and Inspection') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Fee Status') }}</label>
                                <select name="fee_status" class="form-select">
                                    <option value="pending">{{ __('Pending') }}</option>
                                    <option value="exempt">{{ __('Fee Exempt') }}</option>
                                    <option value="paid">{{ __('Paid') }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fee_exemption_requested" id="fee_exemption_requested" value="1">
                                <label class="form-check-label" for="fee_exemption_requested">
                                    {{ __('Fee exemption requested (for personal use or public interest)') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card bg-light mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('PAIA Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <p class="small">{{ __('The Promotion of Access to Information Act (PAIA) gives effect to the constitutional right of access to information.') }}</p>
                        <hr>
                        <p class="small mb-1"><strong>{{ __('Response Time:') }}</strong></p>
                        <p class="small text-muted mb-2">{{ __('30 days (extendable by 30 days)') }}</p>
                        <p class="small mb-1"><strong>{{ __('Request Fee:') }}</strong></p>
                        <p class="small text-muted mb-2">{{ __('R50.00 (may be waived)') }}</p>
                        <p class="small mb-1"><strong>{{ __('Access Fee:') }}</strong></p>
                        <p class="small text-muted">{{ __('Varies based on format and volume') }}</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>{{ __('PAIA Sections') }}</h5>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach($paiaTypes as $code => $info)
                        <li class="list-group-item">
                            <strong>{{ $info['code'] }}</strong> - {{ $info['label'] }}
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a href="{{ route('ahgprivacy.paia-list') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Cancel') }}
            </a>
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-save me-1"></i>{{ __('Create PAIA Request') }}
            </button>
        </div>
    </form>
</div>
@endsection
