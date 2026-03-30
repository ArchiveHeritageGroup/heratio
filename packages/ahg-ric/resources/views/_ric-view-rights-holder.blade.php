{{-- RiC View: Rights Holder — rights context and linked records --}}
@php
  $culture = app()->getLocale();

  // Rights statements linked to this rights holder
  $rights = \Illuminate\Support\Facades\DB::table('relation')
      ->join('information_object_i18n as ioi', function ($j) use ($rightsHolder, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$rightsHolder->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
            ->where('ioi.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$rightsHolder->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->where(function ($q) use ($rightsHolder) {
          $q->where('relation.subject_id', $rightsHolder->id)->orWhere('relation.object_id', $rightsHolder->id);
      })
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug')
      ->limit(20)
      ->get();
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-gavel me-2"></i><strong>{{ $rightsHolder->authorized_form_of_name }}</strong></div>
      <span class="badge bg-light text-success">rico:Agent (Rights Holder)</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">RiC Role</th><td>Rights holder / Access authority</td></tr>
      </table>
    </div>
  </div>

  @if($rights->count())
  <div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-file-alt me-1"></i> Related Descriptions (rights context)</div>
    <div class="list-group list-group-flush">
      @foreach($rights as $r)
        <a href="{{ url('/' . $r->slug) }}" class="list-group-item list-group-item-action">{{ $r->title }}</a>
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
