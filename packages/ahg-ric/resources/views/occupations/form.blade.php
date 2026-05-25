@extends('theme::layouts.2col')
@section('title', ($mode === 'edit' ? __('Edit') : __('Create')) . ' ' . __('Occupation'))
@section('body-class', 'admin ric')

@section('content')
<h1 class="mb-3">
    <i class="fas fa-briefcase me-2"></i>{{ $mode === 'edit' ? __('Edit Occupation') : __('New Occupation') }}
</h1>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="post" action="{{ $mode === 'edit'
    ? route('ric.occupations.update', $occupation->id)
    : route('ric.occupations.store') }}">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    <div class="mb-3">
        <label class="form-label">{{ __('Actor') }} <span class="text-danger">*</span></label>
        <select name="actor_id" class="form-select" required>
            <option value="">{{ __('-- Select an actor --') }}</option>
            @foreach($actors as $a)
                <option value="{{ $a->id }}"
                    {{ (int) old('actor_id', $occupation->actor_id) === (int) $a->id ? 'selected' : '' }}>
                    {{ $a->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">{{ __('The actor that holds (or held) this occupation.') }}</div>
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" maxlength="255"
               value="{{ old('title', $occupation->title) }}" required>
        <div class="form-text">{{ __('Role, profession, or position name (e.g. "Conservator").') }}</div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('Start Date') }}</label>
            <input type="date" name="start_date" class="form-control"
                   value="{{ old('start_date', optional($occupation->start_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ __('End Date') }}</label>
            <input type="date" name="end_date" class="form-control"
                   value="{{ old('end_date', optional($occupation->end_date)->format('Y-m-d')) }}">
        </div>
        <div class="col-md-4 mb-3 d-flex align-items-end">
            <div class="form-check">
                <input type="hidden" name="is_current" value="0">
                <input type="checkbox" name="is_current" value="1" class="form-check-input"
                       id="is_current_chk"
                       {{ old('is_current', $occupation->is_current) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_current_chk">{{ __('Currently held') }}</label>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">{{ __('Description') }}</label>
        <textarea name="description" class="form-control" rows="4">{{ old('description', $occupation->description) }}</textarea>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> {{ __('Save') }}
        </button>
        <a href="{{ route('ric.occupations.index') }}" class="btn btn-secondary">
            {{ __('Cancel') }}
        </a>
    </div>
</form>
@endsection
