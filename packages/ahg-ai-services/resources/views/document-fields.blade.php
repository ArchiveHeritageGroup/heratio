@extends('theme::layouts.1col')

{{-- Invoice / purchase-order fields read from a record's PDF through the AI
     gateway, each one checked against the document's own text, and the match
     between an invoice and the purchase order it quotes. --}}

@section('title', __('Document fields').' - '.($io->title ?? ''))
@section('body-class', 'browse ai')

@section('content')
@php
  $labels = [
    'document_type' => __('Document type'), 'number' => __('Number'), 'date' => __('Date'), 'supplier' => __('Supplier'),
    'supplier_vat' => __('Supplier VAT number'), 'po_number' => __('Purchase order quoted'), 'subtotal' => __('Subtotal'),
    'vat_amount' => __('VAT'), 'total' => __('Total'), 'currency' => __('Currency'),
  ];
  $first = collect($fields)->first();
  $note = $first ? json_decode((string) $first->notes, true) : null;
  $lines = isset($fields['line_items']) ? json_decode((string) $fields['line_items']->suggested_value, true) : [];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-1"><i class="fas fa-file-invoice me-2" aria-hidden="true"></i>{{ __('Document fields') }}</h1>
    <a href="{{ url('/'.$io->slug) }}">{{ $io->title }}</a>
  </div>
  <form method="POST" action="{{ route('admin.ai.document-fields.extract', $io->slug) }}">
    @csrf
    <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-magic me-1" aria-hidden="true"></i>{{ $fields ? __('Read again') : __('Read the document') }}</button>
  </form>
</div>

@foreach(['success' => 'success', 'error' => 'danger'] as $k => $cls)
  @if(session($k))<div class="alert alert-{{ $cls }}">{{ session($k) }}</div>@endif
@endforeach
@if(session('dropped'))
  <div class="alert alert-warning">
    {{ __('Not in the document text, so not kept:') }}
    @foreach(session('dropped') as $f => $v)<span class="badge bg-warning text-dark ms-1">{{ $labels[$f] ?? $f }}: {{ $v }}</span>@endforeach
  </div>
@endif

@if(! $fields)
  <p class="text-muted">{{ __('Nothing read yet. The document is read through the AI gateway (OCR first if it is a scan); every value kept must appear in the document itself.') }}</p>
@else
  @if($match)
    @php
      $m = [
        'matched' => ['success', 'fa-check-circle', __('Matches purchase order :po: the totals agree (:t).', ['po' => $match['po_number'] ?? '', 't' => $match['invoice_total'] ?? ''])],
        'totals_differ' => ['danger', 'fa-exclamation-triangle', __('Purchase order :po found, but the totals differ: invoice :a, order :b.', ['po' => $match['po_number'] ?? '', 'a' => $match['invoice_total'] ?? '-', 'b' => $match['po_total'] ?? '-'])],
        'po_not_found' => ['warning', 'fa-question-circle', __('Purchase order :po is not in the archive yet (or has not been read).', ['po' => $match['po_number'] ?? ''])],
        'no_po_number' => ['secondary', 'fa-info-circle', __('This invoice quotes no purchase order.')],
        'delivered' => ['info', 'fa-truck', __('Delivery note against purchase order :po, which is in the archive. Compare the quantities with the order.', ['po' => $match['po_number'] ?? ''])],
        'delivered_po_not_found' => ['warning', 'fa-question-circle', __('Delivery note against purchase order :po, which is not in the archive yet (or has not been read).', ['po' => $match['po_number'] ?? ''])],
      ][$match['status']];
    @endphp
    <div class="alert alert-{{ $m[0] }}"><i class="fas {{ $m[1] }} me-1" aria-hidden="true"></i>{{ $m[2] }}
      @if($poSlug)<a href="{{ route('admin.ai.document-fields', $poSlug) }}" class="ms-2">{{ __('Open the order') }}</a>@endif
    </div>
  @endif

  <div class="table-responsive mb-4">
    <table class="table table-bordered table-sm">
      <thead><tr><th scope="col">{{ __('Field') }}</th><th scope="col">{{ __('Value') }}</th><th scope="col">{{ __('In the document') }}</th><th scope="col">{{ __('Review') }}</th></tr></thead>
      <tbody>
        @foreach($labels as $f => $label)
          @isset($fields[$f])
            <tr><td>{{ $label }}</td><td>{{ $fields[$f]->suggested_value }}</td>
              <td><i class="fas fa-check text-success" aria-hidden="true"></i> <span class="visually-hidden">{{ __('yes') }}</span></td>
              <td>{{ __(ucfirst($fields[$f]->status)) }}</td></tr>
          @endisset
        @endforeach
      </tbody>
    </table>
  </div>

  @if($lines)
    <h2 class="h5">{{ __('Line items') }}</h2>
    <div class="table-responsive mb-4">
      <table class="table table-bordered table-sm">
        <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Quantity') }}</th><th scope="col">{{ __('Unit price') }}</th><th scope="col">{{ __('Amount') }}</th></tr></thead>
        <tbody>@foreach($lines as $l)<tr><td>{{ $l['description'] ?? '' }}</td><td>{{ $l['quantity'] ?? '' }}</td><td>{{ $l['unit_price'] ?? '' }}</td><td>{{ $l['amount'] ?? '' }}</td></tr>@endforeach</tbody>
      </table>
    </div>
  @endif

  <p class="small text-muted">
    {{ __('Read by :model through the AI gateway', ['model' => $first->model ?? __('unknown model')]) }}@if(! empty($note['ocr'])), {{ __('after OCR') }}@endif.
    @if(! empty($note['receipt']['seq'])) {{ __('Inference receipt #:seq', ['seq' => $note['receipt']['seq']]) }} <code>{{ substr($note['receipt']['hash'] ?? '', 0, 16) }}</code>.@endif
    {{ __('Each value is a pending suggestion until an archivist accepts it.') }}
    <a href="{{ route('admin.ai.suggest-review') }}">{{ __('Review suggestions') }}</a>
  </p>
@endif
@endsection
