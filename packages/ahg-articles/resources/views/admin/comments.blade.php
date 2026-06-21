@extends('theme::layouts.1col')
@section('title', __('Article Comments'))
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0"><i class="far fa-comments me-2"></i>{{ __('Article Comments') }}</h1>
        <a href="{{ route('admin.articles.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Articles') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Article') }}</th>
                        <th>{{ __('Author') }}</th>
                        <th>{{ __('Comment') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('When') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comments as $c)
                        <tr>
                            <td>
                                <a href="{{ route('articles.show', $c->article_slug) }}#comments" target="_blank">
                                    {{ \Illuminate\Support\Str::limit($c->article_title, 40) }}
                                </a>
                            </td>
                            <td>{{ $c->author_name ?: __('Anonymous') }}<div class="small text-muted">{{ $c->ip }}</div></td>
                            <td style="max-width:380px;white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($c->body, 220) }}</td>
                            <td>
                                <span class="badge bg-{{ $c->status === 'approved' ? 'success' : ($c->status === 'spam' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($c->status) }}
                                </span>
                            </td>
                            <td class="text-nowrap small">{{ \Carbon\Carbon::parse($c->created_at)->format('d M Y H:i') }}</td>
                            <td class="text-end text-nowrap">
                                @foreach(['approved' => 'Approve', 'spam' => 'Spam'] as $st => $label)
                                    @if($c->status !== $st)
                                        <form method="POST" action="{{ route('admin.articles.comments.status', $c->id) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <input type="hidden" name="status" value="{{ $st }}">
                                            <button class="btn btn-outline-{{ $st === 'spam' ? 'danger' : 'success' }} btn-sm">{{ __($label) }}</button>
                                        </form>
                                    @endif
                                @endforeach
                                <form method="POST" action="{{ route('admin.articles.comments.destroy', $c->id) }}" class="d-inline"
                                      onsubmit="return confirm('{{ __('Delete this comment?') }}');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">{{ __('No comments yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
