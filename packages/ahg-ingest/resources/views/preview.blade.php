{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Preview & Approve')

@section('content')
@php
    $session = $session ?? null;
    $tree = $tree ?? [];
    $rowCount = $rowCount ?? 0;
    $doCount = $doCount ?? 0;

    if (!function_exists('ahg_ingest_render_tree')) {
        function ahg_ingest_render_tree(array $nodes, int $depth = 0): string
        {
            $html = '<ul class="list-unstyled ' . ($depth > 0 ? 'ms-3 tree-children' : '') . '"' .
                    ($depth > 0 ? ' style="display:block;"' : '') . '>';
            foreach ($nodes as $node) {
                $cls = 'text-success';
                if (!empty($node['is_excluded'])) {
                    $cls = 'text-decoration-line-through text-danger';
                } elseif (empty($node['is_valid'])) {
                    $cls = 'text-warning';
                }
                $hasChildren = !empty($node['children']);
                $html .= '<li class="mb-1">';
                $html .= '<div class="d-flex align-items-center tree-node" data-row="' . (int) ($node['row_number'] ?? 0) . '" style="cursor:pointer;">';
                if ($hasChildren) {
                    $html .= '<i class="fas fa-caret-down me-1 tree-toggle"></i>';
                } else {
                    $html .= '<i class="fas fa-file me-1 text-muted" style="width:14px"></i>';
                }
                $html .= '<span class="' . $cls . '">';
                $html .= htmlspecialchars((string) ($node['title'] ?? ''));
                $html .= '</span>';
                if (!empty($node['level'])) {
                    $html .= ' <small class="badge bg-secondary ms-1">' . htmlspecialchars((string) $node['level']) . '</small>';
                }
                if (!empty($node['has_do'])) {
                    $html .= ' <i class="fas fa-paperclip text-info ms-1" title="{{ __('Has digital object') }}"></i>';
                }
                $html .= '</div>';
                if ($hasChildren) {
                    $html .= ahg_ingest_render_tree($node['children'], $depth + 1);
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
            return $html;
        }
    }
@endphp

<h1>{{ __('Preview &amp; Approve') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('ingest.index') }}">Ingestion Manager</a></li>
        <li class="breadcrumb-item">{{ $session->title ?? ('Session #' . ($session->id ?? '')) }}</li>
        <li class="breadcrumb-item active" aria-current="page">Preview</li>
    </ol>
</nav>

{{-- Wizard Progress --}}
<div class="mb-4">
    <div class="d-flex justify-content-between text-center">
        <div class="flex-fill"><span class="badge bg-success rounded-pill">1</span><br><small class="text-muted">{{ __('Configure') }}</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">2</span><br><small class="text-muted">{{ __('Upload') }}</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">3</span><br><small class="text-muted">{{ __('Map') }}</small></div>
        <div class="flex-fill"><span class="badge bg-success rounded-pill">4</span><br><small class="text-muted">{{ __('Validate') }}</small></div>
        <div class="flex-fill"><span class="badge bg-primary rounded-pill">5</span><br><small class="fw-bold">{{ __('Preview') }}</small></div>
        <div class="flex-fill"><span class="badge bg-secondary rounded-pill">6</span><br><small class="text-muted">{{ __('Commit') }}</small></div>
    </div>
    <div class="progress mt-2" style="height: 4px;">
        <div class="progress-bar" style="width: 75%"></div>
    </div>
</div>

{{-- Summary --}}
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ $rowCount }}</h3>
                <small class="text-muted">{{ __('Records to create') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ $doCount }}</h3>
                <small class="text-muted">{{ __('Digital objects') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="mb-0">{{ ucfirst($session->sector ?? '') }}</h3>
                <small class="text-muted">{{ strtoupper($session->standard ?? '') }}</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    {{-- Tree View --}}
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0"><i class="fas fa-sitemap me-2"></i>Hierarchy Preview</h5>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-expand-all">
                    <i class="fas fa-expand-alt me-1"></i>{{ __('Expand All') }}
                </button>
            </div>
            <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                @if(!empty($tree))
                    {!! ahg_ingest_render_tree($tree) !!}
                @else
                    <p class="text-muted">No hierarchy to display (flat import)</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Detail Panel --}}
    <div class="col-md-5">
        <div class="card mb-4" id="detail-panel">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Record Details</h5>
            </div>
            <div class="card-body" id="detail-content">
                <p class="text-muted">Click a record in the tree to view details</p>
            </div>
        </div>

        @if(!empty($session->output_generate_sip) || !empty($session->output_generate_dip))
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Package Estimates</h5>
            </div>
            <div class="card-body">
                @if(!empty($session->output_generate_sip))
                    <div class="d-flex justify-content-between mb-2">
                        <span>{{ __('SIP Package') }}</span>
                        <span class="badge bg-secondary">{{ $rowCount }} objects</span>
                    </div>
                @endif
                @if(!empty($session->output_generate_dip))
                    <div class="d-flex justify-content-between">
                        <span>{{ __('DIP Package') }}</span>
                        <span class="badge bg-secondary">{{ $rowCount }} objects</span>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

<div class="d-flex justify-content-between">
    <form method="post" action="{{ route('ingest.preview', ['id' => $session->id ?? 0]) }}">
        @csrf
        <input type="hidden" name="form_action" value="back">
        <button type="submit" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Validation') }}
        </button>
    </form>
    <div>
        <a href="{{ route('ingest.index') }}"
           class="btn btn-outline-danger me-2"
           onclick="return confirm('Cancel this ingest?')">
            <i class="fas fa-times me-1"></i>{{ __('Cancel') }}
        </a>
        <form method="post" action="{{ route('ingest.preview', ['id' => $session->id ?? 0]) }}" class="d-inline">
            @csrf
            <input type="hidden" name="form_action" value="approve">
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('This will create records in Heratio. Proceed?')">
                <i class="fas fa-check me-1"></i>Approve &amp; Commit
                ({{ $rowCount }} records)
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tree-toggle').forEach(function(icon) {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            var li = this.closest('li');
            var children = li.querySelector('.tree-children');
            if (children) {
                var hidden = children.style.display === 'none';
                children.style.display = hidden ? 'block' : 'none';
                this.classList.toggle('fa-caret-down', hidden);
                this.classList.toggle('fa-caret-right', !hidden);
            }
        });
    });

    var expandBtn = document.getElementById('btn-expand-all');
    if (expandBtn) {
        expandBtn.addEventListener('click', function() {
            document.querySelectorAll('.tree-children').forEach(function(el) {
                el.style.display = 'block';
            });
            document.querySelectorAll('.tree-toggle').forEach(function(el) {
                el.classList.remove('fa-caret-right');
                el.classList.add('fa-caret-down');
            });
        });
    }
});
</script>
@endsection
