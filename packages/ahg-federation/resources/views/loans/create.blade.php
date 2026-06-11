{{--
  Federation loans - admin: new inter-institution loan-request form (#1203).
  Reuses the union-catalogue member registry (read-only) for the From / To
  member pickers.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('New loan request'))

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-arrow-left-right me-2"></i>{{ __('New loan request') }}
        </h4>
        <a href="{{ route('federation.loans.index') }}" class="atom-btn-white">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to loans') }}
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if (empty($members) || count($members) < 2)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ __('At least two federation members are needed to raise a loan request. Register members on the') }}
            <a href="{{ route('union.members.index') }}">{{ __('members page') }}</a>
            {{ __('first.') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('federation.loans.save') }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="requesting_member_id" class="form-label">
                            {{ __('Requesting member (borrower)') }} <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="requesting_member_id" name="requesting_member_id" required>
                            <option value="">{{ __('Select a member...') }}</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}"
                                    {{ (string) old('requesting_member_id', $self->id ?? '') === (string) $m->id ? 'selected' : '' }}>
                                    {{ $m->name }}@if ((int) ($m->is_self ?? 0)) ({{ __('this institution') }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="holding_member_id" class="form-label">
                            {{ __('Holding member (lender)') }} <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="holding_member_id" name="holding_member_id" required>
                            <option value="">{{ __('Select a member...') }}</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}"
                                    {{ (string) old('holding_member_id') === (string) $m->id ? 'selected' : '' }}>
                                    {{ $m->name }}@if ((int) ($m->is_self ?? 0)) ({{ __('this institution') }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label for="item_title" class="form-label">{{ __('Item title / label') }}</label>
                    <input type="text" class="form-control" id="item_title" name="item_title"
                           value="{{ old('item_title') }}"
                           placeholder="{{ __('The item being requested') }}">
                </div>

                <div class="mb-3">
                    <label for="item_ref" class="form-label">{{ __('Item reference') }}</label>
                    <input type="text" class="form-control" id="item_ref" name="item_ref"
                           value="{{ old('item_ref') }}"
                           placeholder="{{ __('Record id or slug at the holding member (optional)') }}">
                    <div class="form-text">{{ __('A soft reference - the information-object id or slug at the lender. No link is enforced.') }}</div>
                </div>

                <div class="mb-3">
                    <label for="purpose" class="form-label">{{ __('Purpose') }}</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="2"
                              placeholder="{{ __('Exhibition, research, conservation, etc.') }}">{{ old('purpose') }}</textarea>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="needed_from" class="form-label">{{ __('Needed from') }}</label>
                        <input type="date" class="form-control" id="needed_from" name="needed_from"
                               value="{{ old('needed_from') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="needed_to" class="form-label">{{ __('Needed to') }}</label>
                        <input type="date" class="form-control" id="needed_to" name="needed_to"
                               value="{{ old('needed_to') }}">
                    </div>
                </div>

                <div class="mb-4 mt-3">
                    <label for="notes" class="form-label">{{ __('Notes') }}</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"
                              placeholder="{{ __('Any additional notes or correspondence') }}">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="atom-btn-white">
                    <i class="bi bi-send me-1"></i>{{ __('Create loan request') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
