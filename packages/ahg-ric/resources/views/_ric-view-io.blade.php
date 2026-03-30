{{-- RiC View: Information Object — relationship-centered layout --}}
@php
  $culture = app()->getLocale();

  // RiC entity type based on level of description
  $ricTypeMap = [
    'Fonds' => 'RecordSet', 'Sub-fonds' => 'RecordSet', 'Collection' => 'RecordSet',
    'Series' => 'RecordSet', 'Sub-series' => 'RecordSet',
    'File' => 'Record', 'Item' => 'Record', 'Part' => 'RecordPart',
  ];
  $lodLabel = $io->level_of_description ?? '';
  $ricType = $ricTypeMap[$lodLabel] ?? 'RecordResource';

  // Creators via event table
  $creators = \Illuminate\Support\Facades\DB::table('event')
      ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
      ->leftJoin('slug', 'event.actor_id', '=', 'slug.object_id')
      ->where('event.object_id', $io->id)
      ->where('actor_i18n.culture', $culture)
      ->whereNotNull('actor_i18n.authorized_form_of_name')
      ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug', 'event.type_id')
      ->get();

  // Parent
  $parent = null;
  if ($io->parent_id && $io->parent_id > 1) {
      $parent = \Illuminate\Support\Facades\DB::table('information_object')
          ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
          ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
          ->where('information_object.id', $io->parent_id)
          ->where('information_object_i18n.culture', $culture)
          ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
          ->first();
  }

  // Children count
  $childCount = \Illuminate\Support\Facades\DB::table('information_object')
      ->where('parent_id', $io->id)
      ->count();

  // Related records via relation table
  $relatedRecords = \Illuminate\Support\Facades\DB::table('relation')
      ->leftJoin('information_object_i18n as ioi', function ($j) use ($io, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$io->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
            ->where('ioi.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$io->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->leftJoin('term_i18n as ti', function ($j) {
          $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
      })
      ->where(function ($q) use ($io) {
          $q->where('relation.subject_id', $io->id)
            ->orWhere('relation.object_id', $io->id);
      })
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug', 'ti.name as relation_type')
      ->limit(20)
      ->get();

  // Subjects via object_term_relation
  $subjects = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
      ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
      ->where('object_term_relation.object_id', $io->id)
      ->where('term_i18n.culture', $culture)
      ->select('term_i18n.name', 'slug.slug')
      ->get();

  // Places via event/relation
  $places = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
      ->join('term', 'object_term_relation.term_id', '=', 'term.id')
      ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
      ->where('object_term_relation.object_id', $io->id)
      ->where('term.taxonomy_id', 42) {{-- Places taxonomy --}}
      ->where('term_i18n.culture', $culture)
      ->select('term_i18n.name', 'slug.slug')
      ->get();

  // Digital objects (instantiations)
  $digitalObjects = \Illuminate\Support\Facades\DB::table('digital_object')
      ->where('object_id', $io->id)
      ->get();

  // Repository (holder)
  $holder = null;
  if ($io->repository_id) {
      $holder = \Illuminate\Support\Facades\DB::table('actor_i18n')
          ->leftJoin('slug', 'actor_i18n.id', '=', 'slug.object_id')
          ->where('actor_i18n.id', $io->repository_id)
          ->where('actor_i18n.culture', $culture)
          ->select('actor_i18n.authorized_form_of_name as name', 'slug.slug')
          ->first();
  }
@endphp

<div class="ric-view">
  {{-- RiC Entity Header --}}
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-project-diagram me-2"></i>
        <strong>{{ $io->title }}</strong>
      </div>
      <span class="badge bg-light text-success">rico:{{ $ricType }}</span>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6">
          <table class="table table-sm mb-0">
            <tr><th class="text-muted" style="width:40%">RiC Entity Type</th><td><code>rico:{{ $ricType }}</code></td></tr>
            <tr><th class="text-muted">Identifier</th><td>{{ $io->identifier ?? '—' }}</td></tr>
            <tr><th class="text-muted">Level</th><td>{{ $lodLabel ?: '—' }}</td></tr>
            @if($holder)
              <tr><th class="text-muted">hasOrHadHolder</th><td><a href="{{ url('/' . $holder->slug) }}">{{ $holder->name }}</a></td></tr>
            @endif
          </table>
        </div>
        <div class="col-md-6">
          <table class="table table-sm mb-0">
            @if($io->scope_and_content)
              <tr><th class="text-muted" style="width:40%">Scope</th><td>{{ \Illuminate\Support\Str::limit(strip_tags($io->scope_and_content), 200) }}</td></tr>
            @endif
            @if($io->extent_and_medium)
              <tr><th class="text-muted">Extent</th><td>{{ $io->extent_and_medium }}</td></tr>
            @endif
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    {{-- Left: Relationships --}}
    <div class="col-md-8">

      {{-- Parent / Child Relations --}}
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-sitemap me-1"></i> Hierarchy (isPartOf / hasPart)
        </div>
        <div class="card-body">
          @if($parent)
            <div class="mb-2">
              <span class="badge bg-secondary">isPartOf</span>
              <a href="{{ url('/' . $parent->slug) }}">{{ $parent->title ?: '[Untitled]' }}</a>
            </div>
          @endif
          @if($childCount > 0)
            <div>
              <span class="badge bg-info">hasPart</span>
              <span class="text-muted">{{ $childCount }} child {{ $childCount === 1 ? 'record' : 'records' }}</span>
            </div>
          @endif
          @if(!$parent && $childCount === 0)
            <p class="text-muted mb-0">No hierarchical relationships.</p>
          @endif
        </div>
      </div>

      {{-- Creators / Accumulators --}}
      @if($creators->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-user me-1"></i> Creators and Agents (hasOrHadCreator)
        </div>
        <div class="list-group list-group-flush">
          @foreach($creators as $creator)
            <a href="{{ url('/' . $creator->slug) }}" class="list-group-item list-group-item-action">
              <i class="fas fa-user-circle text-danger me-1"></i>
              {{ $creator->name }}
              <span class="badge bg-secondary float-end">Agent</span>
            </a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Related Records --}}
      @if($relatedRecords->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-link me-1"></i> Related Records (isAssociatedWith)
        </div>
        <div class="list-group list-group-flush">
          @foreach($relatedRecords as $rel)
            <a href="{{ url('/' . $rel->slug) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
              <span>{{ $rel->title }}</span>
              @if($rel->relation_type)
                <span class="badge bg-secondary">{{ $rel->relation_type }}</span>
              @endif
            </a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Subjects --}}
      @if($subjects->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-tags me-1"></i> Subjects (hasOrHadSubject)
        </div>
        <div class="card-body">
          @foreach($subjects as $subj)
            <a href="{{ url('/' . $subj->slug) }}" class="badge bg-primary text-decoration-none me-1 mb-1">{{ $subj->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      {{-- Places --}}
      @if($places->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-map-marker-alt me-1"></i> Places (isAssociatedWithPlace)
        </div>
        <div class="card-body">
          @foreach($places as $place)
            <a href="{{ url('/' . $place->slug) }}" class="badge bg-warning text-dark text-decoration-none me-1 mb-1">{{ $place->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

    </div>

    {{-- Right: Instantiations + Provenance --}}
    <div class="col-md-4">

      {{-- Instantiations (digital objects) --}}
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-file-image me-1"></i> Instantiations
        </div>
        <div class="card-body">
          @if($digitalObjects->count())
            <p class="small text-muted mb-2">{{ $digitalObjects->count() }} digital {{ $digitalObjects->count() === 1 ? 'object' : 'objects' }}</p>
            @foreach($digitalObjects->take(5) as $dobj)
              @php
                $dobjUrl = \AhgCore\Services\DigitalObjectService::getUrl($dobj, 'reference');
                $isMaster = $dobj->usage_id == 166; // master
              @endphp
              @if($dobjUrl)
                <div class="mb-2">
                  <img src="{{ $dobjUrl }}" alt="" class="img-fluid rounded" style="max-height:120px;">
                </div>
              @endif
            @endforeach
          @else
            <p class="text-muted small mb-0">No instantiations.</p>
          @endif
        </div>
      </div>

      {{-- Provenance Summary --}}
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-history me-1"></i> Provenance
        </div>
        <div class="card-body" id="ric-provenance-body">
          <div class="text-center py-2">
            <div class="spinner-border spinner-border-sm text-muted"></div>
          </div>
        </div>
      </div>

      {{-- Actions --}}
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-bolt me-1"></i> Actions
        </div>
        <div class="card-body">
          <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2">
            <i class="fas fa-project-diagram me-1"></i>Open in Graph Explorer
          </a>
          <a href="/ric-api/graph-summary/{{ $io->id }}" class="btn btn-sm btn-outline-info w-100 mb-2" target="_blank">
            <i class="fas fa-code me-1"></i>View JSON-LD
          </a>
          <a href="/ric-api/timeline/{{ $io->id }}" class="btn btn-sm btn-outline-secondary w-100" target="_blank">
            <i class="fas fa-clock me-1"></i>View Timeline
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// Load provenance data
fetch('/ric-api/timeline/{{ $io->id }}')
  .then(function(r) { return r.json(); })
  .then(function(data) {
    var body = document.getElementById('ric-provenance-body');
    if (!data.success || !data.events || data.events.length === 0) {
      body.innerHTML = '<p class="text-muted small mb-0">No provenance events.</p>';
      return;
    }
    var html = '<ul class="list-unstyled small mb-0">';
    data.events.forEach(function(evt) {
      var date = evt.date_display || evt.start_date || '';
      var actor = evt.actor || '';
      html += '<li class="mb-1"><i class="fas fa-circle text-success me-1" style="font-size:0.5rem;vertical-align:middle;"></i>';
      if (date) html += '<strong>' + date + '</strong> ';
      if (actor) html += '— ' + actor;
      if (evt.name) html += ' <em>(' + evt.name + ')</em>';
      html += '</li>';
    });
    html += '</ul>';
    body.innerHTML = html;
  })
  .catch(function() {
    document.getElementById('ric-provenance-body').innerHTML = '<p class="text-muted small mb-0">Could not load provenance.</p>';
  });
</script>
