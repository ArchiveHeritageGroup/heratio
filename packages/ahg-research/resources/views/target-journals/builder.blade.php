{{-- #1107 Target-journal directory — create/edit --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'target-journals'])
@endsection

@section('title', $journal ? __('Edit Target Journal') : __('Add Target Journal'))

@section('content')
<div class="container py-3" style="max-width: 860px;">
  <h1 class="h3 mb-3"><i class="fas fa-bullseye text-primary me-2"></i>{{ $journal ? __('Edit') : __('Add') }} {{ __('Target Journal') }}</h1>

  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="post" action="{{ $journal ? route('research.target-journal.update', $journal['id']) : route('research.target-journal.store') }}">
    @csrf
    @if ($journal)@method('PUT')@endif

    <div class="mb-3"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input name="title" class="form-control" required value="{{ old('title', $journal['title'] ?? '') }}"></div>

    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Publisher') }}</label>
        <input name="publisher" class="form-control" value="{{ old('publisher', $journal['publisher'] ?? '') }}"></div>
      <div class="col-md-3 mb-3"><label class="form-label">ISSN</label>
        <input name="issn" class="form-control" value="{{ old('issn', $journal['issn'] ?? '') }}"></div>
      <div class="col-md-3 mb-3"><label class="form-label">eISSN</label>
        <input name="eissn" class="form-control" value="{{ old('eissn', $journal['eissn'] ?? '') }}"></div>
    </div>

    <div class="mb-3"><label class="form-label">{{ __('Subject scope (what it mainly accepts)') }}</label>
      <textarea name="subject_scope" rows="3" class="form-control">{{ old('subject_scope', $journal['subject_scope'] ?? '') }}</textarea></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Article types') }}</label>
        <input name="article_types" class="form-control" value="{{ old('article_types', $journal['article_types'] ?? '') }}" placeholder="research, review, case study"></div>
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Languages') }}</label>
        <input name="languages" class="form-control" value="{{ old('languages', $journal['languages'] ?? '') }}"></div>
    </div>

    <div class="row">
      <div class="col-md-8 mb-3"><label class="form-label">{{ __('Accreditation / indexing') }}</label>
        <input name="accreditation" class="form-control" value="{{ old('accreditation', $journal['accreditation'] ?? '') }}" placeholder="DHET, Scopus, Web of Science, DOAJ, Sabinet"></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Accreditation market') }}</label>
        <input name="accreditation_market" class="form-control" value="{{ old('accreditation_market', $journal['accreditation_market'] ?? '') }}" placeholder="ZA"></div>
    </div>

    <hr><h2 class="h6">{{ __('Submission rules') }}</h2>
    <div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Reference style') }}</label>
        <select name="reference_style" class="form-select"><option value="">{{ __('— none —') }}</option>
          @foreach ($styles as $s)<option value="{{ $s }}" @selected(old('reference_style', $journal['reference_style'] ?? '') === $s)>{{ $s }}</option>@endforeach
        </select></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Max words') }}</label>
        <input type="number" name="max_words" class="form-control" value="{{ old('max_words', $journal['max_words'] ?? '') }}"></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Abstract max words') }}</label>
        <input type="number" name="abstract_max_words" class="form-control" value="{{ old('abstract_max_words', $journal['abstract_max_words'] ?? '') }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Structure / required sections') }}</label>
      <textarea name="structure_notes" rows="2" class="form-control">{{ old('structure_notes', $journal['structure_notes'] ?? '') }}</textarea></div>
    <div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Peer review') }}</label>
        <input name="peer_review" class="form-control" value="{{ old('peer_review', $journal['peer_review'] ?? '') }}" placeholder="double-blind"></div>
      <div class="col-md-2 mb-3"><label class="form-label">{{ __('Open access') }}</label>
        <select name="open_access" class="form-select"><option value="0" @selected(!old('open_access', $journal['open_access'] ?? 0))>{{ __('No') }}</option><option value="1" @selected(old('open_access', $journal['open_access'] ?? 0))>{{ __('Yes') }}</option></select></div>
      <div class="col-md-3 mb-3"><label class="form-label">APC</label>
        <input name="apc_amount" class="form-control" value="{{ old('apc_amount', $journal['apc_amount'] ?? '') }}"></div>
      <div class="col-md-3 mb-3"><label class="form-label">{{ __('Turnaround') }}</label>
        <input name="turnaround" class="form-control" value="{{ old('turnaround', $journal['turnaround'] ?? '') }}"></div>
    </div>

    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Homepage URL') }}</label>
        <input name="homepage_url" class="form-control" value="{{ old('homepage_url', $journal['homepage_url'] ?? '') }}"></div>
      <div class="col-md-6 mb-3"><label class="form-label">{{ __('Submission URL') }}</label>
        <input name="submission_url" class="form-control" value="{{ old('submission_url', $journal['submission_url'] ?? '') }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">{{ __('Notes') }}</label>
      <textarea name="notes" rows="2" class="form-control">{{ old('notes', $journal['notes'] ?? '') }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Status') }}</label>
      <select name="status" class="form-select">
        @foreach (['active','discontinued'] as $s)<option value="{{ $s }}" @selected(old('status', $journal['status'] ?? 'active') === $s)>{{ ucfirst($s) }}</option>@endforeach
      </select></div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary">{{ __('Save') }}</button>
      <a href="{{ $journal ? route('research.target-journal.show', $journal['id']) : route('research.target-journal.index') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>
@endsection
