{{-- heratio#1202 Storytelling: public, shareable "story of the collection" page. --}}
@extends('theme::layouts.1col')
@section('title', $story->title)

@section('content')
<div class="container py-4" style="max-width:820px">
  @if($story->status !== 'published')
    <div class="alert alert-warning py-2 small"><i class="fas fa-eye-slash me-1"></i>{{ __('This story is a draft - only staff can see it. Publish it to share publicly.') }}</div>
  @endif

  <article>
    <header class="mb-3">
      <h1 class="mb-1">{{ $story->title }}</h1>
      @if(!empty($story->theme))
        <p class="text-muted mb-0"><i class="fas fa-feather-pointed me-1"></i>{{ __('A story from the collection') }} - {{ $story->theme }}</p>
      @endif
    </header>

    <div class="story-body fs-5" style="line-height:1.8">
      @foreach(preg_split('/\R{2,}|\R/', trim($story->body)) as $para)
        @if(trim($para) !== '')
          <p>{{ trim($para) }}</p>
        @endif
      @endforeach
    </div>

    @if(!empty($objects))
      <hr class="my-4">
      <h2 class="h6 text-muted mb-2">{{ __('Objects in this story') }}</h2>
      <div class="row g-2">
        @foreach($objects as $o)
          <div class="col-md-6">
            <div class="border rounded p-2 small">
              <i class="fas fa-cube text-muted me-1"></i>
              @if(!empty($o['slug']))
                <a href="{{ url('/'.$o['slug']) }}">{{ $o['title'] }}</a>
              @else
                {{ $o['title'] }}
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif

    @if(!empty($sources))
      <hr class="my-4">
      <h2 class="h6 text-muted mb-2">{{ __('Sources') }}</h2>
      <ul class="small text-muted">
        @foreach($sources as $src)
          <li>
            @switch($src['type'] ?? 'note')
              @case('url')
                <i class="fas fa-link me-1"></i>
                @if(!empty($src['url']))
                  <a href="{{ $src['url'] }}" target="_blank" rel="noopener nofollow">{{ $src['label'] }}</a>
                @else
                  {{ $src['label'] }}
                @endif
                @break
              @case('upload')
                <i class="fas fa-file-arrow-up me-1"></i>{{ $src['label'] }}
                @break
              @case('record')
                <i class="fas fa-cube me-1"></i>{{ $src['label'] }} <span class="text-muted">({{ __('collection record') }})</span>
                @break
              @default
                <i class="fas fa-pen me-1"></i>{{ $src['label'] }}
            @endswitch
          </li>
        @endforeach
      </ul>
    @endif
  </article>
</div>
@endsection
