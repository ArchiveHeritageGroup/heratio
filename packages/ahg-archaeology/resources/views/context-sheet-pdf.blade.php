{{-- Printable context recording sheet (dompdf-safe: self-contained, inline CSS,
     no external assets, DejaVu Sans) - #1428 Phase 4b --}}
@php
  $fmtEl = fn ($v) => $v !== null ? number_format((float) $v, 3).' m' : '-';
  // Group relationships by type for a tidy sheet, ordered as REL_TYPES.
  $byType = collect($relationships)->groupBy('relationship_type');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 22mm 18mm; }
  * { font-family: "DejaVu Sans", sans-serif; }
  body { color: #1a1a1a; font-size: 10.5px; line-height: 1.4; }
  h1 { font-size: 17px; margin: 0 0 2px; }
  h2 { font-size: 11.5px; margin: 14px 0 5px; padding-bottom: 3px; border-bottom: 1.5px solid #10373E; color: #10373E; text-transform: uppercase; letter-spacing: .04em; }
  .sub { color: #555; font-size: 10px; margin-bottom: 2px; }
  .head { border-bottom: 2px solid #10373E; padding-bottom: 8px; margin-bottom: 4px; }
  .badge { display: inline-block; background: #10373E; color: #fff; font-size: 9px; padding: 1px 7px; border-radius: 3px; vertical-align: middle; }
  table { width: 100%; border-collapse: collapse; }
  table.kv td { padding: 3px 6px; vertical-align: top; border-bottom: .5px solid #e2e2e2; }
  table.kv td.k { width: 32%; color: #555; font-weight: bold; }
  table.grid th, table.grid td { border: .75px solid #b9c4c6; padding: 4px 6px; text-align: left; }
  table.grid th { background: #10373E; color: #fff; font-weight: bold; font-size: 9.5px; }
  .prose { margin: 3px 0 0; text-align: justify; white-space: pre-line; }
  .muted { color: #888; }
  .foot { margin-top: 18px; padding-top: 6px; border-top: .5px solid #ccc; color: #888; font-size: 8.5px; }
  ul.rels { margin: 0; padding-left: 16px; }
  ul.rels li { margin-bottom: 2px; }
</style>
</head>
<body>

  <div class="head">
    <h1>Context {{ $context->context_number }}
      @if($context->type_name)<span class="badge">{{ $context->type_name }}</span>@endif
    </h1>
    <div class="sub">{{ $context->site->title ?? 'Site' }} &middot; {{ $context->site->site_number ?? '' }}</div>
  </div>

  <h2>Context sheet</h2>
  <table class="kv">
    <tr><td class="k">Type</td><td>{{ $context->type_name ?: '-' }}</td>
        <td class="k">Phase</td><td>{{ $context->phase_name ?: '-' }}</td></tr>
    <tr><td class="k">Top elevation</td><td>{{ $fmtEl($context->top_elevation_m) }}</td>
        <td class="k">Bottom elevation</td><td>{{ $fmtEl($context->bottom_elevation_m) }}</td></tr>
    <tr><td class="k">Excavation ref.</td><td>{{ $context->excavation_reference ?: '-' }}</td>
        <td class="k">Excavator</td><td>{{ $context->excavator ?: '-' }}</td></tr>
    <tr><td class="k">Excavated</td><td>{{ $context->excavation_date ?: '-' }}</td>
        <td class="k">Date range</td><td>{{ ($context->date_earliest ?: '?') }} - {{ ($context->date_latest ?: '?') }}</td></tr>
    @if($context->dating_note)
      <tr><td class="k">Dating note</td><td colspan="3">{{ $context->dating_note }}</td></tr>
    @endif
  </table>

  @if($context->description)
    <h2>Description</h2>
    <div class="prose">{{ $context->description }}</div>
  @endif
  @if($context->interpretation)
    <h2>Interpretation</h2>
    <div class="prose">{{ $context->interpretation }}</div>
  @endif

  <h2>Stratigraphic relationships</h2>
  @if(collect($relationships)->isEmpty())
    <p class="muted">No relationships recorded.</p>
  @else
    <ul class="rels">
      @foreach($relTypes as $code => $meta)
        @foreach(($byType[$code] ?? []) as $r)
          <li>This context <strong>{{ $meta['label'] }}</strong> context {{ $r->related_number }}@if($r->note) <span class="muted">- {{ $r->note }}</span>@endif</li>
        @endforeach
      @endforeach
    </ul>
  @endif

  <h2>Finds in this context ({{ collect($context->finds)->count() }})</h2>
  @if(collect($context->finds)->isEmpty())
    <p class="muted">No finds catalogued to this context.</p>
  @else
    <table class="grid">
      <thead><tr><th style="width:30%">Accession no.</th><th>Title</th></tr></thead>
      <tbody>
        @foreach($context->finds as $f)
          <tr><td>{{ $f->accession_number }}</td><td>{{ $f->title ?: 'Untitled' }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <div class="foot">
    {{ $context->site->title ?? 'Site' }} &middot; Context {{ $context->context_number }} &middot;
    Generated {{ $generatedAt }}
  </div>

</body>
</html>
