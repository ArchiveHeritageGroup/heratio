@extends('theme::layouts.1col')

@section('title', __('Diff'))

@section('content')
@php
    use Illuminate\Support\Facades\DB;

    // Resolve term + actor labels for friendly rendering.
    $termIds = [];
    $actorIds = [];
    foreach (['access_points_added', 'access_points_removed'] as $s) {
        foreach (($diff[$s] ?? []) as $r) {
            if (!empty($r['term_id'])) { $termIds[(int) $r['term_id']] = true; }
        }
    }
    foreach (['events_added', 'events_removed', 'relations_added', 'relations_removed'] as $s) {
        foreach (($diff[$s] ?? []) as $r) {
            foreach (['actor_id', 'subject_id', 'object_id'] as $k) {
                if (!empty($r[$k])) { $actorIds[(int) $r[$k]] = true; }
            }
        }
    }
    $termLabels = [];
    if (!empty($termIds)) {
        $rows = DB::table('term')
            ->leftJoin('term_i18n', function ($j) {
                $j->on('term_i18n.id', '=', 'term.id')->where('term_i18n.culture', '=', 'en');
            })
            ->whereIn('term.id', array_keys($termIds))
            ->select('term.id', 'term_i18n.name', 'term.taxonomy_id')
            ->get();
        foreach ($rows as $r) {
            $termLabels[(int) $r->id] = ['name' => (string) ($r->name ?? ''), 'taxonomy_id' => (int) $r->taxonomy_id];
        }
    }
    $actorLabels = [];
    if (!empty($actorIds)) {
        $rows = DB::table('actor')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor_i18n.id', '=', 'actor.id')->where('actor_i18n.culture', '=', 'en');
            })
            ->whereIn('actor.id', array_keys($actorIds))
            ->select('actor.id', 'actor_i18n.authorized_form_of_name AS name')
            ->get();
        foreach ($rows as $r) {
            $actorLabels[(int) $r->id] = (string) ($r->name ?? '');
        }
    }

    $sectionLabels = [
        'access_points_added'      => __('Access points added'),
        'access_points_removed'    => __('Access points removed'),
        'events_added'             => __('Events added'),
        'events_removed'           => __('Events removed'),
        'relations_added'          => __('Relations added'),
        'relations_removed'        => __('Relations removed'),
        'physical_objects_added'   => __('Physical objects added'),
        'physical_objects_removed' => __('Physical objects removed'),
        'custom_fields_changes'    => __('Custom field changes'),
    ];
    $sectionIcons = [
        'access_points_added'      => 'fa-plus-circle text-success',
        'access_points_removed'    => 'fa-minus-circle text-danger',
        'events_added'             => 'fa-plus-circle text-success',
        'events_removed'           => 'fa-minus-circle text-danger',
        'relations_added'          => 'fa-plus-circle text-success',
        'relations_removed'        => 'fa-minus-circle text-danger',
        'physical_objects_added'   => 'fa-plus-circle text-success',
        'physical_objects_removed' => 'fa-minus-circle text-danger',
        'custom_fields_changes'    => 'fa-pen text-primary',
    ];

    $renderAccessPoint = function (array $r) use ($termLabels): string {
        $termId = (int) ($r['term_id'] ?? 0);
        $label = $termLabels[$termId]['name'] ?? "term #{$termId}";
        $taxonomyId = $termLabels[$termId]['taxonomy_id'] ?? null;
        $taxonomyLabel = match ($taxonomyId) {
            35 => 'subject', 42 => 'place', 38 => 'genre', 46 => 'name',
            default => 'term',
        };
        $extra = '';
        if (!empty($r['start_date']) || !empty($r['end_date'])) {
            $extra = ' (' . e(($r['start_date'] ?? '') . ' – ' . ($r['end_date'] ?? '')) . ')';
        }
        return '<span class="badge bg-light text-dark me-1">' . e($taxonomyLabel) . '</span>'
            . e($label) . $extra;
    };
    $renderRelation = function (array $r) use ($actorLabels): string {
        $subj = (int) ($r['subject_id'] ?? 0);
        $obj  = (int) ($r['object_id'] ?? 0);
        $subjLabel = $actorLabels[$subj] ?? "#{$subj}";
        $objLabel  = $actorLabels[$obj] ?? "#{$obj}";
        $type = (int) ($r['type_id'] ?? 0);
        return e($subjLabel) . ' <span class="text-muted small">→ type ' . $type . ' →</span> ' . e($objLabel);
    };
    $renderEvent = function (array $r) use ($actorLabels): string {
        $type = (int) ($r['type_id'] ?? 0);
        $actorId = (int) ($r['actor_id'] ?? 0);
        $objId   = (int) ($r['object_id'] ?? 0);
        $bits = ['type ' . $type];
        if ($actorId) { $bits[] = 'actor: ' . ($actorLabels[$actorId] ?? "#{$actorId}"); }
        if ($objId)   { $bits[] = 'object: #' . $objId; }
        if (!empty($r['start_date']) || !empty($r['end_date'])) {
            $bits[] = ($r['start_date'] ?? '') . ' – ' . ($r['end_date'] ?? '');
        }
        return e(implode(' · ', $bits));
    };

    $totalChanges = 0;
    foreach ($diff as $rows) {
        if (is_array($rows)) { $totalChanges += count($rows); }
    }
@endphp

<style>
  .vc-diff .vc-section { margin-top: 1.5rem; }
  .vc-diff .vc-section-header { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; padding-bottom: .3rem; border-bottom: 1px solid #dee2e6; }
  .vc-diff .vc-section-header h5 { margin: 0; }
  .vc-diff .vc-side { width: 50%; vertical-align: top; }
  .vc-diff .vc-side.old { background: #fef8f8; border-right: 1px solid #f5d3d3; }
  .vc-diff .vc-side.new { background: #f8fef9; }
  .vc-diff .field-name { font-family: monospace; color: #6c757d; font-size: .85rem; }
  .vc-diff .vc-text { white-space: pre-wrap; font-family: inherit; }
  .vc-diff ins { background: #d4edda; color: #155724; text-decoration: none; padding: 1px 2px; border-radius: 2px; }
  .vc-diff del { background: #f8d7da; color: #721c24; text-decoration: line-through; padding: 1px 2px; border-radius: 2px; }
  .vc-diff .vc-list-item { padding: .25rem 0; border-bottom: 1px dotted #eee; }
  .vc-diff .vc-list-item:last-child { border-bottom: none; }
  .vc-diff .vc-empty { padding: 2rem; text-align: center; color: #6c757d; background: #f8f9fa; border-radius: .375rem; }
</style>

<h1>
    {{ sprintf(__('Diff v%d → v%d'), $v1, $v2) }}
    <small class="text-muted">{{ $entityTitle }}</small>
</h1>

<p>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('version-control.list', ['entity' => $entityType, 'id' => $entityId]) }}">
        <i class="fas fa-arrow-left me-1"></i>{{ __('All versions') }}
    </a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('version-control.show', ['entity' => $entityType, 'id' => $entityId, 'number' => $v1]) }}">{{ sprintf(__('v%d details'), $v1) }}</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('version-control.show', ['entity' => $entityType, 'id' => $entityId, 'number' => $v2]) }}">{{ sprintf(__('v%d details'), $v2) }}</a>
</p>

<div class="vc-diff">

@if($totalChanges === 0)
    <div class="vc-empty">
        <i class="fas fa-equals fa-2x mb-2 d-block"></i>
        <strong>{{ __('No differences between these two versions.') }}</strong>
        <div class="small mt-1">{{ __('The snapshot is byte-equivalent. Versioning may have been triggered by a save that touched a field outside the snapshot scope.') }}</div>
    </div>
@endif

@php $scalars = $diff['scalar_changes'] ?? []; @endphp
@if(!empty($scalars))
<div class="vc-section">
    <div class="vc-section-header">
        <i class="fas fa-table text-primary"></i>
        <h5>{{ __('Base fields') }} <small class="text-muted">({{ count($scalars) }})</small></h5>
    </div>
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:22%">{{ __('Field') }}</th>
                <th class="vc-side old">{{ sprintf(__('v%d (old)'), $v1) }}</th>
                <th class="vc-side new">{{ sprintf(__('v%d (new)'), $v2) }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($scalars as $r)
            <tr>
                <td class="field-name">{{ $r['field'] ?? '' }}</td>
                @if(!empty($r['long_text_diff']))
                    <td colspan="2"><div class="vc-text">{!! $r['long_text_diff'] !!}</div></td>
                @else
                    <td class="vc-side old vc-text">{{ is_scalar($r['old'] ?? null) || ($r['old'] ?? null) === null ? (string) ($r['old'] ?? '') : json_encode($r['old']) }}</td>
                    <td class="vc-side new vc-text">{{ is_scalar($r['new'] ?? null) || ($r['new'] ?? null) === null ? (string) ($r['new'] ?? '') : json_encode($r['new']) }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@php
    $i18nChanges = $diff['i18n_changes'] ?? [];
    $byCulture = [];
    foreach ($i18nChanges as $r) {
        $c = $r['culture'] ?? '?';
        $byCulture[$c][] = $r;
    }
    ksort($byCulture);
@endphp
@foreach($byCulture as $culture => $rows)
<div class="vc-section">
    <div class="vc-section-header">
        <i class="fas fa-language text-info"></i>
        <h5>{{ __('Localized fields') }} <code class="ms-1">{{ $culture }}</code> <small class="text-muted">({{ count($rows) }})</small></h5>
    </div>
    <table class="table table-sm mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:22%">{{ __('Field') }}</th>
                <th class="vc-side old">{{ sprintf(__('v%d (old)'), $v1) }}</th>
                <th class="vc-side new">{{ sprintf(__('v%d (new)'), $v2) }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $r)
            <tr>
                <td class="field-name">
                    {{ $r['field'] ?? '' }}
                    @if(!empty($r['change_kind']))
                        <div><span class="badge bg-warning text-dark small">{{ $r['change_kind'] }}</span></div>
                    @endif
                </td>
                @if(!empty($r['long_text_diff']))
                    <td colspan="2"><div class="vc-text">{!! $r['long_text_diff'] !!}</div></td>
                @else
                    <td class="vc-side old vc-text">{{ is_scalar($r['old'] ?? null) || ($r['old'] ?? null) === null ? (string) ($r['old'] ?? '') : json_encode($r['old']) }}</td>
                    <td class="vc-side new vc-text">{{ is_scalar($r['new'] ?? null) || ($r['new'] ?? null) === null ? (string) ($r['new'] ?? '') : json_encode($r['new']) }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endforeach

@foreach($sectionLabels as $section => $label)
    @php $rows = $diff[$section] ?? []; @endphp
    @if(!empty($rows))
        <div class="vc-section">
            <div class="vc-section-header">
                <i class="fas {{ $sectionIcons[$section] }}"></i>
                <h5>{{ $label }} <small class="text-muted">({{ count($rows) }})</small></h5>
            </div>
            <div class="vc-list">
            @foreach($rows as $r)
                <div class="vc-list-item">
                    @if(str_starts_with($section, 'access_points'))
                        {!! $renderAccessPoint($r) !!}
                    @elseif(str_starts_with($section, 'relations'))
                        {!! $renderRelation($r) !!}
                    @elseif(str_starts_with($section, 'events'))
                        {!! $renderEvent($r) !!}
                    @elseif($section === 'custom_fields_changes')
                        <span class="field-name">field_definition_id={{ (int) ($r['field_definition_id'] ?? 0) }}</span>
                        @if(!empty($r['sequence'])) · seq {{ (int) $r['sequence'] }}@endif:
                        <code class="ms-1">{{ json_encode($r['old'] ?? null, JSON_UNESCAPED_SLASHES) }}</code>
                        <span class="text-muted">→</span>
                        <code>{{ json_encode($r['new'] ?? null, JSON_UNESCAPED_SLASHES) }}</code>
                    @else
                        <code>{{ json_encode($r, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code>
                    @endif
                </div>
            @endforeach
            </div>
        </div>
    @endif
@endforeach

</div>
@endsection
