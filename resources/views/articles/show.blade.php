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
    </article>
</div>
@endsection
