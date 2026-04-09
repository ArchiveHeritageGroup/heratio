{{-- RiC View: Term — concept/subject with linked descriptions --}}
@php
  $culture = app()->getLocale();

  // Descriptions linked to this term
  $linkedDescriptions = \Illuminate\Support\Facades\DB::table('object_term_relation')
      ->join('information_object_i18n as ioi', 'object_term_relation.object_id', '=', 'ioi.id')
      ->leftJoin('slug', 'object_term_relation.object_id', '=', 'slug.object_id')
      ->where('object_term_relation.term_id', $term->id)
      ->where('ioi.culture', $culture)
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug')
      ->limit(20)
      ->get();

  // Parent term
  $parentTerm = null;
  if ($term->parent_id ?? null) {
      $parentTerm = \Illuminate\Support\Facades\DB::table('term_i18n')
          ->leftJoin('slug', 'term_i18n.id', '=', 'slug.object_id')
          ->where('term_i18n.id', $term->parent_id)
          ->where('term_i18n.culture', $culture)
          ->select('term_i18n.name', 'slug.slug')
          ->first();
  }

  // Child terms
  $childTerms = \Illuminate\Support\Facades\DB::table('term')
      ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
      ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
      ->where('term.parent_id', $term->id)
      ->where('term_i18n.culture', $culture)
      ->select('term_i18n.name', 'slug.slug')
      ->orderBy('term_i18n.name')
      ->limit(30)
      ->get();

  // Taxonomy name
  $taxonomyName = \Illuminate\Support\Facades\DB::table('taxonomy_i18n')
      ->where('id', $term->taxonomy_id ?? 0)
      ->where('culture', $culture)
      ->value('name');
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-tag me-2"></i><strong>{{ $term->name }}</strong></div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:Concept</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">RiC Entity Type</th><td><code>rico:Concept</code></td></tr>
        @if($taxonomyName)<tr><th class="text-muted">Taxonomy</th><td>{{ $taxonomyName }}</td></tr>@endif
        @if($parentTerm)<tr><th class="text-muted">broaderTerm</th><td><a href="{{ url('/' . $parentTerm->slug) }}">{{ $parentTerm->name }}</a></td></tr>@endif
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      @if($childTerms->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-sitemap me-1"></i> Narrower Terms (hasPart)
          <span class="badge bg-light text-dark float-end">{{ $childTerms->count() }}</span>
        </div>
        <div class="card-body">
          @foreach($childTerms as $ct)
            <a href="{{ url('/' . $ct->slug) }}" class="badge bg-primary text-decoration-none me-1 mb-1">{{ $ct->name }}</a>
          @endforeach
        </div>
      </div>
      @endif

      @if($linkedDescriptions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-file-alt me-1"></i> Descriptions with this Subject (hasOrHadSubject)
          <span class="badge bg-light text-dark float-end">{{ $linkedDescriptions->count() }}</span>
        </div>
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
        </div>
      </div>
    </div>
  </div>
</div>
