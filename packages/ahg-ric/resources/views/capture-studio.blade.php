@extends('theme::layouts.1col')
@section('title', 'OpenRiC Capture Studio')
@section('body-class', 'admin ric ric-capture')

@push('head')
<style>
    .ric-capture .studio-hero {
        background: linear-gradient(135deg, #1f3a5f 0%, #2c5282 100%);
        color: #fff;
        padding: 2.5rem 2rem 2rem;
        border-radius: 10px;
        margin-bottom: 1.75rem;
    }
    .ric-capture .studio-hero h1 { margin: 0 0 0.5rem; font-weight: 600; }
    .ric-capture .studio-hero p { margin: 0; opacity: 0.9; max-width: 70ch; }
    .ric-capture .studio-hero .hero-meta { margin-top: 1rem; font-size: 0.85rem; opacity: 0.85; }
    .ric-capture .studio-hero .hero-meta code { background: rgba(255,255,255,0.15); color: #fff; padding: 0.1rem 0.4rem; border-radius: 3px; }

    .ric-capture .entity-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.25rem;
        height: 100%;
        background: #fff;
        display: flex; flex-direction: column;
        transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
    }
    .ric-capture .entity-card:hover { border-color: #3182ce; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(49,130,206,0.12); }
    .ric-capture .entity-card .entity-icon { font-size: 2rem; color: #3182ce; margin-bottom: 0.75rem; }
    .ric-capture .entity-card h3 { margin: 0 0 0.5rem; font-size: 1.15rem; font-weight: 600; }
    .ric-capture .entity-card .entity-desc { color: #4a5568; font-size: 0.88rem; line-height: 1.5; flex: 1; }
    .ric-capture .entity-card .entity-count { font-size: 0.82rem; color: #718096; margin: 0.75rem 0 0.5rem; font-family: monospace; }
    .ric-capture .entity-card .entity-actions { margin-top: 0.75rem; display: flex; gap: 0.5rem; }
    .ric-capture .entity-card .entity-actions .btn { flex: 1; font-size: 0.85rem; }

    .ric-capture .relation-strip {
        background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px;
        padding: 1rem 1.25rem; margin-top: 1rem;
        display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    }
    .ric-capture .relation-strip .relation-icon { font-size: 1.4rem; color: #805ad5; }
    .ric-capture .relation-strip .relation-text { flex: 1; }
    .ric-capture .relation-strip h4 { margin: 0 0 0.2rem; font-size: 1rem; }
    .ric-capture .relation-strip .rel-count { color: #718096; font-size: 0.85rem; }

    .ric-capture .recent-section { margin-top: 2rem; }
    .ric-capture .recent-section h2 { font-size: 1.1rem; margin-bottom: 0.75rem; color: #2d3748; }
    .ric-capture .recent-table { font-size: 0.88rem; }
    .ric-capture .recent-table .type-badge { font-size: 0.72rem; padding: 0.1rem 0.45rem; border-radius: 3px; background: #edf2f7; color: #2d3748; }

    .ric-capture .search-bar { margin: 1rem 0 1.5rem; }
    .ric-capture .search-bar .input-group { max-width: 560px; }
</style>
@endpush

@section('content')
@once
@include('ahg-ric::_ric-api-base')
@endonce
<section class="studio-hero">
    <h1><i class="fas fa-cube me-2"></i>OpenRiC Capture Studio</h1>
    <p>Focused workspace for creating Records-in-Contexts entities: Places, Rules, Activities, Instantiations, and the relations between them. Every entity you create here is immediately served through the OpenRiC API and visible to external clients.</p>
    <div class="hero-meta">
        API base: <code>{{ $ricApiBase }}</code>
        &nbsp;·&nbsp; Spec: <a href="https://openric.org" target="_blank" rel="noopener" style="color:#fff; text-decoration: underline;">openric.org</a>
        &nbsp;·&nbsp; Live viewer: <a href="https://viewer.openric.org" target="_blank" rel="noopener" style="color:#fff; text-decoration: underline;">viewer.openric.org</a>
    </div>
</section>

<div class="search-bar">
    <div class="input-group">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" id="capture-search" class="form-control" placeholder="{{ __('Find a RiC entity across all types…') }}" autocomplete="off">
    </div>
    <div id="capture-search-results" class="list-group position-absolute mt-1" style="z-index: 100; max-width: 560px; max-height: 400px; overflow-y: auto; display: none;"></div>
</div>

<div class="row g-3">
    @foreach($types as $t)
    <div class="col-md-6 col-lg-3">
        <div class="entity-card">
            <div class="entity-icon"><i class="fas {{ $t['icon'] }}"></i></div>
            <h3>{{ $t['title'] }}</h3>
            <p class="entity-desc">{{ $t['description'] }}</p>
            <div class="entity-count">{{ number_format($t['count']) }} existing</div>
            <div class="entity-actions">
                <a href="{{ route('ric.entities.create', [$t['key']]) }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> {{ __('Create') }}
                </a>
                <a href="{{ route('ric.' . $t['key'] . '.browse') }}" class="btn btn-outline-secondary btn-sm">
                    Browse
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="relation-strip">
    <div class="relation-icon"><i class="fas fa-link"></i></div>
    <div class="relation-text">
        <h4>{{ __('Relations between entities') }}</h4>
        <div class="rel-count">{{ number_format($relationCount) }} canonical <code>rico:*</code> relations across the triple store.</div>
    </div>
    <a href="{{ route('ric.relations.browse') }}" class="btn btn-outline-primary btn-sm">Browse all relations</a>
    <span class="text-muted small">{{ __("Create relations inline on any entity's show page via the relation editor.") }}</span>
</div>

<section class="recent-section">
    <h2><i class="fas fa-history me-2"></i>Recent captures</h2>
    @if($recent->isEmpty())
        <p class="text-muted">No entities have been captured yet. Pick a type above to create the first one.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover recent-table">
                <thead>
                    <tr>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Label') }}</th>
                        <th>{{ __('Created') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recent as $row)
                    <tr>
                        <td><span class="type-badge"><i class="fas {{ $row->type_icon }} me-1"></i>{{ $row->type_title }}</span></td>
                        <td>{{ $row->label ?: '(unnamed)' }}</td>
                        <td><small class="text-muted">{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->diffForHumans() : '' }}</small></td>
                        <td>
                            @if($row->slug)
                                <a href="{{ route('ric.entities.show', [$row->type_key, $row->slug]) }}" class="btn btn-link btn-sm p-0">View</a>
                                &middot;
                                <a href="{{ route('ric.entities.edit', [$row->type_key, $row->slug]) }}" class="btn btn-link btn-sm p-0">Edit</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<script>
(function () {
    const input = document.getElementById('capture-search');
    const resultsEl = document.getElementById('capture-search-results');
    let debounce;

    input.addEventListener('input', function () {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { resultsEl.style.display = 'none'; return; }
        debounce = setTimeout(() => {
            fetch(`${RIC_API_BASE}/autocomplete?q=${encodeURIComponent(q)}&types=place,rule,activity,instantiation&limit=15`)
                .then(r => r.json())
                .then(items => {
                    if (!Array.isArray(items) || !items.length) {
                        resultsEl.innerHTML = '<div class="list-group-item text-muted small">No matches.</div>';
                        resultsEl.style.display = '';
                        return;
                    }
                    const typeToKey = { Place: 'places', Rule: 'rules', Activity: 'activities', Instantiation: 'instantiations' };
                    resultsEl.innerHTML = items.map(i => {
                        const key = typeToKey[i.type] || null;
                        const href = key ? `/admin/ric/entities/${key}/${i.id}` : '#';
                        const safe = String(i.label || '').replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;','\'':'&#39;'}[c]));
                        return `<a href="${href}" class="list-group-item list-group-item-action py-1 px-2">
                            <span class="badge bg-secondary me-2">${i.type}</span>${safe}
                        </a>`;
                    }).join('');
                    resultsEl.style.display = '';
                })
                .catch(() => { resultsEl.style.display = 'none'; });
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (!resultsEl.contains(e.target) && e.target !== input) resultsEl.style.display = 'none';
    });
})();
</script>
@endsection
