@extends('theme::layouts.1col')
@section('title', __('Articles'))
@section('content')
@php $isAdmin = auth()->check() && (auth()->user()->is_admin ?? false); @endphp
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h1 class="mb-0"><i class="fas fa-newspaper me-2"></i>{{ __('Articles') }}</h1>
        @if($isAdmin)
            <div class="d-flex gap-2">
                <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary"><i class="fas fa-cog me-1"></i>{{ __('Manage') }}</a>
                <a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('New Article') }}</a>
            </div>
        @endif
    </div>
    <p class="text-muted">{{ __('News, regulation and insight from The Archive and Heritage Group.') }}</p>

    {{-- Group filter pills --}}
    @if(!empty($groups))
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="{{ route('articles.index') }}" class="btn btn-sm {{ empty($activeGroup) ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('All') }}</a>
            @foreach($groups as $g)
                <a href="{{ route('articles.index', ['group' => $g]) }}" class="btn btn-sm {{ $activeGroup === $g ? 'btn-primary' : 'btn-outline-primary' }}">{{ $g }}</a>
            @endforeach
        </div>
    @endif

    @if($articles->isEmpty())
        <div class="alert alert-light border text-center text-muted py-5">
            <i class="fas fa-newspaper fa-2x mb-2 d-block"></i>{{ __('No articles published yet.') }}
        </div>
    @endif

    {{-- 3-per-row card grid --}}
    <div class="row g-4">
        @foreach($articles as $post)
            <div class="col-sm-6 col-lg-4 d-flex">
                <div class="card h-100 shadow-sm w-100">
                    @if($post->cover_image)
                        <a href="{{ route('articles.show', $post->slug) }}">
                            <img src="{{ $post->cover_image }}" class="card-img-top" alt="{{ $post->title }}" style="height:180px;object-fit:cover;">
                        </a>
                    @endif
                    <div class="card-body d-flex flex-column">
                        @if($post->article_group)<span class="badge bg-primary align-self-start mb-2">{{ $post->article_group }}</span>@endif
                        <h2 class="h5 card-title">
                            <a href="{{ route('articles.show', $post->slug) }}" class="text-decoration-none">{{ $post->title }}</a>
                        </h2>
                        <div class="text-muted small mb-2">
                            @if($post->published_at){{ \Carbon\Carbon::parse($post->published_at)->format('d M Y') }}@endif
                            @if($post->author) &middot; {{ $post->author }}@endif
                        </div>
                        @if($post->excerpt)
                            <p class="card-text flex-grow-1">{{ \Illuminate\Support\Str::limit($post->excerpt, 140) }}</p>
                        @endif
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('articles.show', $post->slug) }}" class="btn btn-sm btn-outline-primary">{{ __('Read more') }}</a>
                                @if($isAdmin)
                                    <a href="{{ route('admin.articles.edit', $post->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
                                @endif
                            </div>
                            @if(($post->attachment_count ?? 0) > 0)
                                <div class="mt-2">
                                    <span class="badge bg-primary fs-6 d-inline-flex align-items-center">
                                        <i class="fas fa-paperclip me-2"></i>{{ __('Files to download') }}
                                        <span class="badge bg-light text-primary ms-2">{{ $post->attachment_count }}</span>
                                    </span>
                                </div>
                            @endif
                            <div class="text-muted small mt-2 pt-2 border-top">
                                <i class="fas fa-eye me-1"></i>{{ number_format($post->view_count ?? 0) }} {{ trans_choice('read|reads', (int) ($post->view_count ?? 0)) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($articles->hasPages())
        <div class="mt-4 d-flex justify-content-center">
            {{ $articles->links() }}
        </div>
    @endif
</div>
@endsection
