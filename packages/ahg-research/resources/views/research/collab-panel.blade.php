@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-users me-2"></i>{{ __('Live collaboration') }} - {{ e($project->title) }}</h1>
    <p class="text-muted mb-0">{{ __('Presence + comment threads on evidence sets. Updates every 3 seconds (polling - no WebSocket broker on this host).') }}</p>
@endsection
@section('content')

<a href="{{ route('research.viewProject', $project->id) }}" class="btn btn-sm btn-outline-secondary mb-3">
    <i class="fas fa-arrow-left me-1"></i>{{ __('Back to project') }}
</a>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-user-friends me-2"></i>{{ __('Online now') }}</h6></div>
            <ul class="list-group list-group-flush" id="collab-presence">
                @foreach($presence as $p)
                    <li class="list-group-item d-flex align-items-center" data-researcher-id="{{ $p->researcher_id }}">
                        <span class="d-inline-block rounded-circle me-2" style="width:12px;height:12px;background:{{ $p->user_color ?? '#666' }}"></span>
                        <span>{{ e(trim(($p->first_name ?? '') . ' ' . ($p->last_name ?? ''))) ?: ('#' . $p->researcher_id) }}</span>
                    </li>
                @endforeach
                @if(empty($presence))
                    <li class="list-group-item text-muted text-center">{{ __('No-one online') }}</li>
                @endif
            </ul>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-comments me-2"></i>{{ __('Evidence comments') }}</h6></div>
            <div class="card-body" id="collab-comments">
                @foreach($comments as $c)
                    <div class="border rounded p-2 mb-2" data-comment-id="{{ $c->id }}">
                        <div class="d-flex justify-content-between">
                            <strong>{{ e(trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? ''))) ?: ('#' . $c->author_id) }}</strong>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($c->created_at)->diffForHumans() }}</small>
                        </div>
                        <div class="mt-1">{{ e($c->body) }}</div>
                        <div class="mt-1 small">
                            @if($c->status === 'resolved')
                                <span class="badge bg-success">{{ __('Resolved') }}</span>
                            @else
                                <button class="btn btn-sm btn-link p-0" onclick="collabResolve({{ $c->id }})">{{ __('Mark resolved') }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
                @if(empty($comments))
                    <div class="text-muted text-center py-3" id="collab-empty">{{ __('No comments yet.') }}</div>
                @endif
            </div>
            <div class="card-footer">
                <form id="collab-form" onsubmit="event.preventDefault(); collabPost();" class="d-flex gap-2">
                    @csrf
                    <input type="text" id="collab-body" class="form-control" placeholder="{{ __('Add a comment - everyone on this project sees it within 3s') }}" maxlength="5000" required>
                    <button class="btn btn-primary" type="submit">{{ __('Post') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    const projectId = {{ (int) $project->id }};
    const joinUrl   = "{{ route('research.collabJoin', $project->id) }}";
    const pollUrl   = "{{ route('research.collabPoll', $project->id) }}";
    const postUrl   = "{{ route('research.collabComment', $project->id) }}";
    const resolveBase = "{{ url('/research/projects/' . $project->id . '/realtime/comment') }}";
    const csrf      = "{{ csrf_token() }}";
    let cursor = {{ collect($comments)->max('id') ?? 0 }};

    function api(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {})
        }).then(r => r.json());
    }

    function renderPresence(list) {
        const root = document.getElementById('collab-presence');
        if (!root) return;
        root.innerHTML = '';
        if (!list.length) {
            root.innerHTML = '<li class="list-group-item text-muted text-center">No-one online</li>';
            return;
        }
        list.forEach(p => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex align-items-center';
            li.dataset.researcherId = p.researcher_id;
            li.innerHTML = '<span class="d-inline-block rounded-circle me-2" style="width:12px;height:12px;background:'
                + (p.user_color || '#666') + '"></span>'
                + '<span>' + ((p.first_name || '') + ' ' + (p.last_name || '')).trim() + '</span>';
            root.appendChild(li);
        });
    }

    function appendComment(c) {
        const empty = document.getElementById('collab-empty');
        if (empty) empty.remove();
        const root = document.getElementById('collab-comments');
        if (root.querySelector('[data-comment-id="' + c.id + '"]')) return;
        const div = document.createElement('div');
        div.className = 'border rounded p-2 mb-2';
        div.dataset.commentId = c.id;
        div.innerHTML = '<div class="d-flex justify-content-between">'
            + '<strong>' + ((c.first_name || '') + ' ' + (c.last_name || '')).trim() + '</strong>'
            + '<small class="text-muted">just now</small></div>'
            + '<div class="mt-1"></div>';
        div.querySelector('.mt-1').textContent = c.body;
        root.insertBefore(div, root.firstChild);
    }

    function poll() {
        fetch(pollUrl + '?since=' + cursor, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, method: 'POST', body: '_token=' + csrf, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                renderPresence(data.presence || []);
                (data.comments || []).forEach(appendComment);
                if (data.cursor) cursor = data.cursor;
            })
            .catch(() => {});
    }

    window.collabPost = function () {
        const inp = document.getElementById('collab-body');
        const body = inp.value.trim();
        if (!body) return;
        api(postUrl, { body: body }).then(c => {
            if (c && c.id) {
                inp.value = '';
                poll();
            }
        });
    };

    window.collabResolve = function (id) {
        api(resolveBase + '/' + id + '/resolve', {}).then(() => poll());
    };

    api(joinUrl, {});
    setInterval(poll, 3000);
})();
</script>
@endpush
@endsection
