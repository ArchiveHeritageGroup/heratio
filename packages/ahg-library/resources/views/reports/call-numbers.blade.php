@extends('theme::layouts.1col')
@section('title', 'Call Numbers Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-sort-alpha-down me-2"></i>Call Numbers</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Call Number</th><th>Title</th><th>Classification</th></tr></thead><tbody>@forelse($items??[] as $i)<tr><td><code>{{ e($i->call_number??"") }}</code></td><td>{{ e($i->title??"") }}</td><td>{{ e($i->classification_scheme??"") }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center py-3">No items with call numbers.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
