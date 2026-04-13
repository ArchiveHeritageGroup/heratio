{{--
  AI Condition Dashboard - Heratio clone of PSIS ahgAiCondition dashboardSuccess.php
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.2col')

@section('title', __('AI Condition Dashboard'))
@section('body-class', 'ai ai-condition dashboard')

@section('sidebar')
<div class="sidebar-content">
    <div class="card mb-3">
        <div class="card-header bg-success text-white py-2">
            <h6 class="mb-0"><i class="fas fa-robot me-1"></i>{{ __('AI Condition') }}</h6>
        </div>
        <div class="card-body py-2">
            <a href="{{ route('admin.ai.condition.assess') }}" class="btn btn-success btn-sm w-100 mb-2">
                <i class="fas fa-camera me-1"></i>{{ __('New AI Assessment') }}
            </a>
            <a href="{{ route('admin.ai.condition.manual') }}" class="btn btn-primary btn-sm w-100 mb-2">
                <i class="fas fa-clipboard-check me-1"></i>{{ __('Manual Assessment') }}
            </a>
            <a href="{{ route('admin.ai.condition.bulk') }}" class="btn btn-outline-primary btn-sm w-100 mb-2">
                <i class="fas fa-layer-group me-1"></i>{{ __('Bulk Scan') }}
            </a>
            <a href="{{ route('admin.ai.condition.browse') }}" class="btn btn-outline-secondary btn-sm w-100 mb-2">
                <i class="fas fa-list me-1"></i>{{ __('Browse Assessments') }}
            </a>
            <a href="{{ route('admin.ai.condition.training') }}" class="btn btn-outline-info btn-sm w-100 mb-2">
                <i class="fas fa-brain me-1"></i>{{ __('Model Training') }}
            </a>
            <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                <i class="fas fa-cog me-1"></i>{{ __('Settings') }}
            </a>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1 class="h3 mb-0"><i class="fas fa-tachometer-alt me-2"></i>{{ __('AI Condition Dashboard') }}</h1>
<p class="text-muted small mb-3">{{ __('Assessment statistics, grade distribution, and trends') }}</p>
@endsection

@section('content')

<!-- Summary Cards Row -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-success">{{ $stats['total'] ?? 0 }}</div>
                <small class="text-muted">{{ __('Total Assessments') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-primary">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-primary">{{ $stats['confirmed'] ?? 0 }}</div>
                <small class="text-muted">{{ __('Confirmed') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body py-3">
                <div class="fs-2 fw-bold text-warning">{{ $stats['pending'] ?? 0 }}</div>
                <small class="text-muted">{{ __('Pending Review') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body py-3">
                @php
                    $avgScore = $stats['avg_score'] ?? 0;
                    $scoreColor = $avgScore >= 80 ? 'success' : ($avgScore >= 60 ? 'info' : ($avgScore >= 40 ? 'warning' : 'danger'));
                @endphp
                <div class="fs-2 fw-bold text-{{ $scoreColor }}">{{ $avgScore }}</div>
                <small class="text-muted">{{ __('Average Score') }}</small>
            </div>
        </div>
    </div>
</div>

<!-- Grade Distribution + Source Breakdown -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>{{ __('Grade Distribution') }}</h6></div>
            <div class="card-body">
                @php
                    $gradeColors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger', 'critical' => 'dark'];
                    $gradeIcons = ['excellent' => 'fa-check-circle', 'good' => 'fa-thumbs-up', 'fair' => 'fa-exclamation-triangle', 'poor' => 'fa-times-circle', 'critical' => 'fa-skull-crossbones'];
                    $byGrade = $stats['by_grade'] ?? [];
                    $totalAssessments = max(1, $stats['total'] ?? 1);
                @endphp
                @foreach (['excellent', 'good', 'fair', 'poor', 'critical'] as $grade)
                    @php
                        $count = $byGrade[$grade] ?? 0;
                        $pct = round(($count / $totalAssessments) * 100);
                        $color = $gradeColors[$grade] ?? 'secondary';
                        $icon = $gradeIcons[$grade] ?? 'fa-question';
                    @endphp
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-{{ $color }} me-2" style="min-width:100px">
                            <i class="fas {{ $icon }} me-1"></i>{{ ucfirst($grade) }}
                        </span>
                        <div class="progress flex-grow-1" style="height:12px">
                            <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                        </div>
                        <span class="ms-2 fw-bold" style="min-width:40px">{{ $count }}</span>
                        <span class="ms-1 text-muted small">({{ $pct }}%)</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-tags me-2"></i>{{ __('Assessment Sources') }}</h6></div>
            <div class="card-body">
                @php
                    $sourceColors = [
                        'manual' => 'secondary', 'manual_entry' => 'dark', 'bulk' => 'primary',
                        'auto' => 'success', 'api' => 'info',
                    ];
                    $sourceIcons = [
                        'manual' => 'fa-camera', 'manual_entry' => 'fa-clipboard-check', 'bulk' => 'fa-layer-group',
                        'auto' => 'fa-magic', 'api' => 'fa-plug',
                    ];
                @endphp
                @if (empty($sourceBreakdown))
                    <p class="text-muted text-center py-3">{{ __('No assessments yet.') }}</p>
                @else
                    @foreach ($sourceBreakdown as $src)
                        @php
                            $srcPct = round(($src->count / $totalAssessments) * 100);
                            $srcColor = $sourceColors[$src->source] ?? 'secondary';
                            $srcIcon = $sourceIcons[$src->source] ?? 'fa-question';
                        @endphp
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-{{ $srcColor }} me-2" style="min-width:120px">
                                <i class="fas {{ $srcIcon }} me-1"></i>{{ ucfirst(str_replace('_', ' ', $src->source)) }}
                            </span>
                            <div class="progress flex-grow-1" style="height:12px">
                                <div class="progress-bar bg-{{ $srcColor }}" style="width:{{ $srcPct }}%"></div>
                            </div>
                            <span class="ms-2 fw-bold" style="min-width:40px">{{ $src->count }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Top Damages + Monthly Trend -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-bug me-2"></i>{{ __('Top Damage Types') }}</h6></div>
            <div class="card-body">
                @if (empty($topDamages))
                    <p class="text-muted text-center py-3">{{ __('No damage data yet.') }}</p>
                @else
                    @php
                        $maxDamage = 1;
                        foreach ($topDamages as $_td) { $maxDamage = max($maxDamage, $_td->count); }
                        $damageColors = [
                            'tear' => '#dc3545', 'stain' => '#fd7e14', 'foxing' => '#ffc107', 'fading' => '#6c757d',
                            'water_damage' => '#0dcaf0', 'mold' => '#198754', 'pest_damage' => '#6f42c1',
                            'abrasion' => '#adb5bd', 'brittleness' => '#495057', 'loss' => '#212529',
                            'discoloration' => '#e0a800', 'warping' => '#20c997', 'cracking' => '#d63384',
                            'delamination' => '#0d6efd', 'corrosion' => '#795548',
                        ];
                    @endphp
                    @foreach ($topDamages as $dmg)
                        @php
                            $dmgPct = round(($dmg->count / $maxDamage) * 100);
                            $dmgColor = $damageColors[$dmg->damage_type] ?? '#6c757d';
                        @endphp
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge me-2" style="min-width:110px;background:{{ $dmgColor }}">{{ ucfirst(str_replace('_', ' ', $dmg->damage_type)) }}</span>
                            <div class="progress flex-grow-1" style="height:10px">
                                <div class="progress-bar" style="width:{{ $dmgPct }}%;background:{{ $dmgColor }}"></div>
                            </div>
                            <span class="ms-2 small fw-bold">{{ $dmg->count }}</span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>{{ __('Monthly Trend (12 months)') }}</h6></div>
            <div class="card-body">
                @if (empty($monthlyTrend))
                    <p class="text-muted text-center py-3">{{ __('No trend data yet.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr><th>{{ __('Month') }}</th><th class="text-center">{{ __('Assessments') }}</th><th class="text-center">{{ __('Avg Score') }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($monthlyTrend as $m)
                                    <tr>
                                        <td>{{ $m->month }}</td>
                                        <td class="text-center"><span class="badge bg-success">{{ $m->total }}</span></td>
                                        <td class="text-center">
                                            @php
                                                $ms = round($m->avg_score ?? 0, 1);
                                                $mc = $ms >= 80 ? 'success' : ($ms >= 60 ? 'info' : ($ms >= 40 ? 'warning' : 'danger'));
                                            @endphp
                                            <span class="fw-bold text-{{ $mc }}">{{ $ms }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Recent Assessments -->
<div class="card">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Recent Assessments') }}</h6>
        <a href="{{ route('admin.ai.condition.browse') }}" class="btn btn-sm btn-outline-success">{{ __('View All') }}</a>
    </div>
    <div class="card-body p-0">
        @if (empty($recentAssessments))
            <div class="p-3 text-center text-muted">{{ __('No assessments yet.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Object') }}</th>
                            <th class="text-center">{{ __('Score') }}</th>
                            <th class="text-center">{{ __('Grade') }}</th>
                            <th class="text-center">{{ __('Damages') }}</th>
                            <th>{{ __('Source') }}</th>
                            <th class="text-center">{{ __('Confirmed') }}</th>
                            <th>{{ __('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentAssessments as $a)
                            @php
                                // Score display
                                $score = $a->overall_score;
                                if ($score === null) {
                                    $scoreHtml = '<span class="text-muted">--</span>';
                                } else {
                                    $sc = $score >= 80 ? 'success' : ($score >= 60 ? 'info' : ($score >= 40 ? 'warning' : 'danger'));
                                    $scoreHtml = '<span class="fw-bold text-' . $sc . '">' . number_format($score, 1) . '</span>';
                                }
                                // Grade badge
                                $gradeMap = ['excellent' => ['success','fa-check-circle'], 'good' => ['info','fa-thumbs-up'], 'fair' => ['warning','fa-exclamation-triangle'], 'poor' => ['danger','fa-times-circle'], 'critical' => ['dark','fa-skull-crossbones']];
                                if (!$a->condition_grade) {
                                    $gradeHtml = '<span class="badge bg-secondary">N/A</span>';
                                } else {
                                    $g = $gradeMap[$a->condition_grade] ?? ['secondary','fa-question-circle'];
                                    $gradeHtml = '<span class="badge bg-' . $g[0] . '"><i class="fas ' . $g[1] . ' me-1"></i>' . ucfirst($a->condition_grade) . '</span>';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.ai.condition.view', ['id' => $a->id]) }}">
                                        {{ $a->object_title ?? ('Assessment #' . $a->id) }}
                                    </a>
                                </td>
                                <td class="text-center">{!! $scoreHtml !!}</td>
                                <td class="text-center">{!! $gradeHtml !!}</td>
                                <td class="text-center"><span class="badge bg-secondary">{{ $a->damage_count ?? 0 }}</span></td>
                                <td><span class="small">{{ ucfirst(str_replace('_', ' ', $a->source ?? 'manual')) }}</span></td>
                                <td class="text-center">
                                    @if ($a->is_confirmed)
                                        <i class="fas fa-check-circle text-success"></i>
                                    @else
                                        <i class="fas fa-clock text-warning"></i>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ date('d M Y', strtotime($a->created_at)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@endsection
