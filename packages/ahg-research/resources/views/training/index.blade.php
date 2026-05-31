{{-- #1099 Training — courses index --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'training'])
@endsection

@section('title', 'Training')

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h1 class="h3 mb-0"><i class="fas fa-user-graduate text-primary me-2"></i>{{ __('Training') }}</h1>
    <a href="{{ route('research.training.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('New Course') }}</a>
  </div>
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="card"><div class="card-body p-0">
    @if (count($courses))
    <table class="table mb-0 align-middle">
      <thead><tr><th>{{ __('Course') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Pass mark') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
      <tbody>
        @foreach ($courses as $c)
        <tr>
          <td><a href="{{ route('research.training.show', $c['id']) }}">{{ $c['title'] }}</a>@if($c['language'])<span class="badge bg-light text-dark ms-1">{{ $c['language'] }}</span>@endif</td>
          <td class="text-muted">{{ $c['audience'] ?: '—' }}</td>
          <td>{{ $c['pass_mark'] }}%</td>
          <td><span class="badge bg-{{ $c['status'] === 'published' ? 'success' : ($c['status'] === 'archived' ? 'secondary' : 'warning text-dark') }}">{{ ucfirst($c['status']) }}</span></td>
          <td class="text-end">
            <a href="{{ route('research.training.show', $c['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Open') }}</a>
            <a href="{{ route('research.training.edit', $c['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a>
            <form action="{{ route('research.training.destroy', $c['id']) }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('Delete this course, its modules, enrolments and certificates?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else<p class="text-muted m-3">{{ __('No courses yet. Create one, add modules (reusing curriculum lectures), set a pass mark, then enrol learners.') }}</p>@endif
  </div></div>
</div>
@endsection
