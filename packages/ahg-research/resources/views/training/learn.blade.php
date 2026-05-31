{{-- #1099 Training — learner view --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', $course['title'])

@section('content')
@php $doneSet = array_flip($doneIds); $total = count($modules); $done = count($doneIds); @endphp
<div class="container py-3" style="max-width: 820px;">
  <h1 class="h3 mb-1">{{ $course['title'] }}</h1>
  <p class="text-muted">{{ __('Learner') }}: <strong>{{ $enrol['learner_name'] ?: ('#'.$enrol['id']) }}</strong>
    · {{ __('Progress') }}: {{ $done }}/{{ $total }} {{ __('modules') }}
    @if($enrol['status'] === 'completed')· <span class="badge bg-success">{{ __('Completed') }}</span>@endif</p>
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @if($total)
    <div class="progress mb-3" role="progressbar"><div class="progress-bar bg-success" style="width: {{ $total ? round($done/$total*100) : 0 }}%"></div></div>
  @endif

  @foreach ($modules as $idx => $m)
    @php $isDone = isset($doneSet[$m['id']]); @endphp
    <div class="card mb-2"><div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <h2 class="h6 mb-1">{{ $idx + 1 }}. {{ $m['title'] }} @if($isDone)<i class="fas fa-check-circle text-success ms-1"></i>@endif</h2>
        <form action="{{ route('research.training.module-complete', [$enrol['id'], $m['id']]) }}" method="post">@csrf
          <input type="hidden" name="completed" value="{{ $isDone ? 0 : 1 }}">
          <button class="btn btn-sm {{ $isDone ? 'atom-btn-white' : 'btn-primary' }}">{{ $isDone ? __('Mark incomplete') : __('Mark complete') }}</button>
        </form>
      </div>
      @if($m['lecture_id'])
        <a href="{{ route('research.lecture-builder.show', $m['lecture_id']) }}"><i class="fas fa-chalkboard-teacher me-1"></i>{{ __('Open lecture content') }}</a>
      @endif
      @if(!empty($m['body_html']))<div class="mt-2">{!! $m['body_html'] !!}</div>@endif
    </div></div>
  @endforeach

  <div class="card mt-3"><div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <strong>{{ __('Assessment') }}</strong>
      @if(!count($questions))<div class="text-muted small">{{ __('No assessment set for this course.') }}</div>
      @elseif(!$allDone)<div class="text-muted small">{{ __('Complete all modules to unlock the assessment.') }}</div>
      @else<div class="text-muted small">{{ count($questions) }} {{ __('questions') }}</div>@endif
    </div>
    <div class="d-flex gap-2">
      @if(count($questions) && $allDone)<a href="{{ route('research.training.assessment-take', $enrol['id']) }}" class="btn btn-primary">{{ __('Take assessment') }}</a>@endif
      @if($certificate)<a href="{{ route('research.training.certificate', $enrol['id']) }}" class="btn atom-btn-white"><i class="fas fa-award me-1"></i>{{ __('Certificate') }}</a>@endif
    </div>
  </div></div>
</div>
@endsection
