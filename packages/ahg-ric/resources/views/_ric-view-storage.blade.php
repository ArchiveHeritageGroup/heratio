{{-- RiC View: Physical Storage — instantiation carrier context --}}
@php
  $culture = app()->getLocale();

  // Descriptions stored in this location
  $storedDescriptions = \Illuminate\Support\Facades\DB::table('relation')
      ->join('information_object_i18n as ioi', function ($j) use ($storage, $culture) {
          $j->on(DB::raw("CASE WHEN relation.subject_id = {$storage->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
            ->where('ioi.culture', $culture);
      })
      ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$storage->id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
      ->where(function ($q) use ($storage) {
          $q->where('relation.subject_id', $storage->id)->orWhere('relation.object_id', $storage->id);
      })
      ->whereNotNull('ioi.title')
      ->select('ioi.title', 'slug.slug')
      ->limit(30)
      ->get();
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-archive me-2"></i><strong>{{ $storage->name ?: '[Untitled]' }}</strong></div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">{{ __('Physical Carrier (Storage Location)') }}</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">{{ __('RiC Concept') }}</th><td>rico:Instantiation (Physical Carrier)</td></tr>
        @if($typeName ?? null)<tr><th class="text-muted">{{ __('Type') }}</th><td>{{ $typeName }}</td></tr>@endif
        @if($storage->location ?? null)<tr><th class="text-muted">{{ __('Location') }}</th><td>{{ $storage->location }}</td></tr>@endif
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      @if($storedDescriptions->count())
      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-file-alt me-1"></i> Stored Descriptions
          <span class="badge bg-light text-dark float-end">{{ $storedDescriptions->count() }}</span>
        </div>
        <div class="list-group list-group-flush">
          @foreach($storedDescriptions as $desc)
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
          <a href="/explorer" class="btn btn-sm btn-outline-success w-100 mb-2"><i class="fas fa-project-diagram me-1"></i>{{ __('Open in Graph Explorer') }}</a>
          <a href="/ric-api/relations/{{ $storage->id }}" class="btn btn-sm btn-outline-info w-100" target="_blank"><i class="fas fa-code me-1"></i>{{ __('View Relations JSON') }}</a>
        </div>
      </div>
    </div>
  </div>
</div>
