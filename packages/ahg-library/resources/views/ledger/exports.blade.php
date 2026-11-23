@extends('theme::layouts.1col')
@section('title', __('Finance export'))
@section('content')
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-file-export me-2"></i>{{ __('Finance export') }}</h1>
@include('ahg-library::ledger._nav')
<form method="post" action="{{ route('library.ledger.export-create') }}" class="mb-3" onsubmit="return confirm('{{ __('Create a batch of all posted documents not yet exported? They can no longer be voided afterwards.') }}')">@csrf<button class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Create export batch') }}</button></form>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>{{ __('Batch') }}</th><th>{{ __('Created') }}</th><th class="text-end">{{ __('Documents') }}</th><th></th></tr></thead>
<tbody>@forelse($batches as $b)<tr><td>{{ $b->export_batch }}</td><td>{{ $b->exported_at }}</td><td class="text-end">{{ $b->documents }}</td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('library.ledger.export-download',$b->export_batch) }}">{{ __('Download CSV') }}</a></td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">{{ __('No exports yet.') }}</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
