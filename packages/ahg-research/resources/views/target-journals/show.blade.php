{{-- #1107 Target-journal directory — show --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'target-journals'])
@endsection

@section('title', $journal['title'])

@section('content')
<div class="container py-3" style="max-width: 820px;">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div>
      <h1 class="h3 mb-0">{{ $journal['title'] }}</h1>
      @if ($journal['publisher'])<div class="text-muted">{{ $journal['publisher'] }}</div>@endif
      <small class="text-muted">
        @if($journal['issn'])ISSN {{ $journal['issn'] }}@endif @if($journal['eissn'])· eISSN {{ $journal['eissn'] }}@endif
        @if($journal['open_access'])· <span class="badge bg-success">Open access</span>@endif
        @if($journal['status'] !== 'active')· <span class="badge bg-secondary">{{ ucfirst($journal['status']) }}</span>@endif
      </small>
    </div>
    <a href="{{ route('research.target-journal.edit', $journal['id']) }}" class="btn atom-btn-white">{{ __('Edit') }}</a>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @if ($journal['subject_scope'])<h2 class="h6 mt-3">{{ __('Scope — what it accepts') }}</h2><p>{{ $journal['subject_scope'] }}</p>@endif

  <h2 class="h6 mt-3">{{ __('Submission rules') }}</h2>
  <table class="table table-sm">
    <tbody>
      @foreach ([
        __('Article types') => $journal['article_types'],
        __('Accreditation / indexing') => $journal['accreditation'],
        __('Accreditation market') => $journal['accreditation_market'],
        __('Reference style') => $journal['reference_style'],
        __('Max words') => $journal['max_words'],
        __('Abstract max words') => $journal['abstract_max_words'],
        __('Structure / required sections') => $journal['structure_notes'],
        __('Peer review') => $journal['peer_review'],
        __('APC') => $journal['apc_amount'],
        __('Turnaround') => $journal['turnaround'],
        __('Languages') => $journal['languages'],
      ] as $label => $val)
        @if ($val !== null && $val !== '')
        <tr><th class="text-muted" style="width: 220px;">{{ $label }}</th><td>{{ $val }}</td></tr>
        @endif
      @endforeach
    </tbody>
  </table>

  @if ($journal['notes'])<p class="text-muted"><em>{{ $journal['notes'] }}</em></p>@endif

  <div class="d-flex gap-3">
    @if($journal['homepage_url'])<a href="{{ $journal['homepage_url'] }}" target="_blank" rel="noopener"><i class="fas fa-globe me-1"></i>{{ __('Homepage') }}</a>@endif
    @if($journal['submission_url'])<a href="{{ $journal['submission_url'] }}" target="_blank" rel="noopener"><i class="fas fa-paper-plane me-1"></i>{{ __('Submit') }}</a>@endif
  </div>

  <p class="mt-3"><a href="{{ route('research.target-journal.index') }}" class="btn atom-btn-white btn-sm">&larr; {{ __('Back to directory') }}</a></p>
</div>
@endsection
