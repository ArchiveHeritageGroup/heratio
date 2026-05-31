{{-- #1105 Journal builder — show / table of contents --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'journals'])
@endsection

@section('title', $journal['title'])

@section('content')
@php $isManuscript = $journal['kind'] === 'manuscript'; @endphp
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div>
      <h1 class="h3 mb-0">{{ $journal['title'] }}</h1>
      @if ($journal['subtitle'])<div class="text-muted">{{ $journal['subtitle'] }}</div>@endif
      <small class="text-muted">
        @if($journal['issn'])ISSN {{ $journal['issn'] }} · @endif
        <span class="badge bg-{{ $journal['status'] === 'published' ? 'success' : ($journal['status'] === 'archived' ? 'secondary' : 'warning text-dark') }}">{{ ucfirst($journal['status']) }}</span>
      </small>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('research.journal-builder.article-create', $journal['id']) }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Add Article') }}</a>
      <a href="{{ route('research.journal-builder.edit', $journal['id']) }}" class="btn atom-btn-white">{{ __('Edit') }}</a>
      <form action="{{ route('research.journal-builder.status', $journal['id']) }}" method="post" class="d-inline">@csrf
        <input type="hidden" name="status" value="{{ $journal['status'] === 'published' ? 'draft' : 'published' }}">
        <button class="btn atom-btn-white">{{ $journal['status'] === 'published' ? __('Unpublish') : __('Publish') }}</button>
      </form>
    </div>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if ($journal['aims_scope'])<p class="text-muted">{{ $journal['aims_scope'] }}</p>@endif

  @unless ($isManuscript)
  {{-- Add issue --}}
  <div class="card mb-3"><div class="card-header"><strong>{{ __('Add issue') }}</strong></div><div class="card-body">
    <form method="post" action="{{ route('research.journal-builder.issue-store', $journal['id']) }}" class="row g-2 align-items-end">@csrf
      <div class="col-md-2"><label class="form-label">{{ __('Volume') }}</label><input name="volume" class="form-control"></div>
      <div class="col-md-2"><label class="form-label">{{ __('Number') }}</label><input name="number" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">{{ __('Title') }}</label><input name="title" class="form-control"></div>
      <div class="col-md-2"><label class="form-label">{{ __('Date') }}</label><input type="date" name="issue_date" class="form-control"></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('Add') }}</button></div>
    </form>
  </div></div>
  @endunless

  {{-- Table of contents --}}
  @forelse ($toc as $issue)
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>
          @if ($issue['id'])
            {{ trim(($issue['volume'] ? 'Vol '.$issue['volume'] : '').' '.($issue['number'] ? 'No '.$issue['number'] : '')) ?: __('Issue') }}
            @if($issue['title']) — {{ $issue['title'] }}@endif
            @if($issue['issue_date']) <small class="text-muted">({{ $issue['issue_date'] }})</small>@endif
          @else
            {{ __('Unassigned articles') }}
          @endif
        </strong>
        @if ($issue['id'])
        <form action="{{ route('research.journal-builder.issue-destroy', $issue['id']) }}" method="post" onsubmit="return confirm('{{ __('Remove this issue? Articles are kept and unassigned.') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Remove issue') }}</button></form>
        @endif
      </div>
      <div class="card-body p-0">
        @if (count($issue['articles']))
        <table class="table mb-0 align-middle">
          <tbody>
          @foreach ($issue['articles'] as $a)
            <tr>
              <td><a href="{{ route('research.journal-builder.article-edit', $a['id']) }}">{{ $a['title'] }}</a>
                  @if($a['authors'])<br><small class="text-muted">{{ $a['authors'] }}</small>@endif</td>
              <td class="text-muted">{{ $a['word_count'] }} {{ __('words') }}</td>
              <td><span class="badge bg-{{ $a['status'] === 'published' ? 'success' : ($a['status'] === 'submitted' ? 'info' : 'warning text-dark') }}">{{ ucfirst($a['status']) }}</span></td>
              <td class="text-end"><a href="{{ route('research.journal-builder.article-edit', $a['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a></td>
            </tr>
          @endforeach
          </tbody>
        </table>
        @else<p class="text-muted m-3">{{ __('No articles in this issue yet.') }}</p>@endif
      </div>
    </div>
  @empty
    <p class="text-muted">{{ __('No issues or articles yet. Use “Add Article” to start.') }}</p>
  @endforelse
</div>
@endsection
