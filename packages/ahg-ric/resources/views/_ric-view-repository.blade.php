{{-- RiC View: Repository — holder relationships and holdings --}}
@php
  $culture = app()->getLocale();

  // Holdings count
  $holdingsCount = \Illuminate\Support\Facades\DB::table('information_object')
      ->where('repository_id', $repository->id)
      ->count();

  // Top-level holdings (fonds/collections)
  $topHoldings = \Illuminate\Support\Facades\DB::table('information_object')
      ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
      ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
      ->where('information_object.repository_id', $repository->id)
      ->where('information_object.parent_id', 1)
      ->where('information_object_i18n.culture', $culture)
      ->whereNotNull('information_object_i18n.title')
      ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
      ->orderBy('information_object_i18n.title')
      ->limit(20)
      ->get();

  // Related agents (staff, maintainers via relation table)
  $relatedAgents = \Illuminate\Support\Facades\DB::table('relation')
      ->leftJoin('actor_i18n as ai', function ($j) use ($repository, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$repository->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ai.id')
            ->where('ai.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$repository->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->leftJoin('term_i18n as ti', function ($j) {
          $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
      })
      ->where(function ($q) use ($repository) {
          $q->where('relation.subject_id', $repository->id)
            ->orWhere('relation.object_id', $repository->id);
      })
      ->whereNotNull('ai.authorized_form_of_name')
      ->select('ai.authorized_form_of_name as name', 'slug.slug', 'ti.name as relation_type')
      ->limit(20)
      ->get();
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-building me-2"></i><strong>{{ $repository->authorized_form_of_name }}</strong></div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:CorporateBody (Holder)</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">RiC Role</th><td>Archival institution / Holder</td></tr>
        <tr><th class="text-muted">Total holdings</th><td>{{ number_format($holdingsCount) }} descriptions</td></tr>
        @if($repository->geo_cultural_context ?? null)
          <tr><th class="text-muted">Context</th><td>{{ \Illuminate\Support\Str::limit(strip_tags($repository->geo_cultural_context), 200) }}</td></tr>
        @endif
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      @if($topHoldings->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-folder me-1"></i> isHolderOf (Top-level holdings)
          <span class="badge bg-light text-dark float-end">{{ $topHoldings->count() }}</span>
        </div>
        <div class="list-group list-group-flush">
          @foreach($topHoldings as $h)
            <a href="{{ url('/' . $h->slug) }}" class="list-group-item list-group-item-action">
              <i class="fas fa-folder text-info me-1"></i>{{ $h->title }}
            </a>
          @endforeach
        </div>
      </div>
      @endif

      @if($relatedAgents->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-users me-1"></i> Related Agents
        </div>
        <div class="list-group list-group-flush">
          @foreach($relatedAgents as $rel)
            <a href="{{ url('/' . $rel->slug) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
              <span>{{ $rel->name }}</span>
              @if($rel->relation_type)<span class="badge bg-secondary">{{ $rel->relation_type }}</span>@endif
            </a>
          @endforeach
        </div>
      </div>
      @endif
    </div>

    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-bolt me-1"></i> Actions</div>
        <div class="card-body">
          <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2"><i class="fas fa-project-diagram me-1"></i>Open in Graph Explorer</a>
          <a href="/ric-api/relations/{{ $repository->id }}" class="btn btn-sm btn-outline-info w-100" target="_blank"><i class="fas fa-code me-1"></i>View Relations JSON</a>
        </div>
      </div>
    </div>
  </div>
</div>
