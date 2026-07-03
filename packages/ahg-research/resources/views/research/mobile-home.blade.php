@extends('theme::layouts.app')
@section('title', __('Work Offline'))
@push('meta')
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#0d6efd">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Heratio">
@endpush
@section('content')
<div class="container-fluid px-2 px-md-3 py-3" style="max-width:960px">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h3 mb-0"><i class="fas fa-laptop me-2"></i>{{ __('Work Offline') }}</h1>
        <span class="badge bg-secondary" id="net-status">online</span>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif

    @unless($researcher)
        <div class="alert alert-warning">
            <a href="{{ route('login') }}">{{ __('Log in') }}</a>
            {{ __('with a researcher profile to take your collected records offline.') }}
        </div>
    @else
    @php
        $groups = [
            ['label' => __('Collections'),        'icon' => 'fa-layer-group',     'field' => 'collection_ids', 'items' => $collections],
            ['label' => __('Projects'),           'icon' => 'fa-project-diagram', 'field' => 'project_ids',    'items' => $projects],
            ['label' => __('Favourites folders'), 'icon' => 'fa-star',            'field' => 'folder_ids',     'items' => $folders],
        ];
        $hasAny = count($collections) + count($projects) + count($folders) > 0;
        $anyBuilding = collect($packages)->contains(fn ($p) => in_array($p->status ?? '', ['pending', 'running', 'processing'], true));
    @endphp

    <div class="row g-4">
        {{-- STEP 1: take records offline --}}
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-download me-1"></i>{{ __('1. Take records offline') }}
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        {{ __('Choose what to take with you, then download a self-contained package. It opens in any web browser with no internet or login — add notes, sources, suggestions and files, then bring them back below. Only records you are permitted to see are included.') }}
                    </p>

                    @unless($hasAny)
                        <div class="alert alert-info small mb-0">
                            {{ __('You have no collections, projects or favourites folders yet. Add records to a Collection, Project or Favourites folder first.') }}
                        </div>
                    @else
                        <form method="POST" action="{{ route('research.buildOfflinePackage') }}">
                            @csrf
                            @foreach($groups as $g)
                                @if(!empty($g['items']))
                                    <div class="list-group mb-3">
                                        <span class="list-group-item bg-light fw-bold text-uppercase small">
                                            <i class="fas {{ $g['icon'] }} me-1"></i>{{ $g['label'] }}
                                        </span>
                                        @foreach($g['items'] as $it)
                                            <label class="list-group-item d-flex align-items-center">
                                                <input class="form-check-input me-2 mt-0" type="checkbox" name="{{ $g['field'] }}[]" value="{{ (int) $it->id }}">
                                                <span class="flex-grow-1">{{ e($it->name) }}</span>
                                                <span class="badge bg-secondary rounded-pill">{{ (int) ($it->item_count ?? 0) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-download me-1"></i>{{ __('Download offline package') }}
                            </button>
                        </form>
                    @endunless

                    @if(!empty($packages))
                        <hr>
                        <h6 class="text-uppercase text-muted small mb-2">{{ __('Your packages') }}</h6>
                        <ul class="list-group">
                            @foreach($packages as $p)
                                <li class="list-group-item d-flex align-items-center justify-content-between">
                                    <div class="me-2">
                                        <div>{{ e($p->title ?? ('Package #'.$p->id)) }}</div>
                                        <div class="small text-muted">{{ ucfirst($p->status ?? 'pending') }}@if(($p->status ?? '') !== 'completed') · {{ (int) ($p->progress ?? 0) }}%@endif</div>
                                    </div>
                                    @if(($p->status ?? '') === 'completed')
                                        <a href="{{ route('research.offline.download', ['id' => $p->id]) }}" class="btn btn-sm btn-success">
                                            <i class="fas fa-download me-1"></i>{{ __('Download') }}
                                        </a>
                                    @elseif(($p->status ?? '') === 'failed')
                                        <span class="badge bg-danger">{{ __('Failed') }}</span>
                                    @else
                                        <span class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        {{-- STEP 2: bring your work back --}}
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-cloud-arrow-up me-1"></i>{{ __('2. Bring your work back') }}
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        {{ __('Finished working offline? In the package, click "Save for sync" to download a researcher-sync.json file, then upload it here. Your notes and sources are added to your research, files are attached, and metadata suggestions go to a curator for review.') }}
                    </p>
                    <form method="POST" action="{{ route('research.syncUpload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-1">
                            <input type="file" name="sync_file" accept="application/json,.json" class="form-control" required>
                        </div>
                        <div class="form-text mb-2">{!! __('Choose the <strong>researcher-sync.json</strong> file you downloaded from the package.') !!}</div>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-cloud-arrow-up me-1"></i>{{ __('Upload & sync') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-circle-info me-1"></i>{{ __('How it works') }}</div>
                <div class="card-body small text-muted">
                    <ol class="mb-0 ps-3">
                        <li>{{ __('Pick collections / projects / favourites and download the package.') }}</li>
                        <li>{{ __('Open index.html in any browser — no internet needed.') }}</li>
                        <li>{{ __('Add notes, sources, suggestions, files to records.') }}</li>
                        <li>{{ __('Click "Save for sync" → get researcher-sync.json.') }}</li>
                        <li>{{ __('Upload it here to bring everything back.') }}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    @endunless
</div>

@push('js')
<script>
(function () {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(function (err) { console.warn('sw register failed', err); });
    }
    var status = document.getElementById('net-status');
    function refreshStatus() {
        if (!status) return;
        if (navigator.onLine) { status.textContent = 'online'; status.className = 'badge bg-success'; }
        else { status.textContent = 'offline'; status.className = 'badge bg-warning text-dark'; }
    }
    window.addEventListener('online', refreshStatus);
    window.addEventListener('offline', refreshStatus);
    refreshStatus();

    // Auto-refresh while a package is still building so the Download button appears.
    @if($researcher && $anyBuilding)
    if (navigator.onLine) { setTimeout(function () { window.location.reload(); }, 6000); }
    @endif
})();
</script>
@endpush
@endsection
