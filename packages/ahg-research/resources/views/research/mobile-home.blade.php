@extends('theme::layouts.app')
@section('title', __('Heratio Mobile'))
@push('meta')
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0d6efd">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Heratio">
@endpush
@section('content')
<div class="container-fluid px-2 py-2" style="max-width:640px">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Heratio</h4>
        <span class="badge bg-secondary" id="net-status">online</span>
    </div>

    @if($researcher)
        <div class="alert alert-light border d-flex align-items-center justify-content-between">
            <div>
                <strong>{{ e($researcher->first_name) }}</strong>
                <div class="small text-muted">{{ e($researcher->email) }}</div>
            </div>
            <a href="{{ route('research.profile') }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-user"></i></a>
        </div>
    @else
        <div class="alert alert-warning">
            <a href="{{ route('login') }}">{{ __('Log in') }}</a> {{ __('to sync your reading list and journal.') }}
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-6"><a href="{{ route('research.crossFondsQuery') }}" class="btn btn-outline-primary w-100"><i class="fas fa-network-wired me-1"></i> Search</a></div>
        <div class="col-6"><a href="{{ route('research.notebooks') }}" class="btn btn-outline-primary w-100"><i class="fas fa-sticky-note me-1"></i> Notes</a></div>
        <div class="col-6"><a href="{{ route('research.bibliographies') }}" class="btn btn-outline-primary w-100"><i class="fas fa-book me-1"></i> Bibliographies</a></div>
        <div class="col-6"><a href="{{ route('research.journal') }}" class="btn btn-outline-primary w-100"><i class="fas fa-journal-whills me-1"></i> Journal</a></div>
    </div>

    <h6 class="text-uppercase text-muted small mb-2">{{ __('Reading list') }}</h6>
    <div class="card">
        <ul class="list-group list-group-flush">
            @forelse($readingList as $r)
                <li class="list-group-item">
                    <a href="/{{ $r->slug ?? $r->object_id }}" class="text-decoration-none">{{ e($r->title ?? ('IO #' . $r->object_id)) }}</a>
                    <div class="small text-muted">{{ e($r->collection_name) }}</div>
                </li>
            @empty
                <li class="list-group-item text-muted text-center">{{ __('No items on your reading list yet.') }}</li>
            @endforelse
        </ul>
    </div>

    <h6 class="text-uppercase text-muted small mt-3 mb-2">{{ __('Quick journal entry') }}</h6>
    <form id="quick-journal" class="card" onsubmit="event.preventDefault(); queueJournal();">
        <div class="card-body">
            <input id="qj-title" class="form-control form-control-sm mb-2" placeholder="{{ __('Title') }}" maxlength="255" required>
            <textarea id="qj-body" class="form-control form-control-sm" rows="4" placeholder="{{ __('Type a note - it queues and syncs when you\'re online again') }}" required></textarea>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="small text-muted" id="qj-queue-count">{{ __('Queue: 0') }}</span>
            <button class="btn btn-sm btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</div>

@push('js')
<script>
(function () {
    // Register service worker (offline support + cache shell)
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function (err) { console.warn('sw register failed', err); });
    }

    var status = document.getElementById('net-status');
    function refreshStatus() {
        if (navigator.onLine) {
            status.textContent = 'online';
            status.className = 'badge bg-success';
            flush();
        } else {
            status.textContent = 'offline';
            status.className = 'badge bg-warning text-dark';
        }
    }
    window.addEventListener('online', refreshStatus);
    window.addEventListener('offline', refreshStatus);
    refreshStatus();

    // Tiny IndexedDB queue. Falls back to localStorage on antique browsers.
    var QKEY = 'heratio_offline_queue_v1';
    function readQueue()  { try { return JSON.parse(localStorage.getItem(QKEY) || '[]'); } catch (e) { return []; } }
    function writeQueue(q){ localStorage.setItem(QKEY, JSON.stringify(q)); document.getElementById('qj-queue-count').textContent = 'Queue: ' + q.length; }
    writeQueue(readQueue());

    window.queueJournal = function () {
        var t = document.getElementById('qj-title').value.trim();
        var b = document.getElementById('qj-body').value.trim();
        if (!t || !b) return;
        var q = readQueue();
        q.push({
            kind: 'journal_entry',
            entry_type: 'note',
            entry_date: new Date().toISOString().slice(0,10),
            title: t,
            content: b,
            queued_at: Date.now(),
        });
        writeQueue(q);
        document.getElementById('qj-title').value = '';
        document.getElementById('qj-body').value  = '';
        flush();
    };

    function flush() {
        if (!navigator.onLine) return;
        var q = readQueue();
        if (!q.length) return;
        fetch("{{ route('research.offlineSync') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ queue: q }),
        })
        .then(r => r.json())
        .then(res => {
            if (res && typeof res.applied === 'number' && res.applied >= 0) {
                writeQueue([]);
            }
        })
        .catch(() => {});
    }
})();
</script>
@endpush
@endsection
