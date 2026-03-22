@extends('theme::layouts.1col')
@section('title', 'Materials Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-layer-group me-2"></i>Materials &amp; Techniques</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Object</th><th>Materials</th><th>Techniques</th><th>Dimensions</th></tr></thead><tbody>@forelse($records??[] as $r)<tr><td><strong>{{ e($r->title??"Untitled") }}</strong></td><td>{{ e($r->materials??"-") }}</td><td>{{ e($r->techniques??"-") }}</td><td>{{ e($r->dimensions??$r->measurements??"-") }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No data.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
