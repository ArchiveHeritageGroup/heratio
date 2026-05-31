{{-- #1105 Lecture builder — index --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'lectures'])
@endsection

@section('title', 'Lectures')

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-chalkboard-teacher text-primary me-2"></i>{{ __('Lectures') }}</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('research.lecture-builder.create', ['type' => 'curriculum']) }}" class="btn btn-primary"><i class="fas fa-graduation-cap me-1"></i>{{ __('New Curriculum Lecture') }}</a>
      <a href="{{ route('research.lecture-builder.create', ['type' => 'talk']) }}" class="btn atom-btn-white"><i class="fas fa-microphone me-1"></i>{{ __('New Talk') }}</a>
      <a href="{{ route('research.lecture-builder.create', ['type' => 'standalone']) }}" class="btn atom-btn-white"><i class="fas fa-file-lines me-1"></i>{{ __('New Lecture') }}</a>
    </div>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @foreach ([
      ['label' => __('Curriculum lectures'), 'rows' => $curriculum, 'hint' => __('Teaching content for the training curriculum (#1099).')],
      ['label' => __('Talks & seminars'), 'rows' => $talks, 'hint' => __('Public lecture/seminar records: speaker, schedule, recording.')],
      ['label' => __('Standalone lectures'), 'rows' => $standalone, 'hint' => __('Reusable authored lectures (sections + media).')],
    ] as $group)
    <div class="card mb-4">
      <div class="card-header"><strong>{{ $group['label'] }}</strong> <small class="text-muted ms-2">{{ $group['hint'] }}</small></div>
      <div class="card-body p-0">
        @if (count($group['rows']))
        <table class="table mb-0 align-middle">
          <thead><tr><th>{{ __('Title') }}</th><th>{{ __('When / Speaker') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
          <tbody>
            @foreach ($group['rows'] as $l)
            <tr>
              <td><a href="{{ route('research.lecture-builder.show', $l['id']) }}">{{ $l['title'] }}</a>@if($l['subtitle'])<br><small class="text-muted">{{ $l['subtitle'] }}</small>@endif</td>
              <td class="text-muted">{{ $l['scheduled_at'] ?: '' }}@if($l['speaker_name']) <br><small>{{ $l['speaker_name'] }}</small>@endif</td>
              <td><span class="badge bg-{{ in_array($l['status'], ['published','delivered']) ? 'success' : ($l['status'] === 'archived' ? 'secondary' : ($l['status'] === 'scheduled' ? 'info' : 'warning text-dark')) }}">{{ ucfirst($l['status']) }}</span></td>
              <td class="text-end">
                <a href="{{ route('research.lecture-builder.show', $l['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Open') }}</a>
                <a href="{{ route('research.lecture-builder.edit', $l['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a>
                <form action="{{ route('research.lecture-builder.destroy', $l['id']) }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('Delete this lecture and its sections/resources?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else<p class="text-muted m-3">{{ __('Nothing here yet.') }}</p>@endif
      </div>
    </div>
  @endforeach
</div>
@endsection
