@extends('theme::layouts.1col')
@section('title', __('Invoices and credit notes'))
@section('content')
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="mb-0"><i class="fas fa-receipt me-2"></i>{{ __('Invoices and credit notes') }}</h1><a class="btn atom-btn-white" href="{{ route('library.ledger.create') }}"><i class="fas fa-plus me-1"></i>{{ __('New invoice') }}</a></div>
@include('ahg-library::ledger._nav')
<form method="get" class="row g-2 mb-3">
  <div class="col-md-4"><label class="visually-hidden" for="q">{{ __('Search') }}</label><input id="q" name="q" class="form-control" maxlength="100" placeholder="{{ __('Number, supplier reference or supplier') }}" value="{{ $filters['q'] ?? '' }}"></div>
  <div class="col-md-3"><label class="visually-hidden" for="doc_type">{{ __('Type') }}</label><select id="doc_type" name="doc_type" class="form-select"><option value="">{{ __('All types') }}</option>@foreach($docTypes as $c=>$l)<option value="{{ $c }}" @selected(($filters['doc_type'] ?? '')===$c)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-3"><label class="visually-hidden" for="status">{{ __('Status') }}</label><select id="status" name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach($statuses as $c=>$l)<option value="{{ $c }}" @selected(($filters['status'] ?? '')===$c)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('Filter') }}</button></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-striped table-hover mb-0">
<thead><tr><th>{{ __('Number') }}</th><th>{{ __('Type') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Supplier ref') }}</th><th>{{ __('Order') }}</th><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
<tbody>@forelse($documents as $d)<tr><td><a href="{{ route('library.ledger.show',$d->id) }}">{{ $d->document_number ?: __('Draft #:id',['id'=>$d->id]) }}</a></td><td>{{ $docTypes[$d->doc_type] ?? $d->doc_type }}</td><td>{{ $d->vendor_name }}</td><td>{{ $d->supplier_reference }}</td><td>{{ $d->order_number }}</td><td>{{ $d->document_date }}</td><td><span class="badge bg-secondary">{{ $statuses[$d->status] ?? $d->status }}</span></td><td class="text-end">{{ number_format((float)$d->total,2) }} {{ $d->currency }}</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">{{ __('No documents found.') }}</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $documents->links() }}</div>
</div>
@endsection
