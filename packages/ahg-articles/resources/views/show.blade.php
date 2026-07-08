@extends('theme::layouts.1col')
@section('title', $article->title)
@push('css')
  <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($article->excerpt ?? $article->title), 155) }}">
@endpush
@section('content')
@php $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false); @endphp
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <a href="{{ route('articles.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('All Articles') }}</a>
        @if($isAdmin)
            <a href="{{ route('admin.articles.edit', $article->id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        @endif
    </div>

    <article class="mx-auto" style="max-width:820px;">
        @if($article->article_group)
            <span class="badge bg-primary mb-2">{{ $article->article_group }}</span>
        @endif
        <h1 class="mb-2">{{ $article->title }}</h1>
        <div class="text-muted mb-4">
            @if($article->published_at){{ \Carbon\Carbon::parse($article->published_at)->format('d F Y') }}@endif
            @if($article->author) &middot; {{ $article->author }}@endif
            &middot; <i class="fas fa-eye"></i> {{ number_format($article->view_count ?? 0) }} {{ trans_choice('read|reads', (int) ($article->view_count ?? 0)) }}
        </div>

        @if($article->cover_image)
            <img src="{{ $article->cover_image }}" alt="{{ $article->title }}" class="img-fluid rounded mb-4 d-block mx-auto">
        @endif

        <div class="article-body">
            {!! $bodyHtml !!}
        </div>

        {{-- Linked articles (bidirectional) — above Guides & Templates. heratio#1399 --}}
        @if(!empty($related))
            <div class="card shadow-sm mt-5">
                <div class="card-header d-flex align-items-center">
                    <i class="fas fa-link me-2"></i>
                    <span class="h5 mb-0">{{ __('Related articles') }}</span>
                    <span class="badge bg-secondary ms-2">{{ count($related) }}</span>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($related as $rel)
                            <li class="mb-2">
                                <a href="{{ route('articles.show', $rel['slug']) }}">{{ $rel['title'] }}</a>
                                @if(!empty($rel['description']))<span class="d-block text-muted small">{{ $rel['description'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if(!empty($attachments))
            <div class="card border-primary shadow-sm mt-5">
                <div class="card-header bg-primary text-white d-flex align-items-center">
                    <i class="fas fa-cloud-download-alt fa-lg me-2"></i>
                    <span class="h5 mb-0">{{ __('Guides & Templates') }}</span>
                    <span class="badge bg-light text-primary ms-2">{{ count($attachments) }}</span>
                </div>
                <div class="card-body">
                    @if(!empty($article->attachments_label))
                        <p class="lead mb-3">{{ $article->attachments_label }}</p>
                    @endif
                    <div class="list-group">
                        @php $currentGroup = '__START__'; @endphp
                        @foreach($attachments as $att)
                            @php $g = trim((string) ($att->group_label ?? '')); @endphp
                            @if($g !== $currentGroup)
                                @php $currentGroup = $g; @endphp
                                @if($g !== '')
                                    <div class="list-group-item bg-light border-top mt-2 text-uppercase small fw-bold text-muted">
                                        <i class="fas fa-folder-open me-2"></i>{{ $g }}
                                    </div>
                                @endif
                            @endif
                            <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($att->file_path) }}"
                               class="list-group-item list-group-item-action d-flex align-items-start gap-3"
                               target="_blank" rel="noopener" download="{{ $att->file_name }}">
                                <i class="fas {{ $att->kind === 'template' ? 'fa-file-lines' : 'fa-book' }} fa-lg mt-1 text-{{ $att->kind === 'template' ? 'info' : 'success' }}"></i>
                                <span class="flex-grow-1">
                                    <span class="fw-semibold">{{ $att->title }}</span>
                                    <span class="badge bg-{{ $att->kind === 'template' ? 'info' : 'success' }} ms-2">{{ __(ucfirst($att->kind)) }}</span>
                                    @if($att->description)<span class="d-block text-muted small">{{ $att->description }}</span>@endif
                                    <span class="d-block text-muted small">{{ $att->file_name }} &middot; {{ number_format($att->file_size / 1024, 0) }} KB</span>
                                </span>
                                <i class="fas fa-download text-primary mt-1"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </article>

    {{-- Anonymous blog-style comments --}}
    <section id="comments" class="mx-auto mt-5 pt-4 border-top" style="max-width:820px;">
        <h2 class="h4 mb-3">
            <i class="far fa-comments me-2"></i>{{ __('Comments') }}
            <span class="text-muted fs-6">({{ count($comments ?? []) }})</span>
        </h2>

        @if(session('comment_success'))
            <div class="alert alert-success">{{ session('comment_success') }}</div>
        @endif
        @if(session('comment_error'))
            <div class="alert alert-warning">{{ session('comment_error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @forelse($comments ?? [] as $c)
            <div class="mb-3 pb-3 border-bottom">
                <div class="fw-semibold">
                    {{ $c->author_name ?: __('Anonymous') }}
                    <span class="text-muted fw-normal small ms-2">{{ \Carbon\Carbon::parse($c->created_at)->diffForHumans() }}</span>
                </div>
                <div class="mt-1" style="white-space:pre-wrap;">{{ $c->body }}</div>
            </div>
        @empty
            <p class="text-muted">{{ __('No comments yet. Be the first to comment.') }}</p>
        @endforelse

        <form method="POST" action="{{ route('articles.comment', $article->slug) }}" class="mt-4">
            @csrf
            <h3 class="h6">{{ __('Leave a comment') }}</h3>
            <div class="mb-2">
                <input type="text" name="author_name" maxlength="150" class="form-control"
                       placeholder="{{ __('Name (optional)') }}" value="{{ old('author_name') }}">
            </div>
            {{-- Honeypot: hidden from humans; bots fill it and get silently dropped. --}}
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="mb-2">
                <textarea name="body" rows="4" maxlength="4000" required class="form-control"
                          placeholder="{{ __('Your comment') }}">{{ old('body') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="far fa-paper-plane me-1"></i>{{ __('Post comment') }}
            </button>
        </form>
    </section>
</div>
@endsection
