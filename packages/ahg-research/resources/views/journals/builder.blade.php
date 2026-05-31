{{-- #1105 Journal builder — create/edit journal --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'journals'])
@endsection

@section('title', $journal ? __('Edit Journal') : __('New Journal'))

@section('content')
@php $isManuscript = ($journal['kind'] ?? $kind) === 'manuscript'; @endphp
<div class="container py-3" style="max-width: 820px;">
  <h1 class="h3 mb-3"><i class="fas fa-newspaper text-primary me-2"></i>{{ $journal ? __('Edit') : __('New') }} {{ $isManuscript ? __('Manuscript') : __('Journal') }}</h1>

  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ $journal ? route('research.journal-builder.update', $journal['id']) : route('research.journal-builder.store') }}">
    @csrf
    @if ($journal)@method('PUT')@endif
    <input type="hidden" name="kind" value="{{ $journal['kind'] ?? $kind }}">

    <div class="mb-3"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input name="title" class="form-control" required value="{{ old('title', $journal['title'] ?? '') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Subtitle') }}</label>
      <input name="subtitle" class="form-control" value="{{ old('subtitle', $journal['subtitle'] ?? '') }}"></div>

    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('ISSN') }}</label>
        <input name="issn" class="form-control" value="{{ old('issn', $journal['issn'] ?? '') }}"></div>
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('eISSN') }}</label>
        <input name="eissn" class="form-control" value="{{ old('eissn', $journal['eissn'] ?? '') }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Publisher') }}</label>
      <input name="publisher" class="form-control" value="{{ old('publisher', $journal['publisher'] ?? '') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Description') }}</label>
      <textarea name="description" rows="3" class="form-control">{{ old('description', $journal['description'] ?? '') }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Aims & scope') }}</label>
      <textarea name="aims_scope" rows="3" class="form-control">{{ old('aims_scope', $journal['aims_scope'] ?? '') }}</textarea></div>

    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Editor name') }}</label>
        <input name="editor_name" class="form-control" value="{{ old('editor_name', $journal['editor_name'] ?? '') }}"></div>
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Editor email') }}</label>
        <input type="email" name="editor_email" class="form-control" value="{{ old('editor_email', $journal['editor_email'] ?? '') }}"></div>
    </div>

    @if ($isManuscript)
    <div class="mb-3"><label class="form-label">{{ __('Target journal (where to submit)') }}</label>
      @if (count($targetJournals))
        <select name="target_journal_id" class="form-select">
          <option value="">{{ __('— none —') }}</option>
          @foreach ($targetJournals as $t)<option value="{{ $t['id'] }}" @selected((string)old('target_journal_id', $journal['target_journal_id'] ?? '') === (string)$t['id'])>{{ $t['title'] }}</option>@endforeach
        </select>
      @else
        <input type="number" name="target_journal_id" class="form-control" value="{{ old('target_journal_id', $journal['target_journal_id'] ?? '') }}" placeholder="{{ __('Target-journal directory (#1107) not yet available') }}">
        <div class="form-text">{{ __('The target-journal directory (#1107) is not installed yet; once it is, pick from the list here.') }}</div>
      @endif
    </div>
    @endif

    <div class="mb-3"><label class="form-label">{{ __('Status') }}</label>
      <select name="status" class="form-select">
        @foreach (['draft','published','archived'] as $s)<option value="{{ $s }}" @selected(old('status', $journal['status'] ?? 'draft') === $s)>{{ ucfirst($s) }}</option>@endforeach
      </select></div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary">{{ __('Save') }}</button>
      <a href="{{ $journal ? route('research.journal-builder.show', $journal['id']) : route('research.journal-builder.index') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>
@endsection
