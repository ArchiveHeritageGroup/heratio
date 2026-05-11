{{--
    Inline "Versions" panel for IO and actor show pages.

    Ported from PSIS templates/display/_versions_panel.php. Shows the most
    recent 5 versions plus a link to the full history page.

    Available variables:
      $resource - the entity object (must expose ->id)
      $context  - 'informationobject' | 'actor'

    The auto-injected discovery banner is added separately by
    AhgVersionControl\Http\Middleware\VersionLinkInjector - this partial is
    the richer inline embed that show pages can @include explicitly once
    they're unlocked.

    @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
    @license   AGPL-3.0-or-later
--}}
@php
    if (!isset($resource) || !isset($resource->id) || (int) $resource->id <= 0) {
        return;
    }

    $objectId = (int) $resource->id;
    $entityType = (($context ?? '') === 'actor') ? 'actor' : 'information_object';
    $versionTable = $entityType === 'actor' ? 'actor_version' : 'information_object_version';
    $fk = $entityType === 'actor' ? 'actor_id' : 'information_object_id';

    $totalCount = 0;
    $versions = collect();

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable($versionTable)) {
            $totalCount = (int) \Illuminate\Support\Facades\DB::table($versionTable)
                ->where($fk, $objectId)
                ->count();

            $versions = \Illuminate\Support\Facades\DB::table($versionTable)
                ->leftJoin('user', 'user.id', '=', $versionTable . '.created_by')
                ->where($fk, $objectId)
                ->orderBy('version_number', 'desc')
                ->limit(5)
                ->select(
                    $versionTable . '.version_number',
                    $versionTable . '.change_summary',
                    $versionTable . '.changed_fields',
                    $versionTable . '.created_at',
                    $versionTable . '.is_restore',
                    'user.username as created_by_username',
                )
                ->get();
        }
    } catch (\Throwable $e) {
        // Plugin tables may not be installed yet; render as empty.
    }
@endphp

@if($totalCount === 0)
    <p class="text-muted">{{ __('No versions captured yet.') }}</p>
@else
    <style nonce="{{ csp_nonce() }}">
        .vc-panel .vc-row { padding: .4rem .5rem; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
        .vc-panel .vc-row:last-child { border-bottom: none; }
        .vc-panel .vc-row .vc-num { font-weight: 600; }
        .vc-panel .vc-row .vc-meta { color: #6c757d; font-size: .8rem; }
        .vc-panel .badge-restore { background:#fff3cd; color:#856404; border:1px solid #ffeeba; }
    </style>

    <div class="vc-panel">
        <p class="text-muted mb-2">
            {{ sprintf(__('%d version(s) on record'), $totalCount) }} ·
            <a href="{{ url('/version-control/' . $entityType . '/' . $objectId) }}">
                {{ __('Full history') }} &rarr;
            </a>
        </p>

        @foreach($versions as $v)
            @php
                $changed = is_string($v->changed_fields) ? (json_decode($v->changed_fields, true) ?? []) : [];
                $changedCount = is_array($changed) ? count($changed) : 0;
                $detailUrl = url('/version-control/' . $entityType . '/' . $objectId . '/' . (int) $v->version_number);
            @endphp
            <div class="vc-row">
                <a href="{{ $detailUrl }}"><span class="vc-num">v{{ (int) $v->version_number }}</span></a>
                @if((int) ($v->is_restore ?? 0) === 1)
                    <span class="badge badge-restore">{{ __('restore') }}</span>
                @endif
                <span class="vc-meta">
                    {{ $v->created_at }}
                    @if(!empty($v->created_by_username)) &middot; {{ $v->created_by_username }} @endif
                    @if($changedCount > 0)
                        &middot; {{ sprintf(__('%d field(s) changed'), $changedCount) }}
                    @elseif($v->changed_fields !== null)
                        &middot; {{ __('no archival metadata changes') }}
                    @endif
                </span>
                @if(!empty($v->change_summary))
                    <div class="text-muted small">{{ $v->change_summary }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endif
