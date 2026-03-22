@extends('theme::layouts.1col')
@section('title', 'Serials')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-newspaper me-2"></i>Serials</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Title</th><th>ISSN</th><th>Frequency</th><th>Status</th><th class="text-end">Issues</th></tr></thead><tbody>@forelse($serials??[] as $s)<tr><td><a href="{{ route("library.serial-view",$s->id??0) }}"><strong>{{ e($s->title??"") }}</strong></a></td><td><code>{{ e($s->issn??"") }}</code></td><td>{{ e($s->frequency??"") }}</td><td><span class="badge bg-{{ ($s->status??"")=="active"?"success":"secondary" }}">{{ ucfirst($s->status??"") }}</span></td><td class="text-end">{{ $s->issue_count??0 }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No serials.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
