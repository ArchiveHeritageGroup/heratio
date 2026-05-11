@extends('theme::layouts.1col')

@section('title', __('Version :n', ['n' => $versionNumber]))

@section('content')
@php
    $base = is_array($snapshot['base'] ?? null) ? $snapshot['base'] : [];
    $i18n = is_array($snapshot['i18n'] ?? null) ? $snapshot['i18n'] : [];
    $ap = is_array($snapshot['access_points'] ?? null) ? $snapshot['access_points'] : [];
    $ev = is_array($snapshot['events'] ?? null) ? $snapshot['events'] : [];
    $rel = is_array($snapshot['relations'] ?? null) ? $snapshot['relations'] : [];
    $po = is_array($snapshot['physical_objects'] ?? null) ? $snapshot['physical_objects'] : [];
    $cf = is_array($snapshot['custom_fields'] ?? null) ? $snapshot['custom_fields'] : [];
@endphp

<h1>
    {{ sprintf(__('Version %d'), $versionNumber) }}
    <small class="text-muted">{{ $entityTitle }}</small>
</h1>

@if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('notice') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<p>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('version-control.list', ['entity' => $entityType, 'id' => $entityId]) }}">
        <i class="fas fa-arrow-left me-1"></i>{{ __('All versions') }}
    </a>
    @if($entitySlug)
        <a class="btn btn-outline-secondary btn-sm" href="/{{ $entitySlug }}">
            <i class="fas fa-eye me-1"></i>{{ __('View record') }}
        </a>
    @endif
    @if($versionNumber > 1)
        <a class="btn btn-outline-primary btn-sm" href="{{ route('version-control.diff', ['entity' => $entityType, 'id' => $entityId, 'v1' => $versionNumber - 1, 'v2' => $versionNumber]) }}">
            <i class="fas fa-code-compare me-1"></i>{{ sprintf(__('Diff v%d → v%d'), $versionNumber - 1, $versionNumber) }}
        </a>
    @endif
    <button type="button" class="btn btn-warning btn-sm ms-2" data-bs-toggle="modal" data-bs-target="#vc-restore-modal">
        <i class="fas fa-undo me-1"></i>{{ sprintf(__('Restore this version (v%d)'), $versionNumber) }}
    </button>
</p>

<div class="modal fade" id="vc-restore-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('version-control.restore', ['entity' => $entityType, 'id' => $entityId, 'number' => $versionNumber]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ sprintf(__('Restore version %d?'), $versionNumber) }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>{!! sprintf(__('You are about to overwrite the current state of <strong>%s</strong> with the snapshot from v%d (captured %s).'), e($entityTitle), $versionNumber, e($version->created_at)) !!}</p>
                    <p class="text-muted small mb-2">
                        {{ __('A new version will be created marking the restore (is_restore=1, restored_from_version=' . $versionNumber . ').') }}
                    </p>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>{{ __('Scope of restore (v1):') }}</strong>
                        {{ __('Base record fields + descriptive metadata (titles, scope, notes, all cultures) + custom fields.') }}
                        {{ __('Access points, events, relationships and physical-object links are NOT restored — they stay as they are currently. Full restore of these is a planned enhancement.') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo me-1"></i>{{ __('Confirm restore') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">{{ __('Version details') }}</h5></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('Version number') }}</dt>
            <dd class="col-sm-9">v{{ $versionNumber }}
                @if((int) $version->is_restore === 1)
                    <span class="badge bg-warning text-dark">{{ __('restore') }}</span>
                    @if($version->restored_from_version)
                        <span class="text-muted">↩ {{ sprintf(__('from v%d'), (int) $version->restored_from_version) }}</span>
                    @endif
                @endif
            </dd>

            <dt class="col-sm-3">{{ __('Created at') }}</dt>
            <dd class="col-sm-9">{{ $version->created_at }}</dd>

            <dt class="col-sm-3">{{ __('Created by') }}</dt>
            <dd class="col-sm-9">{{ $version->created_by_username ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('Summary') }}</dt>
            <dd class="col-sm-9">{{ $version->change_summary ?: '—' }}</dd>

            <dt class="col-sm-3">{{ __('Changed fields') }}</dt>
            <dd class="col-sm-9">
                @if(!empty($changedFields))
                    <ul class="mb-0">
                        @foreach($changedFields as $f)
                            <li><code>{{ $f }}</code></li>
                        @endforeach
                    </ul>
                @else
                    <span class="text-muted">{{ __('No archival metadata changes (or first version)') }}</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">{{ __('Snapshot') }}</h5>
        <small class="text-muted">{{ __('Schema version') }} {{ (int) ($snapshot['schema_version'] ?? 0) }} · {{ __('Captured at') }} {{ $snapshot['captured_at'] ?? '' }}</small>
    </div>
    <div class="card-body">

        <h6>{{ __('Base') }} <small class="text-muted">({{ count($base) }} {{ __('fields') }})</small></h6>
        <table class="table table-sm">
            <tbody>
            @foreach($base as $k => $val)
                <tr>
                    <th style="width:30%"><code>{{ $k }}</code></th>
                    <td>{{ is_scalar($val) || $val === null ? (string) $val : json_encode($val) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <h6 class="mt-3">{{ __('Localized fields') }} <small class="text-muted">({{ count($i18n) }} {{ __('cultures') }})</small></h6>
        @foreach($i18n as $row)
            <details class="mb-2">
                <summary><strong>{{ $row['culture'] ?? '?' }}</strong></summary>
                <table class="table table-sm mt-1">
                    <tbody>
                    @foreach($row as $k => $val)
                        @if($k === 'culture' || $k === 'id' || $val === null || $val === '') @continue @endif
                        <tr>
                            <th style="width:30%"><code>{{ $k }}</code></th>
                            <td>{!! nl2br(e(is_scalar($val) ? (string) $val : json_encode($val))) !!}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </details>
        @endforeach

        <h6 class="mt-3">{{ __('Access points, events, relations') }}</h6>
        <ul>
            <li>{{ sprintf(__('Access points: %d'), count($ap)) }}</li>
            <li>{{ sprintf(__('Events: %d'), count($ev)) }}</li>
            <li>{{ sprintf(__('Relations: %d'), count($rel)) }}</li>
            <li>{{ sprintf(__('Physical objects: %d'), count($po)) }}</li>
            <li>{{ sprintf(__('Custom fields: %d'), count($cf)) }}</li>
        </ul>

    </div>
</div>
@endsection
