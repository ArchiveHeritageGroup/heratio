{{-- #1105 Lecture builder — show / section + resource management --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'lectures'])
@endsection

@section('title', $lecture['title'])

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div>
      <h1 class="h3 mb-0">{{ $lecture['title'] }}</h1>
      @if ($lecture['subtitle'])<div class="text-muted">{{ $lecture['subtitle'] }}</div>@endif
      <small class="text-muted">
        <span class="badge bg-secondary">{{ ucfirst($lecture['type']) }}</span>
        <span class="badge bg-{{ in_array($lecture['status'], ['published','delivered']) ? 'success' : ($lecture['status'] === 'archived' ? 'secondary' : ($lecture['status'] === 'scheduled' ? 'info' : 'warning text-dark')) }}">{{ ucfirst($lecture['status']) }}</span>
        @if($lecture['scheduled_at']) · {{ $lecture['scheduled_at'] }}@endif
        @if($lecture['speaker_name']) · {{ $lecture['speaker_name'] }}@endif
      </small>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('research.lecture-builder.edit', $lecture['id']) }}" class="btn atom-btn-white">{{ __('Edit details') }}</a>
      <form action="{{ route('research.lecture-builder.status', $lecture['id']) }}" method="post">@csrf
        <input type="hidden" name="status" value="{{ $lecture['status'] === 'published' ? 'draft' : 'published' }}">
        <button class="btn atom-btn-white">{{ $lecture['status'] === 'published' ? __('Unpublish') : __('Publish') }}</button>
      </form>
    </div>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if ($lecture['summary'])<p class="text-muted">{{ $lecture['summary'] }}</p>@endif
  @if ($lecture['slides_url'] || $lecture['recording_url'])
    <p>@if($lecture['slides_url'])<a href="{{ $lecture['slides_url'] }}" target="_blank" rel="noopener" class="me-3"><i class="fas fa-file-powerpoint me-1"></i>{{ __('Slides') }}</a>@endif
       @if($lecture['recording_url'])<a href="{{ $lecture['recording_url'] }}" target="_blank" rel="noopener"><i class="fas fa-video me-1"></i>{{ __('Recording') }}</a>@endif</p>
  @endif

  <div class="row">
    <div class="col-lg-8">
      {{-- Content sections --}}
      <h2 class="h5">{{ __('Content') }}</h2>
      @forelse ($sections as $s)
        <div class="card mb-2"><div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <h3 class="h6 mb-1">{{ $s['heading'] ?: __('(untitled section)') }}</h3>
            <div class="d-flex gap-1">
              <a href="{{ route('research.lecture-builder.section-edit', $s['id']) }}" class="btn btn-sm atom-btn-white">{{ __('Edit') }}</a>
              <form action="{{ route('research.lecture-builder.section-destroy', $s['id']) }}" method="post" onsubmit="return confirm('{{ __('Remove this section?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button></form>
            </div>
          </div>
          @if ($s['media_url'])
            @if ($s['media_type'] === 'image')<img src="{{ $s['media_url'] }}" class="img-fluid my-2" alt="">
            @elseif ($s['media_type'] === 'video')<video src="{{ $s['media_url'] }}" controls class="w-100 my-2"></video>
            @elseif ($s['media_type'] === 'audio')<audio src="{{ $s['media_url'] }}" controls class="w-100 my-2"></audio>
            @else<p class="my-1"><a href="{{ $s['media_url'] }}" target="_blank" rel="noopener">{{ $s['media_url'] }}</a></p>@endif
          @endif
          <div>{!! $s['body_html'] !!}</div>
        </div></div>
      @empty
        <p class="text-muted">{{ __('No sections yet. Add the first one below.') }}</p>
      @endforelse

      {{-- Add section --}}
      <div class="card mt-3"><div class="card-header"><strong>{{ __('Add section') }}</strong></div><div class="card-body">
        <form method="post" action="{{ route('research.lecture-builder.section-store', $lecture['id']) }}">@csrf
          <div class="mb-2"><label class="form-label">{{ __('Heading') }}</label><input name="heading" class="form-control"></div>
          <div class="mb-2"><label class="form-label">{{ __('Body (Markdown)') }}</label><textarea name="body_markdown" rows="5" class="form-control font-monospace"></textarea></div>
          <div class="row">
            <div class="col-md-8 mb-2"><label class="form-label">{{ __('Media URL') }}</label><input name="media_url" class="form-control"></div>
            <div class="col-md-4 mb-2"><label class="form-label">{{ __('Media type') }}</label>
              <select name="media_type" class="form-select"><option value="">{{ __('— none —') }}</option>
                @foreach (['image','video','audio','embed'] as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach
              </select></div>
          </div>
          <button class="btn btn-primary">{{ __('Add section') }}</button>
        </form>
      </div></div>
    </div>

    <div class="col-lg-4">
      {{-- Resources --}}
      <h2 class="h5">{{ __('Resources') }}</h2>
      <ul class="list-group mb-2">
        @forelse ($resources as $r)
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <span><span class="badge bg-light text-dark me-1">{{ $r['resource_type'] }}</span>
              @if($r['url'])<a href="{{ $r['url'] }}" target="_blank" rel="noopener">{{ $r['label'] }}</a>@else{{ $r['label'] }}@endif</span>
            <form action="{{ route('research.lecture-builder.resource-destroy', $r['id']) }}" method="post" onsubmit="return confirm('{{ __('Remove?') }}')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">&times;</button></form>
          </li>
        @empty<li class="list-group-item text-muted">{{ __('No resources yet.') }}</li>@endforelse
      </ul>
      <div class="card"><div class="card-header"><strong>{{ __('Add resource') }}</strong></div><div class="card-body">
        <form method="post" action="{{ route('research.lecture-builder.resource-store', $lecture['id']) }}">@csrf
          <div class="mb-2"><label class="form-label">{{ __('Label') }}</label><input name="label" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">{{ __('URL') }}</label><input name="url" class="form-control"></div>
          <div class="mb-2"><label class="form-label">{{ __('Type') }}</label>
            <select name="resource_type" class="form-select">@foreach (['reading','slides','video','link','file'] as $rt)<option value="{{ $rt }}">{{ ucfirst($rt) }}</option>@endforeach</select></div>
          <button class="btn btn-primary btn-sm">{{ __('Add') }}</button>
        </form>
      </div></div>
    </div>
  </div>
</div>
@endsection
