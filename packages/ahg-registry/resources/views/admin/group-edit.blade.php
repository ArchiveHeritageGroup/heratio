{{--
  Registry Admin — Edit Group
  Cloned from PSIS adminGroupEditSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Edit Group') . ' — ' . ($group->name ?? ''))
@section('body-class', 'registry registry-admin-group-edit')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.groups') }}">{{ __('Groups') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Edit Group') }}: {{ $group->name ?? '' }}</h1>
  <div class="btn-group btn-group-sm">
    @if(Route::has('registry.admin.groupMembers'))
      <a href="{{ route('registry.admin.groupMembers', ['id' => (int) $group->id]) }}" class="btn btn-outline-primary">
        <i class="fas fa-users me-1"></i>{{ __('Members') }}
        <span class="badge bg-primary ms-1">{{ (int) ($group->member_count ?? 0) }}</span>
      </a>
    @endif
    @if(!empty($group->slug))
      <a href="{{ url('/registry/groups/' . $group->slug) }}" class="btn btn-outline-secondary" target="_blank">
        <i class="fas fa-external-link-alt me-1"></i>{{ __('View') }}
      </a>
    @endif
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
  </div>
@endif

<form method="post" action="{{ route('registry.admin.groupEdit', ['id' => (int) $group->id]) }}">
  @csrf
  @method('PUT')
  <div class="row">
    <div class="col-lg-8">

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fas fa-users me-2 text-primary"></i>{{ __('Group Information') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">{{ __('Group Name') }} <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" value="{{ old('name', $group->name ?? '') }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Group Type') }}</label>
              @php $gTypes = ['regional' => 'Regional', 'topic' => 'Topic / Interest', 'software' => 'Software / Technical', 'institutional' => 'Institutional', 'other' => 'Other']; $selType = old('group_type', $group->group_type ?? 'regional'); @endphp
              <select class="form-select" name="group_type">
                @foreach($gTypes as $val => $label)
                  <option value="{{ $val }}" {{ $selType === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea class="form-control" name="description" rows="4">{{ old('description', $group->description ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fas fa-user-tie me-2 text-success"></i>{{ __('Organizer') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ __('Name') }}</label><input type="text" class="form-control" name="organizer_name" value="{{ old('organizer_name', $group->organizer_name ?? '') }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" class="form-control" name="organizer_email" value="{{ old('organizer_email', $group->organizer_email ?? '') }}"></div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fas fa-map-marker-alt me-2 text-danger"></i>{{ __('Location') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label">{{ __('City') }}</label><input type="text" class="form-control" name="city" value="{{ old('city', $group->city ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Country') }}</label><input type="text" class="form-control" name="country" value="{{ old('country', $group->country ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Region') }}</label><input type="text" class="form-control" name="region" value="{{ old('region', $group->region ?? '') }}"></div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_virtual" value="1" {{ !empty($group->is_virtual) ? 'checked' : '' }}>
                <label class="form-check-label">{{ __('Virtual / Online-only group') }}</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fas fa-comments me-2 text-info"></i>{{ __('Communication') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">{{ __('Website') }}</label><input type="url" class="form-control" name="website" value="{{ old('website', $group->website ?? '') }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Email') }}</label><input type="email" class="form-control" name="email" value="{{ old('email', $group->email ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Mailing List URL') }}</label><input type="url" class="form-control" name="mailing_list_url" value="{{ old('mailing_list_url', $group->mailing_list_url ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Slack URL') }}</label><input type="url" class="form-control" name="slack_url" value="{{ old('slack_url', $group->slack_url ?? '') }}"></div>
            <div class="col-md-4"><label class="form-label">{{ __('Discord URL') }}</label><input type="url" class="form-control" name="discord_url" value="{{ old('discord_url', $group->discord_url ?? '') }}"></div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fas fa-calendar me-2 text-success"></i>{{ __('Meetings') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ __('Frequency') }}</label>
              @php $freqs = ['weekly' => 'Weekly', 'biweekly' => 'Bi-weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual', 'adhoc' => 'Ad hoc']; $selFreq = old('meeting_frequency', $group->meeting_frequency ?? ''); @endphp
              <select class="form-select" name="meeting_frequency">
                <option value="">{{ __('-- N/A --') }}</option>
                @foreach($freqs as $val => $label)
                  <option value="{{ $val }}" {{ $selFreq === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Format') }}</label>
              @php $fmts = ['in_person' => 'In Person', 'virtual' => 'Virtual', 'hybrid' => 'Hybrid']; $selFmt = old('meeting_format', $group->meeting_format ?? ''); @endphp
              <select class="form-select" name="meeting_format">
                <option value="">{{ __('-- Select --') }}</option>
                @foreach($fmts as $val => $label)
                  <option value="{{ $val }}" {{ $selFmt === $val ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4"><label class="form-label">{{ __('Platform') }}</label><input type="text" class="form-control" name="meeting_platform" value="{{ old('meeting_platform', $group->meeting_platform ?? '') }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Next Meeting') }}</label><input type="datetime-local" class="form-control" name="next_meeting_at" value="{{ !empty($group->next_meeting_at) ? date('Y-m-d\TH:i', strtotime($group->next_meeting_at)) : '' }}"></div>
            <div class="col-md-6"><label class="form-label">{{ __('Details') }}</label><input type="text" class="form-control" name="next_meeting_details" value="{{ old('next_meeting_details', $group->next_meeting_details ?? '') }}"></div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header fw-semibold"><i class="fas fa-tag me-2 text-warning"></i>{{ __('Focus Areas') }}</div>
        <div class="card-body">
          @php
            $focusVal = '';
            if (!empty($group->focus_areas)) {
              $decoded = json_decode((string) $group->focus_areas, true);
              $focusVal = is_array($decoded) ? implode(', ', $decoded) : (string) $group->focus_areas;
            }
          @endphp
          <input type="text" class="form-control" name="focus_areas" value="{{ old('focus_areas', $focusVal) }}" placeholder="{{ __('Comma-separated, e.g.: atom, preservation, digitization') }}">
        </div>
      </div>

    </div>

    <div class="col-lg-4">
      <div class="card mb-4 border-primary">
        <div class="card-header fw-semibold bg-primary text-white"><i class="fas fa-shield-alt me-2"></i>{{ __('Admin Controls') }}</div>
        <div class="card-body">

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ag-active" {{ (!isset($group->is_active) || $group->is_active) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="ag-active">{{ __('Active') }}</label>
            <div class="form-text">{{ __('Inactive groups are hidden from public listings.') }}</div>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_verified" value="1" id="ag-verified" {{ !empty($group->is_verified) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="ag-verified">{{ __('Verified') }}</label>
            <div class="form-text">{{ __('Shows verified badge on public profile.') }}</div>
          </div>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="ag-featured" {{ !empty($group->is_featured) ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="ag-featured">{{ __('Featured') }}</label>
            <div class="form-text">{{ __('Featured groups appear on the homepage.') }}</div>
          </div>

          <hr>
          <dl class="small mb-0">
            <dt>{{ __('Members') }}</dt><dd>{{ (int) ($group->member_count ?? 0) }}</dd>
            <dt>{{ __('Created') }}</dt><dd>{{ !empty($group->created_at) ? date('j M Y', strtotime($group->created_at)) : '—' }}</dd>
            <dt>{{ __('Updated') }}</dt><dd>{{ !empty($group->updated_at) ? date('j M Y H:i', strtotime($group->updated_at)) : '—' }}</dd>
            <dt>{{ __('Slug') }}</dt><dd><code>{{ $group->slug ?? '' }}</code></dd>
          </dl>
        </div>
      </div>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> {{ __('Save Changes') }}</button>
        <a href="{{ route('registry.admin.groups') }}" class="btn btn-outline-secondary">{{ __('Back to Groups') }}</a>
      </div>
    </div>
  </div>
</form>
@endsection
