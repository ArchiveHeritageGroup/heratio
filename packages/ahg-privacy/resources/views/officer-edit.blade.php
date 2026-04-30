@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.officer-list') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h2 mb-0"><i class="fas fa-user-tie me-2"></i>{{ __('Edit Privacy Officer') }}</h1>
    </div>

    <form method="post" action="{{ route('ahgprivacy.officer-edit', ['id' => $officer->id]) }}">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Officer Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required value="{{ $officer->name ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Title / Position') }}</label>
                                <input type="text" name="title" class="form-control" placeholder="{{ __('e.g., Information Officer, DPO') }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Email') }} <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required value="{{ $officer->email ?? '' }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Phone') }}</label>
                                <input type="tel" name="phone" class="form-control" value="{{ $officer->phone ?? '' }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Jurisdiction') }}</label>
                                <select name="jurisdiction" class="form-select">
                                    <option value="all">{{ __('All Jurisdictions') }}</option>
                                    @foreach($jurisdictions as $code => $info)
                                    <option value="{{ $code }}">{{ $info['name'] }} ({{ $info['country'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Registration Number') }}</label>
                                <input type="text" name="registration_number" class="form-control" value="{{ $officer->registration_number ?? '' }}" placeholder="{{ __('e.g., IO registration with regulator') }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Appointed Date') }}</label>
                            <input type="date" name="appointed_date" class="form-control">
                        </div>

                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" checked>
                            <label class="form-check-label" for="is_active">{{ __('Active') }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4 bg-light">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About Privacy Officers') }}</h5>
                    </div>
                    <div class="card-body small">
                        <p><strong>{{ __('POPIA (South Africa):') }}</strong> Information Officer must be registered with the Information Regulator.</p>
                        <p><strong>{{ __('GDPR (EU):') }}</strong> Data Protection Officer required for public authorities and large-scale processing.</p>
                        <p><strong>{{ __('NDPA (Nigeria):') }}</strong> Data Protection Officer required for major data controllers.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('Save Changes') }}
                            </button>
                            <a href="{{ route('ahgprivacy.officer-list') }}" class="btn btn-outline-secondary">
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
