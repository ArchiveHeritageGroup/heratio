@extends('theme::layouts.1col')
@section('title', __('Notice channel'))
@section('content')
<div class="container py-4">
    <h2 class="mb-3"><i class="fas fa-bell me-2"></i>{{ __('Notice channel') }}: {{ trim($patron->first_name . ' ' . $patron->last_name) }}</h2>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($preference === 'sms' && !$phoneOk)
        <div class="alert alert-warning">{{ __('This patron has no usable phone number, so SMS notices will fall through to the next channel.') }}</div>
    @endif

    <form method="POST" action="{{ route('library.notice-channel.patron-update', $patron->id) }}" class="card card-body">
        @csrf
        @method('PUT')
        <label for="channel" class="form-label">{{ __('Preferred channel') }}</label>
        <select id="channel" name="channel" class="form-select mb-2">
            <option value="">{{ __('Library default (email)') }}</option>
            @foreach($channels as $code => $label)
                <option value="{{ $code }}" @selected($preference === $code)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="form-text">{{ __('If the preferred channel cannot be used, email and then print are tried. Each attempt is logged.') }}</p>
        <div>
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('library.patron-view', $patron->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</div>
@endsection
