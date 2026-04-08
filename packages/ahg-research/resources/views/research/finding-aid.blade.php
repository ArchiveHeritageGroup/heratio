<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Finding Aid: {{ e($collection->name) }}</title>
    <style>
        body { font-family: 'Georgia', 'Times New Roman', serif; margin: 40px; color: #333; line-height: 1.6; }
        h1 { font-size: 1.8rem; border-bottom: 3px double #333; padding-bottom: 10px; margin-bottom: 5px; }
        h2 { font-size: 1.3rem; margin-top: 30px; border-bottom: 1px solid #999; padding-bottom: 5px; color: #555; }
        h3 { font-size: 1.1rem; margin-top: 20px; color: #444; }
        .meta { color: #666; font-size: 0.9rem; margin-bottom: 20px; }
        .item { margin-bottom: 25px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; page-break-inside: avoid; }
        .item-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
        .item-title { font-weight: bold; font-size: 1.05rem; }
        .item-level { color: #888; font-size: 0.85rem; }
        .field { margin-bottom: 6px; }
        .field-label { font-weight: bold; font-size: 0.85rem; color: #555; }
        .field-value { font-size: 0.9rem; }
        .researcher-note { background: #fffde7; border-left: 3px solid #ffc107; padding: 8px 12px; margin-top: 8px; font-style: italic; font-size: 0.85rem; }
        .toc { margin: 20px 0; }
        .toc ol { padding-left: 20px; }
        .toc li { margin-bottom: 4px; }
        .footer { margin-top: 40px; padding-top: 15px; border-top: 1px solid #ccc; font-size: 0.8rem; color: #888; text-align: center; }
        .no-print { margin-bottom: 20px; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 20px; }
            .item { border-color: #ccc; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background:#f8f9fa; padding:15px; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <a href="{{ route('research.viewCollection') }}?id={{ $collection->id }}" style="text-decoration:none;">&larr; Back to Evidence Set</a>
        </div>
        <div>
            <button onclick="window.print()" style="padding:8px 16px; background:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:0.9rem;">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
        </div>
    </div>

    <h1>Finding Aid</h1>
    <div class="meta">
        <strong>{{ e($collection->name) }}</strong><br>
        @if($collection->description)
            {{ e($collection->description) }}<br>
        @endif
        Prepared by: {{ e(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) }}<br>
        Date: {{ date('F j, Y') }}<br>
        Items: {{ count($items) }}
    </div>

    @if(count($items) > 3)
    <h2>Table of Contents</h2>
    <div class="toc">
        <ol>
            @foreach($items as $i => $item)
                <li><a href="#item-{{ $item->id }}">{{ e($item->title ?: '[Untitled]') }}{{ $item->identifier ? ' (' . e($item->identifier) . ')' : '' }}</a></li>
            @endforeach
        </ol>
    </div>
    @endif

    <h2>Archival Descriptions</h2>

    @foreach($items as $item)
    <div class="item" id="item-{{ $item->id }}">
        <div class="item-header">
            <span class="item-title">{{ e($item->title ?: '[Untitled]') }}</span>
            <span class="item-level">{{ e($item->level_of_description ?? '') }}</span>
        </div>

        @if($item->identifier)
        <div class="field"><span class="field-label">Identifier:</span> <span class="field-value">{{ e($item->identifier) }}</span></div>
        @endif

        @if($item->repository_name)
        <div class="field"><span class="field-label">Repository:</span> <span class="field-value">{{ e($item->repository_name) }}</span></div>
        @endif

        @if($item->scope_and_content)
        <div class="field"><span class="field-label">Scope and Content:</span><br><span class="field-value">{{ e($item->scope_and_content) }}</span></div>
        @endif

        @if($item->extent_and_medium)
        <div class="field"><span class="field-label">Extent and Medium:</span><br><span class="field-value">{{ e($item->extent_and_medium) }}</span></div>
        @endif

        @if($item->archival_history)
        <div class="field"><span class="field-label">Archival History:</span><br><span class="field-value">{{ e($item->archival_history) }}</span></div>
        @endif

        @if($item->arrangement)
        <div class="field"><span class="field-label">Arrangement:</span><br><span class="field-value">{{ e($item->arrangement) }}</span></div>
        @endif

        @if($item->access_conditions)
        <div class="field"><span class="field-label">Access Conditions:</span><br><span class="field-value">{{ e($item->access_conditions) }}</span></div>
        @endif

        @if($item->reproduction_conditions)
        <div class="field"><span class="field-label">Reproduction Conditions:</span><br><span class="field-value">{{ e($item->reproduction_conditions) }}</span></div>
        @endif

        @if($item->physical_characteristics)
        <div class="field"><span class="field-label">Physical Characteristics:</span><br><span class="field-value">{{ e($item->physical_characteristics) }}</span></div>
        @endif

        @if($item->researcher_notes)
        <div class="researcher-note"><strong>Research Notes:</strong> {{ e($item->researcher_notes) }}</div>
        @endif
    </div>
    @endforeach

    <div class="footer">
        Generated by Heratio &mdash; {{ date('F j, Y \a\t H:i') }}
    </div>
</body>
</html>
