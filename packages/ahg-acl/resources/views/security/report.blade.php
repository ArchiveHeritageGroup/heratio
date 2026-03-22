@extends('theme::layouts.1col')
@section('title', 'Security Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-chart-pie me-2"></i>Security Report</h1><div class="row g-3 mb-4"><div class="col-md-4"><div class="card"><div class="card-body text-center"><h3>{{ $stats["classified_count"]??0 }}</h3><small>Classified Objects</small></div></div></div><div class="col-md-4"><div class="card"><div class="card-body text-center"><h3>{{ $stats["cleared_users"]??0 }}</h3><small>Cleared Users</small></div></div></div><div class="col-md-4"><div class="card"><div class="card-body text-center"><h3>{{ $stats["denied_count"]??0 }}</h3><small>Access Denials</small></div></div></div></div><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Classification Breakdown</h5></div><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Level</th><th>Code</th><th class="text-end">Objects</th></tr></thead><tbody>@forelse($breakdown??[] as $b)<tr><td><span class="badge" style="background-color:{{ $b->color??"#999" }}">{{ e($b->name??"") }}</span></td><td><code>{{ e($b->code??"") }}</code></td><td class="text-end">{{ number_format($b->count??0) }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center">No data</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
