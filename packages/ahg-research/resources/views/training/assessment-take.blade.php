{{-- #1099 Training — learner takes the assessment --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', __('Assessment'))

@section('content')
<div class="container py-3" style="max-width: 760px;">
  <h1 class="h3 mb-1">{{ __('Assessment') }} — {{ $course['title'] }}</h1>
  <p class="text-muted">{{ $enrol['learner_name'] ?: ('#'.$enrol['id']) }} · {{ __('Pass mark') }} {{ $course['pass_mark'] }}%</p>

  @if(!$allDone)
    <div class="alert alert-warning">{{ __('Complete all modules before taking the assessment.') }}
      <a href="{{ route('research.training.learn', $enrol['id']) }}">{{ __('Back to course') }}</a></div>
  @elseif(!count($questions))
    <div class="alert alert-info">{{ __('This course has no assessment.') }}</div>
  @else
    <form method="post" action="{{ route('research.training.assessment-submit', $enrol['id']) }}">
      @csrf
      @foreach ($questions as $i => $q)
        <div class="card mb-2"><div class="card-body">
          <p class="fw-bold mb-2">{{ $i + 1 }}. {{ $q['q'] }}</p>
          @foreach (($q['options'] ?? []) as $o => $opt)
            <div class="form-check">
              <input class="form-check-input" type="radio" name="answer[{{ $i }}]" id="q{{ $i }}o{{ $o }}" value="{{ $o }}" required>
              <label class="form-check-label" for="q{{ $i }}o{{ $o }}">{{ $opt }}</label>
            </div>
          @endforeach
        </div></div>
      @endforeach
      <button class="btn btn-primary">{{ __('Submit answers') }}</button>
      <a href="{{ route('research.training.learn', $enrol['id']) }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
    </form>
  @endif
</div>
@endsection
