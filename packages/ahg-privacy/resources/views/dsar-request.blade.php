@extends('theme::layouts.1col')

@section('content')
<div class="container py-4">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgprivacy.index') }}">{{ __('Privacy') }}</a></li>
            <li class="breadcrumb-item active">{{ __('Submit Request') }}</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="fas fa-file-alt me-2"></i>{{ __('Data Subject Access Request') }}</h1>

    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('ahgprivacy.dsar-request.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Request Type') }} <span class="text-danger">*</span></label>
                        <select name="request_type" class="form-select" required>
                            <option value="">{{ __('Select...') }}</option>
                            @foreach($requestTypes as $key => $label)
                            <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <h5 class="mt-4 mb-3">{{ __('Your Details') }}</h5>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="requestor_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Email Address') }} <span class="text-danger">*</span></label>
                        <input type="email" name="requestor_email" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Phone Number') }}</label>
                        <input type="tel" name="requestor_phone" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('ID Type') }}</label>
                        <select name="requestor_id_type" class="form-select">
                            <option value="">{{ __('Select...') }}</option>
                            <option value="sa_id">{{ __('South African ID') }}</option>
                            <option value="passport">{{ __('Passport') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('ID Number') }}</label>
                        <input type="text" name="requestor_id_number" class="form-control">
                        <small class="text-muted">{{ __('Required for identity verification') }}</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('Description of Request') }}</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="{{ __('Please describe the information you are requesting...') }}"></textarea>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    {{ __('We will respond to your request within 30 days as required by POPIA.') }}
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="{{ route('ahgprivacy.index') }}" class="btn btn-secondary">
                        {{ __('Cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>{{ __('Submit Request') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
