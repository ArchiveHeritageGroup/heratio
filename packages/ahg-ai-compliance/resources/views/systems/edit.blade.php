@extends('theme::layouts.2col')
@section('title', $system ? 'Edit AI system' : 'Add AI system')
@section('body-class', 'admin ai-compliance')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => []])
@endsection

@section('title-block')
  <h1>{{ $system ? __('Edit AI system') : __('Add AI system') }}</h1>
  <p class="text-muted small mb-0">{{ __('EU AI Act system inventory - Art. 6 classification, Art. 14 human oversight') }}</p>
@endsection

@section('content')

@if ($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ $system ? route('ai-compliance.systems.update', $system->id) : route('ai-compliance.systems.store') }}" autocomplete="off">
  @csrf
  @if ($system)
    @method('PUT')
  @endif

  <div class="row g-3">
    <div class="col-md-8">
      <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
      <input type="text" name="name" class="form-control" required value="{{ old('name', $system->name ?? '') }}">
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ __('Owner (accountable person or unit)') }}</label>
      <input type="text" name="owner" class="form-control" value="{{ old('owner', $system->owner ?? '') }}">
    </div>

    <div class="col-md-4">
      <label class="form-label">{{ __('Role') }} <span class="text-danger">*</span></label>
      <select name="role" class="form-select">
        @foreach ($roles as $r)
          <option value="{{ $r }}" @selected(old('role', $system->role ?? 'deployer') === $r)>{{ ucfirst($r) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ __('Risk classification') }} <span class="text-danger">*</span></label>
      <select name="risk_classification" class="form-select">
        @foreach ($risks as $r)
          <option value="{{ $r }}" @selected(old('risk_classification', $system->risk_classification ?? 'minimal') === $r)>{{ ucfirst($r) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">{{ __('Lifecycle status') }} <span class="text-danger">*</span></label>
      <select name="lifecycle_status" class="form-select">
        @foreach ($statuses as $s)
          <option value="{{ $s }}" @selected(old('lifecycle_status', $system->lifecycle_status ?? 'development') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>

    <div class="col-md-6">
      <label class="form-label">{{ __('Provider / deployer name') }}</label>
      <input type="text" name="provider" class="form-control" value="{{ old('provider', $system->provider ?? '') }}">
    </div>
    <div class="col-md-3">
      <label class="form-label">{{ __('Last review') }}</label>
      <input type="date" name="last_review_date" class="form-control" value="{{ old('last_review_date', optional($system->last_review_date ?? null)->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3">
      <label class="form-label">{{ __('Next review') }}</label>
      <input type="date" name="next_review_date" class="form-control" value="{{ old('next_review_date', optional($system->next_review_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="col-12">
      <label class="form-label">{{ __('Intended purpose (Art. 6)') }}</label>
      <textarea name="purpose" class="form-control" rows="2">{{ old('purpose', $system->purpose ?? '') }}</textarea>
    </div>
    <div class="col-12">
      <label class="form-label">{{ __('Deployment context') }}</label>
      <textarea name="deployment_context" class="form-control" rows="2">{{ old('deployment_context', $system->deployment_context ?? '') }}</textarea>
    </div>
    <div class="col-12">
      <label class="form-label">{{ __('Human oversight measures (Art. 14)') }}</label>
      <textarea name="human_oversight" class="form-control" rows="2">{{ old('human_oversight', $system->human_oversight ?? '') }}</textarea>
    </div>
    <div class="col-12">
      <label class="form-label">{{ __('Description / notes') }}</label>
      <textarea name="description" class="form-control" rows="2">{{ old('description', $system->description ?? '') }}</textarea>
    </div>

    <div class="col-12">
      <div class="form-check">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $system->is_active ?? true))>
        <label class="form-check-label" for="is_active">{{ __('Active (counts toward review-due tracking)') }}</label>
      </div>
    </div>
  </div>

  <div class="mt-4">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button>
    <a href="{{ route('ai-compliance.systems.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
  </div>
</form>

@endsection
