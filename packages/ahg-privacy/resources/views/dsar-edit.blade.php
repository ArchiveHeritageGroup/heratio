@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-edit me-2"></i>{{ __('Edit DSAR') }}
            <small class="text-muted">{{ $dsar->reference_number }}</small>
        </h1>
        <div>
            <a href="{{ route('ahgprivacy.dsar-view', ['id' => $dsar->id]) }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to View') }}
            </a>
        </div>
    </div>

    <form method="post" action="{{ route('ahgprivacy.dsar-edit', ['id' => $dsar->id]) }}">
        <input type="hidden" name="id" value="{{ $dsar->id }}">
        
        <div class="row">
            <!-- Left Column - Main Details -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Requestor Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }}</label>
                                <select name="jurisdiction" class="form-select" disabled>
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}" {{ ($dsar->jurisdiction ?? '') === $code ? 'selected' : '' }}>{{ $info['name'] }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ __('Cannot change jurisdiction after creation') }}</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Request Type') }}</label>
                                <select name="request_type" class="form-select">
                                    @foreach($requestTypes as $key => $label)
                                    <option value="{{ $key }}" {{ ($dsar->request_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Requestor Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="requestor_name" class="form-control" value="{{ $dsar->requestor_name ?? '' }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }}</label>
                                <input type="email" name="requestor_email" class="form-control" value="{{ $dsar->requestor_email ?? '' }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="tel" name="requestor_phone" class="form-control" value="{{ $dsar->requestor_phone ?? '' }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('ID Type') }}</label>
                                <select name="requestor_id_type" class="form-select">
                                    <option value="">{{ __('-- Select --') }}</option>
                                    @foreach($idTypes as $key => $label)
                                    <option value="{{ $key }}" {{ ($dsar->requestor_id_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('ID Number') }}</label>
                                <input type="text" name="requestor_id_number" class="form-control" value="{{ $dsar->requestor_id_number ?? '' }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Address') }}</label>
                            <textarea name="requestor_address" class="form-control" rows="2">{{ $dsar->requestor_address ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('Request Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="4">{{ $dsarI18n->description ?? '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Internal Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="3">{{ $dsarI18n->notes ?? '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Response Summary') }}</label>
                            <textarea name="response_summary" class="form-control" rows="3" placeholder="{{ __('Summary of how the request was handled...') }}">{{ $dsarI18n->response_summary ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Outcome Section (when completed/rejected) -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>{{ __('Outcome') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Outcome') }}</label>
                                <select name="outcome" class="form-select">
                                    @foreach($outcomeOptions as $key => $label)
                                    <option value="{{ $key }}" {{ ($dsar->outcome ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Refusal Reason') }}</label>
                            <textarea name="refusal_reason" class="form-control" rows="2" placeholder="{{ __('If refused, explain the legal grounds...') }}">{{ $dsar->refusal_reason ?? '' }}</textarea>
                            <small class="text-muted">{{ __('Required if outcome is Refused') }}</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Status & Admin -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>{{ __('Status & Assignment') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Status') }}</label>
                            <select name="status" class="form-select">
                                @foreach($statusOptions as $key => $label)
                                <option value="{{ $key }}" {{ ($dsar->status ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Priority') }}</label>
                            <select name="priority" class="form-select">
                                <option value="low" {{ ($dsar->priority ?? '') === 'low' ? 'selected' : '' }}>{{ __('Low') }}</option>
                                <option value="normal" {{ ($dsar->priority ?? 'normal') === 'normal' ? 'selected' : '' }}>{{ __('Normal') }}</option>
                                <option value="high" {{ ($dsar->priority ?? '') === 'high' ? 'selected' : '' }}>{{ __('High') }}</option>
                                <option value="urgent" {{ ($dsar->priority ?? '') === 'urgent' ? 'selected' : '' }}>{{ __('Urgent') }}</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Assigned To') }}</label>
                            <select name="assigned_to" class="form-select">
                                <option value="">{{ __('-- Unassigned --') }}</option>
                                @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ ($dsar->assigned_to ?? '') == $user->id ? 'selected' : '' }}>{{ $user->username }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_verified" value="1" class="form-check-input" id="isVerified" {{ ($dsar->is_verified ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="isVerified">{{ __('Identity Verified') }}</label>
                            </div>
                            @if($dsar->verified_at)
                            <small class="text-muted">{{ __('Verified at') }}: {{ $dsar->verified_at }}</small>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>{{ __('Dates') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Received Date') }}</label>
                            <input type="date" class="form-control" value="{{ $dsar->received_date ?? '' }}" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Due Date') }}</label>
                            <input type="date" class="form-control" value="{{ $dsar->due_date ?? '' }}" disabled>
                            @php
$dueDate = strtotime($dsar->due_date ?? 'now');
                            $today = strtotime('today');
                            $daysLeft = floor(($dueDate - $today) / 86400);
                            if ($daysLeft < 0):
@endphp
                            <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> {{ __('Overdue by') }} {{ abs($daysLeft) }} {{ __('days') }}</small>
                            @elseif($daysLeft <= 5)
                            <small class="text-warning"><i class="fas fa-clock"></i> {{ $daysLeft }} {{ __('days remaining') }}</small>
                            @else
                            <small class="text-muted">{{ $daysLeft }} {{ __('days remaining') }}</small>
                            @endif
                        </div>
                        @if($dsar->completed_date)
                        <div class="mb-3">
                            <label class="form-label">{{ __('Completed Date') }}</label>
                            <input type="date" class="form-control" value="{{ $dsar->completed_date }}" disabled>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-money-bill me-2"></i>{{ __('Fees') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Fee Required') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">R</span>
                                <input type="number" name="fee_required" class="form-control" step="0.01" min="0" value="{{ $dsar->fee_required ?? '' }}" placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="fee_paid" value="1" class="form-check-input" id="feePaid" {{ ($dsar->fee_paid ?? 0) ? 'checked' : '' }}>
                                <label class="form-check-label" for="feePaid">{{ __('Fee Paid') }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('Save Changes') }}
                            </button>
                            <a href="{{ route('ahgprivacy.dsar-view', ['id' => $dsar->id]) }}" class="btn btn-outline-secondary">
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
