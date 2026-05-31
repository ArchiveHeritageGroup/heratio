{{-- #1099 Training — course detail (modules, assessment, enrolments) --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', $course['title'])

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div>
      <h1 class="h3 mb-0">{{ $course['title'] }}</h1>
      <small class="text-muted">
        @if($course['audience']){{ $course['audience'] }} · @endif{{ __('Pass mark') }} {{ $course['pass_mark'] }}%
        · <span class="badge bg-{{ $course['status'] === 'published' ? 'success' : ($course['status'] === 'archived' ? 'secondary' : 'warning text-dark') }}">{{ ucfirst($course['status']) }}</span>
      </small>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('research.training.edit', $course['id']) }}" class="btn atom-btn-white">{{ __('Edit') }}</a>
      <a href="{{ route('research.training.assessment-edit', $course['id']) }}" class="btn atom-btn-white">{{ __('Assessment') }}</a>
      <form action="{{ route('research.training.status', $course['id']) }}" method="post">@csrf
        <input type="hidden" name="status" value="{{ $course['status'] === 'published' ? 'draft' : 'published' }}">
        <button class="btn atom-btn-white">{{ $course['status'] === 'published' ? __('Unpublish') : __('Publish') }}</button>
      </form>
    </div>
  </div>
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if ($course['description'])<p class="text-muted">{{ $course['description'] }}</p>@endif

  <div class="row">
    <div class="col-lg-7">
      {{-- Modules --}}
      <h2 class="h5">{{ __('Modules') }} <span class="text-muted">({{ count($modules) }})</span></h2>
      <ol class="list-group list-group-numbered mb-3">
        @forelse ($modules as $m)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ $m['title'] }}
              @if($m['lecture_id'])<span class="badge bg-light text-dark ms-1"><i class="fas fa-chalkboard-teacher me-1"></i>{{ __('lecture') }}</span>@endif</span>
            <span class="d-flex gap-1">
              <a href="{{ route('research.training.module-edit', $m['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a>
              <form action="{{ route('research.training.module-destroy', $m['id']) }}" method="post" onsubmit="return confirm('{{ __('Remove module?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">&times;</button></form>
            </span>
          </li>
        @empty<li class="list-group-item text-muted">{{ __('No modules yet.') }}</li>@endforelse
      </ol>
      <div class="card mb-3"><div class="card-header"><strong>{{ __('Add module') }}</strong></div><div class="card-body">
        <form method="post" action="{{ route('research.training.module-store', $course['id']) }}">@csrf
          <div class="mb-2"><label class="form-label">{{ __('Title') }}</label><input name="title" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">{{ __('Curriculum lecture (optional content source)') }}</label>
            <select name="lecture_id" class="form-select"><option value="">{{ __('— none / write below —') }}</option>
              @foreach ($lectures as $l)<option value="{{ $l['id'] }}">{{ $l['title'] }}</option>@endforeach
            </select>
            @unless(count($lectures))<div class="form-text">{{ __('Create curriculum lectures in the Lectures builder to attach them here.') }}</div>@endunless
          </div>
          <div class="mb-2"><label class="form-label">{{ __('Or module content (Markdown)') }}</label><textarea name="body_markdown" rows="3" class="form-control font-monospace"></textarea></div>
          <button class="btn btn-primary">{{ __('Add module') }}</button>
        </form>
      </div></div>

      {{-- Assessment summary --}}
      <h2 class="h5">{{ __('Assessment') }}</h2>
      <p>
        @if($assessment && count($questions))
          {{ count($questions) }} {{ __('questions') }} · {{ __('pass mark') }} {{ $assessment['pass_mark'] ?? $course['pass_mark'] }}%
        @else
          <span class="text-muted">{{ __('No assessment yet.') }}</span>
        @endif
        <a href="{{ route('research.training.assessment-edit', $course['id']) }}" class="ms-2">{{ __('Edit assessment') }}</a>
      </p>
    </div>

    <div class="col-lg-5">
      {{-- Enrolments --}}
      <h2 class="h5">{{ __('Enrolments') }} <span class="text-muted">({{ count($enrolments) }})</span></h2>
      <div class="card mb-2"><div class="card-body">
        <form method="post" action="{{ route('research.training.enrol', $course['id']) }}" class="row g-2 align-items-end">@csrf
          <div class="col-7"><label class="form-label">{{ __('Learner name') }}</label><input name="learner_name" class="form-control form-control-sm" required></div>
          <div class="col-5"><label class="form-label">{{ __('Email') }}</label><input name="learner_email" type="email" class="form-control form-control-sm"></div>
          <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('Enrol') }}</button></div>
        </form>
      </div></div>
      <ul class="list-group">
        @forelse ($enrolments as $e)
          <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-center">
              <span>{{ $e['learner_name'] ?: ('#'.$e['id']) }}
                <span class="badge bg-{{ $e['status'] === 'completed' ? 'success' : ($e['status'] === 'in_progress' ? 'info' : 'secondary') }}">{{ str_replace('_',' ',$e['status']) }}</span>
                @if($e['score'] !== null)<span class="text-muted small">{{ $e['score'] }}%</span>@endif
              </span>
              <span class="d-flex gap-1">
                <a href="{{ route('research.training.learn', $e['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Learn') }}</a>
                @if($e['status'] === 'completed')<a href="{{ route('research.training.certificate', $e['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Cert') }}</a>@endif
                <form action="{{ route('research.training.enrolment-destroy', $e['id']) }}" method="post" onsubmit="return confirm('{{ __('Remove enrolment?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">&times;</button></form>
              </span>
            </div>
          </li>
        @empty<li class="list-group-item text-muted">{{ __('No learners enrolled.') }}</li>@endforelse
      </ul>
    </div>
  </div>
</div>
@endsection
