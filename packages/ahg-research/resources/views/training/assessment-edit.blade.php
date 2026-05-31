{{-- #1099 Training — assessment (quiz) builder --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', __('Edit Assessment'))

@section('content')
@php
  // Existing questions plus 3 blank rows for adding more. Each row has 4 option slots.
  $rows = $questions;
  for ($b = 0; $b < 3; $b++) { $rows[] = ['q' => '', 'options' => [], 'answer' => 0]; }
@endphp
<div class="container py-3" style="max-width: 860px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i>{{ __('Assessment') }} — {{ $course['title'] }}</h1>
    <a href="{{ route('research.training.show', $course['id']) }}" class="btn atom-btn-white">{{ __('Back') }}</a>
  </div>
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <form method="post" action="{{ route('research.training.assessment-save', $course['id']) }}">
    @csrf
    <div class="row mb-3">
      <div class="col-md-8"><label class="form-label">{{ __('Assessment title') }}</label>
        <input name="title" class="form-control" value="{{ $assessment['title'] ?? __('Course assessment') }}"></div>
      <div class="col-md-4"><label class="form-label">{{ __('Pass mark % (blank = course default :p%)', ['p' => $course['pass_mark']]) }}</label>
        <input type="number" name="pass_mark" class="form-control" min="0" max="100" value="{{ $assessment['pass_mark'] ?? '' }}"></div>
    </div>

    <p class="text-muted small">{{ __('Enter the question and 2–4 options; select the correct answer. Leave a question blank to skip it.') }}</p>

    @foreach ($rows as $i => $row)
      @php $opts = array_pad((array) ($row['options'] ?? []), 4, ''); @endphp
      <div class="card mb-2"><div class="card-body">
        <div class="mb-2"><label class="form-label">{{ __('Question') }} {{ $i + 1 }}</label>
          <input name="q[{{ $i }}]" class="form-control" value="{{ $row['q'] ?? '' }}"></div>
        <div class="row g-2">
          @for ($o = 0; $o < 4; $o++)
            <div class="col-md-6">
              <div class="input-group input-group-sm">
                <div class="input-group-text">
                  <input class="form-check-input mt-0" type="radio" name="answer[{{ $i }}]" value="{{ $o }}" @checked((int)($row['answer'] ?? 0) === $o)>
                </div>
                <input name="options[{{ $i }}][{{ $o }}]" class="form-control" placeholder="{{ __('Option') }} {{ $o + 1 }}" value="{{ $opts[$o] ?? '' }}">
              </div>
            </div>
          @endfor
        </div>
        <small class="text-muted">{{ __('Radio = correct answer.') }}</small>
      </div></div>
    @endforeach

    <button class="btn btn-primary">{{ __('Save assessment') }}</button>
  </form>
</div>
@endsection
