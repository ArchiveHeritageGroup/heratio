@extends('theme::layouts.1col')
@section('title', isset($patron) && $patron ? __('Edit Patron') : __('Add Patron'))
@section('content')
@php
    $editing = isset($patron) && $patron;
    $action  = $editing
        ? route('library.patron-update', $patron->id)
        : route('library.patron-store');
    $types = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
        ->where('taxonomy', 'patron_type')
        ->orderBy('sort_order')->get(['code', 'label']);
    $val = fn(string $f, $d = null) => old($f, $editing ? ($patron->{$f} ?? $d) : $d);
@endphp
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ $editing ? route('library.patron-view', $patron->id) : route('library.patrons') }}"
           class="btn btn-outline-secondary btn-sm me-3"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h2 class="mb-0">{{ $editing ? __('Edit Patron') : __('Add Patron') }}</h2>
            <span class="badge bg-primary mt-1">{{ __('Patrons') }}</span>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul></div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @if($editing)@method('PUT')@endif

        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Identity') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">{{ __('First Name') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                        <input type="text" name="first_name" id="first_name" required
                               class="form-control @error('first_name') is-invalid @enderror" value="{{ $val('first_name') }}">
                        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">{{ __('Last Name') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                        <input type="text" name="last_name" id="last_name" required
                               class="form-control @error('last_name') is-invalid @enderror" value="{{ $val('last_name') }}">
                        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="patron_type" class="form-label">{{ __('Patron Type') }}</label>
                        <select name="patron_type" id="patron_type" class="form-select">
                            <option value="">{{ __('(default)') }}</option>
                            @foreach($types as $t)
                                <option value="{{ $t->code }}" @selected($val('patron_type') === $t->code)>{{ $t->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="card_number" class="form-label">{{ __('Card Number') }}</label>
                        <input type="text" name="card_number" id="card_number" class="form-control" value="{{ $val('card_number') }}"
                               placeholder="{{ $editing ? '' : __('auto-generated if blank') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="id_number" class="form-label">{{ __('ID / Passport Number') }}</label>
                        <input type="text" name="id_number" id="id_number" class="form-control" value="{{ $val('id_number') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-address-card me-2"></i>{{ __('Contact') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">{{ __('Email') }}</label>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ $val('email') }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">{{ __('Phone') }}</label>
                        <input type="text" name="phone" id="phone" class="form-control" value="{{ $val('phone') }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">{{ __('Address') }}</label>
                    <textarea name="address" id="address" rows="2" class="form-control">{{ $val('address') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="institution" class="form-label">{{ __('Institution') }}</label>
                        <input type="text" name="institution" id="institution" class="form-control" value="{{ $val('institution') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="department" class="form-label">{{ __('Department') }}</label>
                        <input type="text" name="department" id="department" class="form-control" value="{{ $val('department') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>{{ __('Membership & Limits') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="date_of_birth" class="form-label">{{ __('Date of Birth') }}</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" value="{{ $val('date_of_birth') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="membership_expiry" class="form-label">{{ __('Membership Expiry') }}</label>
                        <input type="date" name="membership_expiry" id="membership_expiry" class="form-control" value="{{ $val('membership_expiry') }}"
                               placeholder="{{ $editing ? '' : __('auto from settings if blank') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="max_checkouts" class="form-label">{{ __('Max Checkouts') }}</label>
                        <input type="number" min="0" name="max_checkouts" id="max_checkouts" class="form-control" value="{{ $val('max_checkouts') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="max_renewals" class="form-label">{{ __('Max Renewals') }}</label>
                        <input type="number" min="0" name="max_renewals" id="max_renewals" class="form-control" value="{{ $val('max_renewals') }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="max_holds" class="form-label">{{ __('Max Holds') }}</label>
                        <input type="number" min="0" name="max_holds" id="max_holds" class="form-control" value="{{ $val('max_holds') }}">
                    </div>
                </div>
                <div class="mb-0">
                    <label for="notes" class="form-label">{{ __('Notes') }}</label>
                    <textarea name="notes" id="notes" rows="2" class="form-control">{{ $val('notes') }}</textarea>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $editing ? __('Save Changes') : __('Create Patron') }}</button>
            <a href="{{ $editing ? route('library.patron-view', $patron->id) : route('library.patrons') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
