{{--
  RiC View: Museum object — record-resource context with creators and place of origin.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@php
  $culture = app()->getLocale();

  $creators = collect();
  if (isset($museum->id)) {
      $creators = \Illuminate\Support\Facades\DB::table('relation as r')
          ->join('actor_i18n as ai', function ($j) use ($museum, $culture) {
              $j->on(DB::raw("CASE WHEN r.subject_id = {$museum->id} THEN r.object_id ELSE r.subject_id END"), '=', 'ai.id')
                ->where('ai.culture', $culture);
          })
          ->leftJoin('slug', 'slug.object_id', '=', 'ai.id')
          ->where(function ($q) use ($museum) {
              $q->where('r.subject_id', $museum->id)->orWhere('r.object_id', $museum->id);
          })
          ->whereNotNull('ai.authorized_form_of_name')
          ->select('ai.authorized_form_of_name as name', 'slug.slug')
          ->limit(20)
          ->get();
  }

  $museumTitle = $museum->title ?? '[Untitled object]';
@endphp

<div class="ric-view">
  <div class="card mb-3 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
      <div><i class="fas fa-landmark me-2"></i><strong>{{ $museumTitle }}</strong></div>
      <span style="background:#fff !important;color:#198754 !important;border:2px solid #198754;padding:.25em .6em;border-radius:.375em;font-size:.85em;font-weight:600;display:inline-block;">rico:RecordResource (Museum object)</span>
    </div>
    <div class="card-body">
      <table class="table table-sm mb-0">
        <tr><th class="text-muted" style="width:35%">{{ __('RiC Role') }}</th><td>Cultural object / heritage asset</td></tr>
        @if(! empty($museum->object_type))<tr><th class="text-muted">{{ __('Object type') }}</th><td>{{ $museum->object_type }}</td></tr>@endif
        @if(! empty($museum->material))<tr><th class="text-muted">{{ __('Material') }}</th><td>{{ $museum->material }}</td></tr>@endif
        @if(! empty($museum->date_created))<tr><th class="text-muted">{{ __('Date created') }}</th><td>{{ $museum->date_created }}</td></tr>@endif
        @if(! empty($museum->place_of_origin))<tr><th class="text-muted">{{ __('rico:hasOrigin') }}</th><td>{{ $museum->place_of_origin }}</td></tr>@endif
        @if(! empty($museum->accession_number))<tr><th class="text-muted">{{ __('Accession number') }}</th><td><code>{{ $museum->accession_number }}</code></td></tr>@endif
      </table>
    </div>
  </div>

  @if($creators->count())
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-user-pen me-1"></i> rico:hasCreator</div>
      <div class="list-group list-group-flush">
        @foreach($creators as $c)
          <a href="{{ $c->slug ? url('/' . $c->slug) : '#' }}" class="list-group-item list-group-item-action">{{ $c->name }}</a>
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
