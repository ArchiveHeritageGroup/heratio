{{-- RiC View: Donor — provenance agent with accession links --}}
@php
  $culture = app()->getLocale();

  // Accessions linked to this donor via relation table
  $accessions = \Illuminate\Support\Facades\DB::table('relation')
      ->join('accession_i18n as ai', 'relation.subject_id', '=', 'ai.id')
      ->leftJoin('slug', 'relation.subject_id', '=', 'slug.object_id')
      ->where('relation.object_id', $donor->id)
      ->where('ai.culture', $culture)
      ->select('ai.title', 'slug.slug', 'ai.id')
      ->limit(20)
      ->get();

  // Related descriptions via relation
  $relatedDescriptions = \Illuminate\Support\Facades\DB::table('relation')
      ->join('information_object_i18n as ioi', function ($j) use ($donor, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$donor->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
            ->where('ioi.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$donor->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->where(function ($q) use ($donor) {
          $q->where('relation.subject_id', $donor->id)->orWhere('relation.object_id', $donor->id);
      })
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug')
      ->limit(20)
      ->get();
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-hand-holding-heart me-2"></i><strong>{{ $donor->authorized_form_of_name }}</strong></div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:Agent (Donor)</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">RiC Role</th><td>Provenance agent / Donor</td></tr>
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      @if($accessions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-inbox me-1"></i> Accessions (donated)</div>
        <div class="list-group list-group-flush">
          @foreach($accessions as $acc)
            <a href="{{ url('/' . $acc->slug) }}" class="list-group-item list-group-item-action"><i class="fas fa-inbox text-info me-1"></i>{{ $acc->title ?: '[Untitled]' }}</a>
          @endforeach
        </div>
      </div>
      @endif

      @if($relatedDescriptions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-file-alt me-1"></i> Related Descriptions</div>
        <div class="list-group list-group-flush">
          @foreach($relatedDescriptions as $desc)
            <a href="{{ url('/' . $desc->slug) }}" class="list-group-item list-group-item-action">{{ $desc->title }}</a>
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
        </div>
      </div>
    </div>
  </div>
</div>
