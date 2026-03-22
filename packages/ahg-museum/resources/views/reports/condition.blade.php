@extends('theme::layouts.1col')
@section('title', 'Condition Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-heartbeat me-2"></i>Condition Report</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Object</th><th>Condition</th><th>Date</th><th>Treatment</th><th>Notes</th></tr></thead><tbody>@forelse($records??[] as $r)<tr><td><strong>{{ e($r->title??"Untitled") }}</strong></td><td><span class="badge bg-secondary">{{ ucfirst($r->condition_term??"-") }}</span></td><td>{{ $r->condition_date?date("d M Y",strtotime($r->condition_date)):"-" }}</td><td>{{ e($r->treatment_type??"-") }}</td><td>{{ \Illuminate\Support\Str::limit($r->notes??"",60) }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No reports.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
