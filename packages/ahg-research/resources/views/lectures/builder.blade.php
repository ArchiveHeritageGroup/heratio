{{-- #1105 Lecture builder — create/edit lecture --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'lectures'])
@endsection

@section('title', $lecture ? __('Edit Lecture') : __('New Lecture'))

@section('content')
@php $t = $lecture['type'] ?? $type; @endphp
<div class="container py-3" style="max-width: 820px;">
  <h1 class="h3 mb-3"><i class="fas fa-chalkboard-teacher text-primary me-2"></i>{{ $lecture ? __('Edit') : __('New') }}
    {{ ['curriculum' => __('Curriculum Lecture'), 'talk' => __('Talk'), 'standalone' => __('Lecture')][$t] ?? __('Lecture') }}</h1>

  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ $lecture ? route('research.lecture-builder.update', $lecture['id']) : route('research.lecture-builder.store') }}">
    @csrf
    @if ($lecture)@method('PUT')@endif
    <input type="hidden" name="type" value="{{ $t }}">

    <div class="mb-3"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input name="title" class="form-control" required value="{{ old('title', $lecture['title'] ?? '') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Subtitle') }}</label>
      <input name="subtitle" class="form-control" value="{{ old('subtitle', $lecture['subtitle'] ?? '') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Summary / abstract') }}</label>
      <textarea name="summary" rows="3" class="form-control">{{ old('summary', $lecture['summary'] ?? '') }}</textarea></div>

    @if ($t === 'talk')
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Speaker') }}</label>
        <input name="speaker_name" class="form-control" value="{{ old('speaker_name', $lecture['speaker_name'] ?? '') }}"></div>
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Affiliation') }}</label>
        <input name="speaker_affiliation" class="form-control" value="{{ old('speaker_affiliation', $lecture['speaker_affiliation'] ?? '') }}"></div>
    </div>
    @endif

    @if (in_array($t, ['talk', 'curriculum']))
    <div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Scheduled') }}</label>
        <input type="datetime-local" name="scheduled_at" class="form-control" value="{{ old('scheduled_at', isset($lecture['scheduled_at']) ? str_replace(' ', 'T', substr((string)$lecture['scheduled_at'],0,16)) : '') }}"></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Location / venue') }}</label>
        <input name="location" class="form-control" value="{{ old('location', $lecture['location'] ?? '') }}"></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Duration (min)') }}</label>
        <input type="number" name="duration_minutes" class="form-control" value="{{ old('duration_minutes', $lecture['duration_minutes'] ?? '') }}"></div>
    </div>
    @endif

    @if ($t === 'curriculum')
    <div class="mb-3"><label class="form-label">{{ __('Curriculum reference') }}</label>
      <input name="curriculum_ref" class="form-control" value="{{ old('curriculum_ref', $lecture['curriculum_ref'] ?? '') }}" placeholder="{{ __('e.g. Session 4 — Cataloguing (training curriculum #1099)') }}">
      <div class="form-text">{{ __('Links this lecture to a training-curriculum session (#1099) until that module ships.') }}</div>
    </div>
    @endif

    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Slides URL') }}</label>
        <input name="slides_url" class="form-control" value="{{ old('slides_url', $lecture['slides_url'] ?? '') }}"></div>
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Recording URL') }}</label>
        <input name="recording_url" class="form-control" value="{{ old('recording_url', $lecture['recording_url'] ?? '') }}"></div>
    </div>

    <div class="mb-3"><label class="form-label">{{ __('Status') }}</label>
      <select name="status" class="form-select">
        @foreach (['draft','scheduled','delivered','published','archived'] as $s)<option value="{{ $s }}" @selected(old('status', $lecture['status'] ?? 'draft') === $s)>{{ ucfirst($s) }}</option>@endforeach
      </select></div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary">{{ __('Save') }}</button>
      <a href="{{ $lecture ? route('research.lecture-builder.show', $lecture['id']) : route('research.lecture-builder.index') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>
@endsection
