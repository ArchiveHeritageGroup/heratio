{{-- #1105 Journal builder — article / manuscript editor --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'journals'])
@endsection

@section('title', $article ? __('Edit Article') : __('New Article'))

@section('content')
@php $isManuscript = ($journal['kind'] ?? 'publication') === 'manuscript'; @endphp
<div class="container py-3" style="max-width: 900px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-file-lines text-primary me-2"></i>{{ $article ? __('Edit Article') : __('New Article') }}</h1>
    <a href="{{ route('research.journal-builder.show', $journal['id']) }}" class="btn atom-btn-white">{{ __('Back to') }} {{ $journal['title'] }}</a>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  @if ($isManuscript && !empty($validation))
    <div class="alert alert-warning"><strong>{{ __('Before submission:') }}</strong>
      <ul class="mb-0">@foreach ($validation as $p)<li>{{ $p }}</li>@endforeach</ul></div>
  @elseif ($isManuscript && $article)
    <div class="alert alert-success">{{ __('Manuscript passes the submission checks.') }}</div>
  @endif

  <form method="post" action="{{ $article ? route('research.journal-builder.article-update', $article['id']) : route('research.journal-builder.article-store', $journal['id']) }}">
    @csrf
    @if ($article)@method('PUT')@endif

    <div class="mb-3"><label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
      <input name="title" class="form-control" required value="{{ old('title', $article['title'] ?? '') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Authors') }}</label>
      <input name="authors" class="form-control" value="{{ old('authors', $article['authors'] ?? '') }}" placeholder="{{ __('Surname, Initials; Surname, Initials') }}"></div>
    <div class="mb-3"><label class="form-label">{{ __('Abstract') }}</label>
      <textarea name="abstract" rows="3" class="form-control">{{ old('abstract', $article['abstract'] ?? '') }}</textarea></div>
    <div class="mb-3"><label class="form-label">{{ __('Keywords') }}</label>
      <input name="keywords" class="form-control" value="{{ old('keywords', $article['keywords'] ?? '') }}" placeholder="{{ __('comma, separated') }}"></div>

    <div class="mb-3"><label class="form-label">{{ __('Body (Markdown)') }}</label>
      <textarea name="body_markdown" rows="14" class="form-control font-monospace">{{ old('body_markdown', $article['body_markdown'] ?? '') }}</textarea>
      @if ($article)<div class="form-text">{{ $article['word_count'] }} {{ __('words (updated on save)') }}</div>@endif</div>

    <div class="row">
      @unless ($isManuscript)
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Issue') }}</label>
        <select name="issue_id" class="form-select"><option value="">{{ __('— unassigned —') }}</option>
          @foreach ($issues as $i)<option value="{{ $i['id'] }}" @selected((string)old('issue_id', $article['issue_id'] ?? '') === (string)$i['id'])>{{ trim(($i['volume']?'Vol '.$i['volume']:'').' '.($i['number']?'No '.$i['number']:'')) ?: ($i['title'] ?: 'Issue '.$i['id']) }}</option>@endforeach
        </select></div>
      @endunless
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Reference style') }}</label>
        <select name="reference_style" class="form-select"><option value="">{{ __('— none —') }}</option>
          @foreach ($styles as $s)<option value="{{ $s }}" @selected(old('reference_style', $article['reference_style'] ?? '') === $s)>{{ $s }}</option>@endforeach
        </select></div>
      <div class="col-md-4 mb-3"><label class="form-label">{{ __('Status') }}</label>
        <select name="status" class="form-select">
          @foreach (['draft','submitted','published'] as $s)<option value="{{ $s }}" @selected(old('status', $article['status'] ?? 'draft') === $s)>{{ ucfirst($s) }}</option>@endforeach
        </select></div>
    </div>

    @if ($isManuscript)
    <div class="mb-3"><label class="form-label">{{ __('Target journal (where to submit)') }}</label>
      @if (count($targetJournals))
        <select name="target_journal_id" class="form-select"><option value="">{{ __('— none —') }}</option>
          @foreach ($targetJournals as $t)<option value="{{ $t['id'] }}" @selected((string)old('target_journal_id', $article['target_journal_id'] ?? '') === (string)$t['id'])>{{ $t['title'] }}</option>@endforeach
        </select>
      @else
        <input type="number" name="target_journal_id" class="form-control" value="{{ old('target_journal_id', $article['target_journal_id'] ?? '') }}" placeholder="{{ __('Target-journal directory (#1107) not yet available') }}">
      @endif
      <div class="form-text">{{ __('When the target-journal directory (#1107) is installed, its submission rules drive the validation checks above.') }}</div>
    </div>
    @endif

    <button class="btn btn-primary">{{ __('Save') }}</button>
  </form>
  @if ($article)
  <form action="{{ route('research.journal-builder.article-destroy', $article['id']) }}" method="post" class="mt-2" onsubmit="return confirm('{{ __('Delete this article?') }}')">@csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('Delete article') }}</button></form>
  @endif
</div>
@endsection
