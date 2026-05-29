@extends('theme::layouts.1col')
@section('title', 'ONIX Ingest #' . $ingest->id)
@section('content')
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <a href="{{ route('library.onix-index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to ONIX Ingestion') }}"><i class="fas fa-arrow-left"></i></a>
    <h1 class="mb-0"><i class="fas fa-file-import me-2"></i>{{ __('ONIX Ingest') }} #{{ $ingest->id }}</h1>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="card mb-4">
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-2">{{ __('File / source') }}</dt><dd class="col-sm-4">{{ e($ingest->filename ?? '(pasted)') }} <span class="badge bg-light text-dark">{{ $ingest->source }}</span></dd>
        <dt class="col-sm-2">{{ __('ONIX version') }}</dt><dd class="col-sm-4">{{ e($ingest->onix_version ?? '-') }}</dd>
        <dt class="col-sm-2">{{ __('Status') }}</dt>
        <dd class="col-sm-4">
          @php $sc = ['parsed' => 'bg-info', 'committed' => 'bg-success', 'failed' => 'bg-danger'][$ingest->status] ?? 'bg-secondary'; @endphp
          <span class="badge {{ $sc }}">{{ ucfirst($ingest->status) }}</span>
          @if($ingest->order_id)<a href="{{ route('library.acquisition-order', $ingest->order_id) }}" class="ms-2">{{ __('Order') }} #{{ $ingest->order_id }}</a>@endif
        </dd>
        <dt class="col-sm-2">{{ __('Totals') }}</dt>
        <dd class="col-sm-10">
          {{ $ingest->record_count }} {{ __('records') }} ·
          <span class="text-success">{{ $ingest->valid_count }} {{ __('valid') }}</span> ·
          <span class="text-danger">{{ $ingest->error_count }} {{ __('errors') }}</span> ·
          {{ $ingest->imported_count }} {{ __('imported') }}
        </dd>
      </dl>
    </div>
    @if($ingest->status !== 'committed' && $ingest->valid_count > 0)
    <div class="card-footer text-end">
      <form method="POST" action="{{ route('library.onix-commit', $ingest->id) }}" onsubmit="return confirm('{{ __('Commit all valid records to the catalogue and create an acquisitions order?') }}')">
        @csrf
        <button class="btn btn-success"><i class="fas fa-check me-1"></i>{{ __('Commit valid records') }}</button>
      </form>
    </div>
    @endif
  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('Review queue') }}</h5></div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0 align-middle">
        <thead><tr>
          <th>{{ __('Title') }}</th><th>{{ __('Author') }}</th><th>{{ __('ISBN/ISSN') }}</th>
          <th>{{ __('Publisher') }}</th><th class="text-end">{{ __('Price') }}</th>
          <th>{{ __('Status') }}</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($lines as $l)
          <tr>
            <td>
              {{ e($l->title ?? '(untitled)') }}
              @if($l->subtitle)<div class="text-muted small">{{ e($l->subtitle) }}</div>@endif
              @if($l->error)<div class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i>{{ e($l->error) }}</div>@endif
            </td>
            <td>{{ e($l->author ?? '-') }}</td>
            <td><code>{{ e($l->isbn ?? $l->issn ?? '-') }}</code></td>
            <td>{{ e($l->publisher ?? '-') }}</td>
            <td class="text-end">{{ $l->price !== null ? number_format($l->price, 2) . ' ' . e($l->currency ?? '') : '-' }}</td>
            <td>
              @php $lc = ['valid' => 'bg-success', 'invalid' => 'bg-danger', 'duplicate' => 'bg-warning text-dark', 'imported' => 'bg-primary', 'skipped' => 'bg-secondary', 'parsed' => 'bg-info'][$l->status] ?? 'bg-secondary'; @endphp
              <span class="badge {{ $lc }}">{{ ucfirst($l->status) }}</span>
              @if($l->library_item_id)
                <a href="{{ route('library.marc-edit', $l->library_item_id) }}" class="ms-1 small">{{ __('item') }} #{{ $l->library_item_id }}</a>
              @endif
            </td>
            <td class="text-end">
              @if($ingest->status !== 'committed' && in_array($l->status, ['valid', 'skipped', 'duplicate']))
                <form method="POST" action="{{ route('library.onix-line-status', $l->id) }}" class="d-inline">
                  @csrf
                  @if($l->status === 'skipped')
                    <input type="hidden" name="status" value="valid">
                    <button class="btn btn-sm btn-outline-success">{{ __('Include') }}</button>
                  @else
                    <input type="hidden" name="status" value="skipped">
                    <button class="btn btn-sm btn-outline-secondary">{{ __('Skip') }}</button>
                  @endif
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-muted text-center py-3">{{ __('No records parsed.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
