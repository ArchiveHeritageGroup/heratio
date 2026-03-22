@extends('theme::layouts.1col')
@section('title', 'Style & Period Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-theater-masks me-2"></i>Style &amp; Period Report</h1><div class="row"><div class="col-md-6 mb-4"><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff">By Style</div><ul class="list-group list-group-flush">@forelse($byStyle??[] as $s)<li class="list-group-item d-flex justify-content-between">{{ e($s->style) }} <span class="badge bg-primary">{{ $s->count }}</span></li>@empty<li class="list-group-item text-muted">No styles recorded</li>@endforelse</ul></div></div><div class="col-md-6 mb-4"><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff">By Period</div><ul class="list-group list-group-flush">@forelse($byPeriod??[] as $p)<li class="list-group-item d-flex justify-content-between">{{ e($p->period) }} <span class="badge bg-success">{{ $p->count }}</span></li>@empty<li class="list-group-item text-muted">No periods recorded</li>@endforelse</ul></div></div></div>
</div>
@endsection
