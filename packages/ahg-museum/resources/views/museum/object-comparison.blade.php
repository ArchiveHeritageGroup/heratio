@extends('theme::layouts.1col')
@section('title', 'Object Comparison')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-columns me-2"></i>Object Comparison</h1><div class="row">@foreach($objects??[] as $obj)<div class="col-md-{{ 12/max(count($objects??[1]),1) }}"><div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ e($obj->title??"Untitled") }}</h5></div><div class="card-body"><dl><dt>Identifier</dt><dd>{{ e($obj->identifier??"") }}</dd><dt>Work Type</dt><dd>{{ e($obj->work_type??"") }}</dd><dt>Creator</dt><dd>{{ e($obj->creator_identity??"") }}</dd><dt>Date</dt><dd>{{ e($obj->creation_date_display??"") }}</dd></dl></div></div></div>@endforeach</div>
</div>
@endsection
