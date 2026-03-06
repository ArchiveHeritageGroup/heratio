<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Print Preview - {{ $parent->title ?? 'GLAM Browse Results' }}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { font-size: 12px; }
    @media print {
      .no-print { display: none !important; }
      body { font-size: 10px; }
      .table th, .table td { padding: 0.25rem 0.5rem; }
    }
    .print-header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
  </style>
</head>
<body class="p-4">

  {{-- Action buttons --}}
  <div class="no-print mb-4 d-flex gap-2">
    <button type="button" class="btn btn-primary" onclick="window.print();">
      <i class="fas fa-print me-1"></i> Print
    </button>
    <button type="button" class="btn btn-secondary" onclick="window.close();">
      <i class="fas fa-times me-1"></i> Close
    </button>
  </div>

  {{-- Header --}}
  <div class="print-header">
    <h2>{{ $parent->title ?? 'GLAM Browse Results' }}</h2>
    <div class="text-muted">
      <span class="me-3"><strong>Total:</strong> {{ number_format($total ?? 0) }} records</span>
      <span class="me-3"><strong>Date:</strong> {{ now()->format('Y-m-d H:i') }}</span>
      @if(!empty($typeFilter))
        <span><strong>Type filter:</strong> {{ ucfirst($typeFilter) }}</span>
      @endif
    </div>
  </div>

  {{-- Results table --}}
  @if(!empty($objects) && count($objects))
    <table class="table table-bordered table-sm">
      <thead class="table-light">
        <tr>
          <th style="width: 15%;">Identifier</th>
          <th style="width: 25%;">Title</th>
          <th style="width: 10%;">Level</th>
          <th style="width: 10%;">Type</th>
          <th style="width: 40%;">Scope and Content</th>
        </tr>
      </thead>
      <tbody>
        @foreach($objects as $object)
          <tr>
            <td>{{ $object->identifier ?? '-' }}</td>
            <td>{{ $object->title ?? '-' }}</td>
            <td>{{ $object->level ?? $object->level_of_description ?? '-' }}</td>
            <td>{{ $object->type ?? $object->collection_type ?? '-' }}</td>
            <td>{{ $object->scope_and_content ?? $object->scopeAndContent ?? '-' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="text-muted">No records to display.</p>
  @endif

  {{-- Footer --}}
  <div class="mt-4 text-muted text-center small">
    Printed from Heratio on {{ now()->format('Y-m-d H:i:s') }}
  </div>

</body>
</html>
