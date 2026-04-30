@extends('theme::layouts.1col')

@section('content')
@php $isEdit = isset($jurisdiction) && $jurisdiction; @endphp

<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('ahgprivacy.jurisdiction-list') }}" class="btn btn-outline-secondary me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h2 mb-0">
            <i class="fas fa-globe me-2"></i>
            {{ $isEdit ? __('Edit Jurisdiction') : __('Add Jurisdiction') }}
        </h1>
    </div>

    <form method="post">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Basic Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Code') }} <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control" required 
                                       value="{{ $isEdit ? $jurisdiction->code : '' }}"
                                       placeholder="{{ __('e.g. popia, gdpr') }}" maxlength="30"
                                       {{ $isEdit ? 'readonly' : '' }}>
                                <small class="text-muted">{{ __('Unique identifier, lowercase') }}</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('Short Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" required
                                       value="{{ $isEdit ? $jurisdiction->name : '' }}"
                                       placeholder="{{ __('e.g. POPIA, GDPR') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Full Name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required
                                       value="{{ $isEdit ? $jurisdiction->full_name : '' }}"
                                       placeholder="{{ __('e.g. Protection of Personal Information Act') }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Country') }} <span class="text-danger">*</span></label>
                                <input type="text" name="country" class="form-control" required
                                       value="{{ $isEdit ? $jurisdiction->country : '' }}"
                                       placeholder="{{ __('e.g. South Africa') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Region') }}</label>
                                <select name="region" class="form-select">
                                    @foreach($regions as $r)
                                    <option value="{{ $r }}" {{ ($isEdit && $jurisdiction->region === $r) ? 'selected' : '' }}>
                                        {{ $r }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('Flag Icon') }}</label>
                                <input type="text" name="icon" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->icon : '' }}"
                                       placeholder="{{ __('e.g. za, eu') }}" maxlength="10">
                                <small class="text-muted">{{ __('ISO code') }}</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('Sort Order') }}</label>
                                <input type="number" name="sort_order" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->sort_order : 99 }}" min="0">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Regulatory Information') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Regulator Name') }}</label>
                                <input type="text" name="regulator" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->regulator : '' }}"
                                       placeholder="{{ __('e.g. Information Regulator') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Regulator Website') }}</label>
                                <input type="url" name="regulator_url" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->regulator_url : '' }}"
                                       placeholder="{{ __('https://...') }}">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('DSAR Response Days') }}</label>
                                <input type="number" name="dsar_days" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->dsar_days : 30 }}" min="0">
                                <small class="text-muted">{{ __('Days to respond to data subject requests') }}</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Breach Notification Hours') }}</label>
                                <input type="number" name="breach_hours" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->breach_hours : 72 }}" min="0">
                                <small class="text-muted">{{ __('0 = "as soon as feasible"') }}</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Effective Date') }}</label>
                                <input type="date" name="effective_date" class="form-control"
                                       value="{{ $isEdit ? $jurisdiction->effective_date : '' }}">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Related Laws') }}</label>
                            <textarea name="related_laws" class="form-control" rows="3"
                                      placeholder="{{ __('One law per line...') }}">@php
if ($isEdit && $jurisdiction->related_laws) {
                                $laws = json_decode($jurisdiction->related_laws, true);
                                echo is_array($laws) ? implode("\n", $laws) : '';
                            }
@endphp</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Status') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                                   {{ (!$isEdit || $jurisdiction->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">
                                <strong>{{ __('Active') }}</strong>
                                <br><small class="text-muted">{{ __('Available for selection in forms') }}</small>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-save me-1"></i>{{ $isEdit ? __('Update Jurisdiction') : __('Add Jurisdiction') }}
                    </button>
                    <a href="{{ route('ahgprivacy.jurisdiction-list') }}" class="btn btn-outline-secondary">
                        {{ __('Cancel') }}
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
