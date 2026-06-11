{{--
  Open heritage graph "at a glance" dashboard - the human-facing view of
  /data/stats (north-star #1204, the world heritage graph / open memory
  protocol). Big numbers plus plain CSS bars (no charting library), driven
  entirely by the cheap aggregates StatsController::compute() returns. Read-only;
  resilient to an empty corpus (every figure defaults to 0). Self-contained
  inline CSS so it renders without the app theme bundle.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@php
    /** @var array<string,mixed> $s */
    /** @var array<string,string> $links */
    $appName = config('app.name', 'Heratio');
    $fmt = static fn ($n) => number_format((int) ($n ?? 0));

    // Helper: a percentage of published records (for the coverage bars).
    $published = (int) ($s['published_records'] ?? 0);
    $pct = static function ($part) use ($published) {
        if ($published <= 0) {
            return 0;
        }
        return min(100, (int) round(((int) $part / $published) * 100));
    };

    // Largest level count, for scaling the level bars.
    $levels = $s['records_by_level'] ?? [];
    $maxLevel = 0;
    foreach ($levels as $lv) {
        $maxLevel = max($maxLevel, (int) ($lv['count'] ?? 0));
    }

    $actors = $s['actors_by_kind'] ?? [];
    $terms = $s['terms_by_kind'] ?? [];
    $maxActor = max(1, (int) ($actors['person'] ?? 0), (int) ($actors['corporate_body'] ?? 0), (int) ($actors['family'] ?? 0), (int) ($actors['other'] ?? 0));
    $maxTerm = max(1, (int) ($terms['subject'] ?? 0), (int) ($terms['place'] ?? 0), (int) ($terms['genre'] ?? 0));

    $isEmpty = $published === 0
        && (int) ($s['actors_total'] ?? 0) === 0
        && (int) ($s['terms_total'] ?? 0) === 0;
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $appName }} - the open heritage graph at a glance</title>
<style>
  :root { --ink:#1a1f29; --muted:#5a6472; --line:#e3e7ed; --bar:#2c6e8f; --bar2:#7aa9bf; --card:#f7f9fb; --accent:#1d3c5a; }
  * { box-sizing:border-box; }
  body { font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; color:var(--ink); margin:0; background:#fff; }
  .wrap { max-width:64rem; margin:0 auto; padding:2rem 1.1rem 3.5rem; }
  h1 { font-size:1.55rem; margin:0 0 .3rem; }
  .lede { color:var(--muted); margin:0 0 1.6rem; max-width:46rem; line-height:1.5; }
  h2 { font-size:1.05rem; margin:2rem 0 .8rem; border-bottom:1px solid var(--line); padding-bottom:.35rem; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(8.5rem,1fr)); gap:.75rem; }
  .stat { background:var(--card); border:1px solid var(--line); border-radius:9px; padding:.9rem 1rem; }
  .stat .num { font-size:1.85rem; font-weight:700; line-height:1.1; color:var(--accent); }
  .stat .lab { font-size:.78rem; color:var(--muted); margin-top:.25rem; text-transform:uppercase; letter-spacing:.03em; }
  .bars { display:flex; flex-direction:column; gap:.55rem; margin:.4rem 0 0; }
  .row { display:grid; grid-template-columns:11rem 1fr 4.5rem; align-items:center; gap:.6rem; font-size:.9rem; }
  .row .name { color:var(--ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .track { background:#eef2f6; border-radius:5px; height:1.05rem; overflow:hidden; }
  .fill { background:var(--bar); height:100%; border-radius:5px; min-width:2px; }
  .fill.alt { background:var(--bar2); }
  .row .val { text-align:right; color:var(--muted); font-variant-numeric:tabular-nums; }
  .cov { display:grid; grid-template-columns:1fr; gap:.6rem; }
  .links { display:flex; flex-wrap:wrap; gap:.55rem; margin-top:.6rem; }
  .links a { display:inline-block; border:1px solid var(--line); border-radius:7px; padding:.5rem .85rem; color:var(--accent); text-decoration:none; font-size:.9rem; background:#fff; }
  .links a:hover { background:var(--card); }
  .meta { color:var(--muted); font-size:.82rem; margin-top:2rem; border-top:1px solid var(--line); padding-top:.9rem; line-height:1.5; }
  .meta a { color:var(--accent); }
  .empty { background:var(--card); border:1px dashed var(--line); border-radius:9px; padding:1.4rem; color:var(--muted); }
  code { background:#eef2f6; padding:.08rem .3rem; border-radius:4px; font-size:.85em; }
  @media (max-width:34rem){ .row { grid-template-columns:6.5rem 1fr 3.5rem; } }
</style>
</head>
<body>
<div class="wrap">
  <h1>The open heritage graph, at a glance</h1>
  <p class="lede">A live snapshot of the size and shape of {{ $appName }}'s published open-data graph. Every figure
  is an aggregate count over published records only, served as open data under CC-BY-4.0. This is the human view of
  <code>/data/stats</code>; a machine-readable version is at <a href="{{ $links['json'] ?? url('/data/stats.json') }}">/data/stats.json</a>.</p>

  @if($isEmpty)
    <div class="empty">
      The published open graph is currently empty. Once records are described and published they will appear here,
      counted by level, with their people, places, subjects and the connections between them.
    </div>
  @endif

  <h2>Headline figures</h2>
  <div class="grid">
    <div class="stat"><div class="num">{{ $fmt($s['published_records'] ?? 0) }}</div><div class="lab">Published records</div></div>
    <div class="stat"><div class="num">{{ $fmt($s['actors_total'] ?? 0) }}</div><div class="lab">People &amp; organisations</div></div>
    <div class="stat"><div class="num">{{ $fmt($s['terms_total'] ?? 0) }}</div><div class="lab">Subjects, places &amp; genres</div></div>
    <div class="stat"><div class="num">{{ $fmt($s['relation_edges_total'] ?? 0) }}</div><div class="lab">Relation edges</div></div>
    <div class="stat"><div class="num">{{ $fmt($s['repositories'] ?? 0) }}</div><div class="lab">Holding repositories</div></div>
    <div class="stat"><div class="num">~{{ $fmt($s['triple_estimate'] ?? 0) }}</div><div class="lab">Triples (estimated)</div></div>
  </div>

  @if(!empty($levels))
  <h2>Records by level of description</h2>
  <div class="bars">
    @foreach($levels as $lv)
      @php $w = $maxLevel > 0 ? max(2, (int) round(((int) $lv['count'] / $maxLevel) * 100)) : 0; @endphp
      <div class="row">
        <span class="name" title="{{ $lv['label'] }}">{{ $lv['label'] }}</span>
        <span class="track"><span class="fill" style="width:{{ $w }}%"></span></span>
        <span class="val">{{ $fmt($lv['count']) }}</span>
      </div>
    @endforeach
  </div>
  @endif

  <h2>People &amp; organisations</h2>
  <div class="bars">
    @php
      $actorRows = [
        ['Persons', (int) ($actors['person'] ?? 0)],
        ['Corporate bodies', (int) ($actors['corporate_body'] ?? 0)],
        ['Families', (int) ($actors['family'] ?? 0)],
      ];
      if ((int) ($actors['other'] ?? 0) > 0) { $actorRows[] = ['Other', (int) $actors['other']]; }
    @endphp
    @foreach($actorRows as $ar)
      @php $w = $maxActor > 0 ? max(2, (int) round(($ar[1] / $maxActor) * 100)) : 0; @endphp
      <div class="row">
        <span class="name">{{ $ar[0] }}</span>
        <span class="track"><span class="fill" style="width:{{ $w }}%"></span></span>
        <span class="val">{{ $fmt($ar[1]) }}</span>
      </div>
    @endforeach
  </div>

  <h2>Subjects, places &amp; genres</h2>
  <div class="bars">
    @php
      $termRows = [
        ['Subjects', (int) ($terms['subject'] ?? 0)],
        ['Places', (int) ($terms['place'] ?? 0)],
        ['Genres', (int) ($terms['genre'] ?? 0)],
      ];
    @endphp
    @foreach($termRows as $tr)
      @php $w = $maxTerm > 0 ? max(2, (int) round(($tr[1] / $maxTerm) * 100)) : 0; @endphp
      <div class="row">
        <span class="name">{{ $tr[0] }}</span>
        <span class="track"><span class="fill alt" style="width:{{ $w }}%"></span></span>
        <span class="val">{{ $fmt($tr[1]) }}</span>
      </div>
    @endforeach
  </div>

  <h2>Connections</h2>
  <div class="grid">
    <div class="stat"><div class="num">{{ $fmt($s['relation_edges_total'] ?? 0) }}</div><div class="lab">Total relation edges</div></div>
    <div class="stat"><div class="num">{{ $fmt($s['relation_record_to_record'] ?? 0) }}</div><div class="lab">Record-to-record cross-links</div></div>
    <div class="stat"><div class="num">{{ $fmt($s['records_with_uri'] ?? 0) }}</div><div class="lab">Records with a linked-data URI</div></div>
  </div>

  <h2>Descriptive coverage</h2>
  <div class="bars cov">
    @php
      $covRows = [
        ['Records with dates', (int) ($s['records_with_dates'] ?? 0)],
        ['Records with a creator', (int) ($s['records_with_creator'] ?? 0)],
        ['Records with a subject', (int) ($s['records_with_subject'] ?? 0)],
        ['Records with a linked-data URI', (int) ($s['records_with_uri'] ?? 0)],
      ];
    @endphp
    @foreach($covRows as $cr)
      @php $p = $pct($cr[1]); @endphp
      <div class="row">
        <span class="name">{{ $cr[0] }}</span>
        <span class="track"><span class="fill" style="width:{{ $p }}%"></span></span>
        <span class="val">{{ $p }}%</span>
      </div>
    @endforeach
  </div>

  <h2>Explore the graph</h2>
  <div class="links">
    @if(!empty($links['graphExplorer']))<a href="{{ $links['graphExplorer'] }}">Graph explorer</a>@endif
    @if(!empty($links['catalog']))<a href="{{ $links['catalog'] }}">Data catalogue (DCAT)</a>@endif
    @if(!empty($links['protocol']))<a href="{{ $links['protocol'] }}">Open Memory Protocol</a>@endif
    @if(!empty($links['void']))<a href="{{ $links['void'] }}">VoID description</a>@endif
    <a href="{{ $links['json'] ?? url('/data/stats.json') }}">This page as JSON</a>
  </div>

  <p class="meta">
    Figures are aggregate counts over published records only and refresh on every request. The triple count is an
    order-of-magnitude estimate for the VoID dataset description, not an exact statement count. Open data, licensed
    <a href="https://creativecommons.org/licenses/by/4.0/">CC-BY-4.0</a>. Part of the
    {{ $appName }} open memory protocol (#1204).
  </p>
</div>
</body>
</html>
