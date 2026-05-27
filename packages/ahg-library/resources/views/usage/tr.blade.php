{{-- resources/views/usage/tr.blade.php - TR Title Report --}}
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-book"></i> Title Report (TR)</span>
    <small class="text-muted">COUNTER 5 — Per-title usage metrics</small>
  </div>
  <div class="card-body">

    <form method="get" class="row g-2 mb-3" action="{{ route('library.usage.title-report') }}">
      <div class="col-auto">
        <label class="form-label small">Start date</label>
        <input type="date" name="start" class="form-control" value="{{ $start ?? old('start', now()->startOfMonth()->toDateString()) }}">
      </div>
      <div class="col-auto">
        <label class="form-label small">End date</label>
        <input type="date" name="end" class="form-control" value="{{ $end ?? old('end', now()->toDateString()) }}">
      </div>
      <div class="col-auto d-flex align-items-end">
        <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
      </div>
    </form>

    @if($rows->isEmpty())
      <p class="text-muted">No usage records for this period.</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>Title</th>
              <th class="text-end">Total</th>
              <th class="text-end">HTML</th>
              <th class="text-end">PDF</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rows as $row)
              <tr>
                <td>{{ $row['title'] }}</td>
                <td class="text-end">{{ number_format($row['total']) }}</td>
                <td class="text-end">{{ number_format($row['html']) }}</td>
                <td class="text-end">{{ number_format($row['pdf']) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td>Total</td>
              <td class="text-end">{{ number_format($rows->sum('total')) }}</td>
              <td class="text-end">{{ number_format($rows->sum('html')) }}</td>
              <td class="text-end">{{ number_format($rows->sum('pdf')) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div class="mt-2">
        <a href="{{ route('library.usage.export', ['type' => 'tr', 'start' => old('start', $start ?? ''), 'end' => old('end', $end ?? '')]) }}"
           class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-download"></i> Export TSV
        </a>
      </div>
    @endif
  </div>
</div>
