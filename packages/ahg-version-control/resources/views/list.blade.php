@extends('theme::layouts.1col')

@section('title', __('Version history'))

@section('content')
<style>
  .vc-list .badge-restore { background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
  .vc-list .badge-no-change { background:#e9ecef; color:#495057; }
  .vc-list .badge-changes { background:#d4edda; color:#155724; }
  .vc-list code.fields { font-size: 0.85rem; color:#495057; }
  .vc-list tbody tr:hover { background:#f8f9fa; }
  .vc-list .restored-from { font-size: 0.85rem; color:#856404; }
</style>

<h1>
    {{ __('Version history') }}
    <small class="text-muted">{{ $entityTitle }}</small>
</h1>

<p>
    @if($entitySlug)
        <a class="btn btn-outline-secondary btn-sm" href="/{{ $entitySlug }}">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to record') }}
        </a>
    @endif
    <span class="text-muted ms-2">{{ sprintf(__('%d version(s)'), $totalCount) }}</span>
</p>

@if($totalCount === 0)
    <div class="alert alert-info">
        {{ __('No versions have been captured for this record yet. A version is written automatically the next time the record is saved.') }}
    </div>
@else

<form id="vc-diff-form" method="get" action="{{ url('/version-control/' . $entityType . '/' . $entityId . '/diff/PLACEHOLDER_V1/PLACEHOLDER_V2') }}" class="mb-2">
    <div class="d-flex align-items-center gap-2">
        <button type="submit" class="btn btn-outline-primary btn-sm" disabled id="vc-compare-btn">
            <i class="fas fa-code-compare me-1"></i>{{ __('Compare selected') }}
        </button>
        <small class="text-muted">{{ __('Tick two versions to compare them') }}</small>
    </div>
</form>

<table class="table table-sm vc-list">
    <thead>
        <tr>
            <th style="width:36px"></th>
            <th style="width:84px">{{ __('Version') }}</th>
            <th style="width:170px">{{ __('Date') }}</th>
            <th style="width:140px">{{ __('User') }}</th>
            <th>{{ __('Summary') }}</th>
            <th style="width:170px">{{ __('Changes') }}</th>
            <th style="width:60px"></th>
        </tr>
    </thead>
    <tbody>
    @foreach($versions as $v)
        @php
            $detailUrl = route('version-control.show', ['entity' => $entityType, 'id' => $entityId, 'number' => (int) $v->version_number]);
            $changed = is_string($v->changed_fields) ? (json_decode($v->changed_fields, true) ?? []) : [];
            $changedCount = is_array($changed) ? count($changed) : 0;
        @endphp
        <tr>
            <td><input type="checkbox" class="vc-pick" value="{{ (int) $v->version_number }}"></td>
            <td>
                <a href="{{ $detailUrl }}"><strong>v{{ (int) $v->version_number }}</strong></a>
                @if((int) $v->is_restore === 1)
                    <span class="badge badge-restore">{{ __('restore') }}</span>
                @endif
            </td>
            <td>{{ $v->created_at }}</td>
            <td>{{ $v->created_by_username ?? '—' }}</td>
            <td>
                {{ $v->change_summary ?: '—' }}
                @if((int) $v->is_restore === 1 && $v->restored_from_version)
                    <div class="restored-from">↩ {{ sprintf(__('Restored from v%d'), (int) $v->restored_from_version) }}</div>
                @endif
            </td>
            <td>
                @if($changedCount === 0 && $v->changed_fields !== null)
                    <span class="badge badge-no-change">{{ __('no archival metadata changes') }}</span>
                @elseif($changedCount > 0)
                    <span class="badge badge-changes">{{ sprintf(__('%d field(s)'), $changedCount) }}</span>
                    <div><code class="fields">{{ implode(', ', array_slice((array) $changed, 0, 3)) }}{{ $changedCount > 3 ? '…' : '' }}</code></div>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td><a class="btn btn-sm btn-outline-secondary" href="{{ $detailUrl }}">{{ __('View') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>

@if($totalPages > 1)
<nav>
    <ul class="pagination pagination-sm">
        @for($p = 1; $p <= $totalPages; $p++)
            <li class="page-item {{ $p === $page ? 'active' : '' }}">
                <a class="page-link" href="{{ route('version-control.list', ['entity' => $entityType, 'id' => $entityId, 'page' => $p]) }}">{{ $p }}</a>
            </li>
        @endfor
    </ul>
</nav>
@endif

<script>
(function () {
    var picks = document.querySelectorAll('.vc-pick');
    var btn = document.getElementById('vc-compare-btn');
    var form = document.getElementById('vc-diff-form');
    var entityType = @json($entityType);
    var entityId = @json($entityId);
    function refresh() {
        var checked = Array.from(picks).filter(function (c) { return c.checked; });
        btn.disabled = checked.length !== 2;
        picks.forEach(function (c) { if (!c.checked) c.disabled = checked.length >= 2; });
    }
    picks.forEach(function (c) { c.addEventListener('change', refresh); });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var checked = Array.from(picks).filter(function (c) { return c.checked; })
            .map(function (c) { return parseInt(c.value, 10); })
            .sort(function (a, b) { return a - b; });
        if (checked.length !== 2) return;
        window.location.href = '/version-control/' + entityType + '/' + entityId + '/diff/' + checked[0] + '/' + checked[1];
    });
})();
</script>
@endif
@endsection
