@extends('theme::layouts.1col')
@section('title', __('Manage Articles'))
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <a href="{{ route('articles.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('View site') }}"><i class="fas fa-arrow-left"></i></a>
            <h1 class="mb-0"><i class="fas fa-newspaper me-2"></i>{{ __('Manage Articles') }}</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.articles.comments') }}" class="btn btn-outline-secondary"><i class="far fa-comments me-1"></i>{{ __('Comments') }}</a>
            <a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('New Article') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Group') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Reads') }}</th>
                        <th>{{ __('Published') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($articles as $a)
                        <tr>
                            <td><strong>{{ $a->title }}</strong><br><small class="text-muted"><code>{{ $a->slug }}</code></small></td>
                            <td>{{ $a->article_group ?: '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $a->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($a->status) }}</span>
                            </td>
                            <td class="text-end">{{ number_format($a->view_count ?? 0) }}</td>
                            <td>{{ $a->published_at ? \Carbon\Carbon::parse($a->published_at)->format('d M Y') : '—' }}</td>
                            <td class="text-end">
                                @if($a->status === 'published')
                                    <a href="{{ route('articles.show', $a->slug) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View') }}" target="_blank"><i class="fas fa-eye"></i></a>
                                @endif
                                <a href="{{ route('admin.articles.edit', $a->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
                                <form method="POST" action="{{ route('admin.articles.destroy', $a->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Delete this article?') }}');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No articles yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
