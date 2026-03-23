{{--
  Print Preview – print.blade.php
  Migrated from AtoM printSuccess.php (ahgDisplayPlugin)
  Matches AtoM exactly: same styling, same table columns, same layout
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>GLAM Browse - Print Preview</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 20px; }
    h1 { font-size: 18px; border-bottom: 2px solid var(--ahg-primary, #1d6a52); padding-bottom: 10px; color: var(--ahg-primary, #1d6a52); }
    .meta { color: #666; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
    th { background-color: var(--ahg-primary, #1d6a52); color: white; font-weight: bold; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .type-archive { color: #198754; }
    .type-museum { color: #ffc107; }
    .type-gallery { color: #0dcaf0; }
    .type-library { color: #0d6efd; }
    .type-dam { color: #dc3545; }
    .scope { font-size: 11px; color: #666; max-width: 300px; }
    @media print {
      body { margin: 0; }
      h1 { page-break-after: avoid; }
      tr { page-break-inside: avoid; }
      .no-print { display: none; }
    }
    .print-btn { background: var(--ahg-primary, #1d6a52); color: white; border: none; padding: 10px 20px; cursor: pointer; margin-right: 10px; margin-bottom: 20px; }
    .print-btn:hover { opacity: 0.9; }
  </style>
</head>
<body>
  <div class="no-print">
    <button class="print-btn" onclick="window.print()">Print this page</button>
    <button class="print-btn" onclick="window.close()">Close</button>
  </div>

  <h1>
    @if(isset($parent) && $parent)
      {{ e($parent->title ?? '') }} - Contents
    @else
      GLAM Browse Results
    @endif
  </h1>

  <div class="meta">
    <strong>Total:</strong> {{ $total ?? 0 }} records |
    <strong>Generated:</strong> {{ now()->format('Y-m-d H:i:s') }}
    @if(!empty($typeFilter))
      | <strong>Type:</strong> {{ ucfirst($typeFilter) }}
    @endif
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:120px">Identifier</th>
        <th>Title</th>
        <th style="width:100px">Level</th>
        <th style="width:80px">Type</th>
        <th style="width:250px">Scope and Content</th>
      </tr>
    </thead>
    <tbody>
      @if(!empty($objects) && count($objects))
        @foreach($objects as $obj)
          <tr>
            <td>{{ e($obj->identifier ?? '-') }}</td>
            <td><strong>{{ e($obj->title ?? '[Untitled]') }}</strong></td>
            <td>{{ e($obj->level_name ?? $obj->level ?? $obj->level_of_description ?? '-') }}</td>
            <td class="type-{{ $obj->object_type ?? $obj->type ?? $obj->collection_type ?? '' }}">{{ ucfirst($obj->object_type ?? $obj->type ?? $obj->collection_type ?? '-') }}</td>
            <td class="scope">{{ e(mb_substr($obj->scope_and_content ?? $obj->scopeAndContent ?? '', 0, 200)) }}@if(strlen($obj->scope_and_content ?? $obj->scopeAndContent ?? '') > 200)...@endif</td>
          </tr>
        @endforeach
      @else
        <tr>
          <td colspan="5" style="text-align:center;color:#999;padding:20px;">No records to display.</td>
        </tr>
      @endif
    </tbody>
  </table>

  <div class="meta" style="margin-top: 20px;">
    <em>Printed from Heratio GLAM Display System</em>
  </div>
</body>
</html>
