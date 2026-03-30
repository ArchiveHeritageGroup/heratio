{{-- RiC View: Accession — provenance and transfer context --}}
@php
  $culture = app()->getLocale();

  // Donors linked via relation table
  $donors = \Illuminate\Support\Facades\DB::table('relation')
      ->join('actor_i18n as ai', 'relation.object_id', '=', 'ai.id')
      ->leftJoin('slug', 'relation.object_id', '=', 'slug.object_id')
      ->where('relation.subject_id', $accession->id)
      ->where('ai.culture', $culture)
      ->whereNotNull('ai.authorized_form_of_name')
      ->select('ai.authorized_form_of_name as name', 'slug.slug')
      ->get();

  // Linked descriptions
  $linkedDescriptions = \Illuminate\Support\Facades\DB::table('relation')
      ->join('information_object_i18n as ioi', 'relation.object_id', '=', 'ioi.id')
      ->leftJoin('slug', 'relation.object_id', '=', 'slug.object_id')
      ->where('relation.subject_id', $accession->id)
      ->where('ioi.culture', $culture)
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug')
      ->limit(20)
      ->get();
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-inbox me-2"></i><strong>{{ $accession->title ?: $accession->identifier ?: '[Untitled]' }}</strong></div>
      <span class="badge bg-light text-success">Accession (Transfer Activity)</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">RiC Concept</th><td>rico:Activity (Transfer / Accession)</td></tr>
        @if($accession->identifier)<tr><th class="text-muted">Identifier</th><td>{{ $accession->identifier }}</td></tr>@endif
        @if($accession->date)<tr><th class="text-muted">Date</th><td>{{ $accession->date }}</td></tr>@endif
        @if($accession->scope_and_content)<tr><th class="text-muted">Scope</th><td>{{ \Illuminate\Support\Str::limit(strip_tags($accession->scope_and_content), 200) }}</td></tr>@endif
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      @if($donors->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-hand-holding-heart me-1"></i> Donors / Source (provenance)</div>
        <div class="list-group list-group-flush">
          @foreach($donors as $d)
            <a href="{{ url('/' . $d->slug) }}" class="list-group-item list-group-item-action"><i class="fas fa-user text-danger me-1"></i>{{ $d->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      @if($linkedDescriptions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-file-alt me-1"></i> Linked Descriptions</div>
        <div class="list-group list-group-flush">
          @foreach($linkedDescriptions as $desc)
            <a href="{{ url('/' . $desc->slug) }}" class="list-group-item list-group-item-action"><i class="fas fa-file-alt text-info me-1"></i>{{ $desc->title }}</a>
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
          <a href="/ric-api/relations/{{ $accession->id }}" class="btn btn-sm btn-outline-info w-100" target="_blank"><i class="fas fa-code me-1"></i>View Relations JSON</a>
        </div>
      </div>
    </div>
  </div>
</div>
