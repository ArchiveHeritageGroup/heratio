{{--
  RiC View: Digital Asset (DAM item) — file embodiment + record-resource context.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@php
  $culture = app()->getLocale();

  // Sibling assets attached to the same parent IO (if any).
  $siblings = collect();
  if (! empty($asset->object_id)) {
      $siblings = \Illuminate\Support\Facades\DB::table('digital_object as d')
          ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
              $j->on('ioi.id', '=', 'd.object_id')->where('ioi.culture', $culture);
          })
          ->leftJoin('slug', 'slug.object_id', '=', 'd.object_id')
          ->where('d.object_id', $asset->object_id)
          ->where('d.id', '!=', $asset->id)
          ->select('d.id', 'd.name', 'slug.slug', 'ioi.title')
          ->limit(20)
          ->get();
  }

  $assetTitle = $asset->title ?? $asset->name ?? '[Untitled asset]';
  $mime       = $asset->mime_type ?? null;
  $byteSize   = $asset->byte_size ?? null;
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-photo-film me-2"></i><strong>{{ $assetTitle }}</strong></div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:Instantiation</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">RiC Role</th><td>Digital embodiment / file carrier</td></tr>
        @if($mime)<tr><th class="text-muted">Media format</th><td><code>{{ $mime }}</code></td></tr>@endif
        @if($byteSize)<tr><th class="text-muted">Byte size</th><td>{{ number_format((int) $byteSize) }} bytes</td></tr>@endif
        @if(! empty($asset->object_id))
          <tr><th class="text-muted">rico:isInstantiationOf</th><td>Information object #{{ $asset->object_id }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  @if($siblings->count())
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-layer-group me-1"></i> Sibling Instantiations</div>
      <div class="list-group list-group-flush">
        @foreach($siblings as $s)
          <a href="{{ $s->slug ? url('/' . $s->slug) : '#' }}" class="list-group-item list-group-item-action">
            {{ $s->title ?? $s->name ?? '[file]' }}
          </a>
        @endforeach
      </div>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-bolt me-1"></i> Actions</div>
    <div class="card-body">
      <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2"><i class="fas fa-project-diagram me-1"></i>Open in Graph Explorer</a>
    </div>
  </div>
</div>
