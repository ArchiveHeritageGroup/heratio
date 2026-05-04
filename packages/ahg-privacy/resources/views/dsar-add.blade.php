@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.dsar-list') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h2 mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('New DSAR') }}</h1>
    </div>

    <form method="post" action="{{ route('ahgprivacy.dsar-add.store') }}">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Request Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }} <span class="text-danger">*</span></label>
                                <select name="jurisdiction" id="jurisdiction" class="form-select" required onchange="updateRequestTypes()">
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ $code === $defaultJurisdiction ? 'selected' : '' }}>
                                        {{ $info['name'] }} ({{ $info['country'] }})
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Request Type') }} <span class="text-danger">*</span></label>
                                <select name="request_type" id="request_type" class="form-select" required>
                                    @foreach($requestTypes as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Priority') }}</label>
                                <select name="priority" class="form-select">
                                    <option value="low">{{ __('Low') }}</option>
                                    <option value="normal" selected>{{ __('Normal') }}</option>
                                    <option value="high">{{ __('High') }}</option>
                                    <option value="urgent">{{ __('Urgent') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Received Date') }}</label>
                                <input type="date" name="received_date" class="form-control" value="{{ date('Y-m-d') }}">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Assigned To') }}</label>
                                <select name="assigned_to" class="form-select">
                                    <option value="">{{ __('-- Unassigned --') }}</option>
                                    @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->username }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            </div>
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
                                <label class="form-label">{{ __('ID Type') }}</label>
                                <select name="requestor_id_type" class="form-select">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach($idTypes as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('ID Number') }}</label>
                                <input type="text" name="requestor_id_number" class="form-control">
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
                        <h5 class="mb-0">{{ __('Request Description') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="{{ __('Details of the information or action requested...') }}"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Internal Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="{{ __('Internal notes (not shared with requestor)...') }}"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4 bg-light">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Jurisdiction Info') }}</h5>
                    </div>
                    <div class="card-body" id="jurisdiction-info">
                        @php $info = $jurisdictions[$defaultJurisdiction]; @endphp
                        <p><strong>{{ $info['full_name'] }}</strong></p>
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-clock me-2 text-primary"></i>{{ __('Response:') }} {{ $info['dsar_days'] }} {{ __('days') }}</li>
                            <li><i class="fas fa-university me-2 text-primary"></i>{{ $info['regulator'] }}</li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('Create DSAR') }}
                            </button>
                            <a href="{{ route('ahgprivacy.dsar-list') }}" class="btn btn-outline-secondary">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
