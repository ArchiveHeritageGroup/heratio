@extends('theme::layouts.1col')
@section('title', 'ONIX Ingestion')
@section('content')
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <a href="{{ route('library.acquisitions') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to Acquisitions') }}"><i class="fas fa-arrow-left"></i></a>
    <h1 class="mb-0"><i class="fas fa-file-import me-2"></i>{{ __('ONIX Ingestion') }}</h1>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">{{ __('Upload ONIX message') }}</h5></div>
    <div class="card-body">
      <p class="text-muted small">{{ __('Upload an EDItEUR ONIX for Books message (ONIX 3.0 or 2.1), or paste the XML below. Records are staged for review before they are committed to the catalogue and an acquisitions order.') }}</p>
      <form method="POST" action="{{ route('library.onix-store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label class="form-label">{{ __('ONIX file') }}</label>
          <input type="file" name="onix_file" class="form-control" accept=".xml,.onx,text/xml,application/xml">
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('…or paste ONIX XML') }}</label>
          <textarea name="onix_xml" rows="6" class="form-control" style="font-family:monospace" placeholder="{{ __('&lt;ONIXMessage release=&quot;3.0&quot;&gt;…&lt;/ONIXMessage&gt;') }}"></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Parse & stage') }}</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('Ingest history') }}</h5></div>
    <div class="card-body p-0">
      <table class="table table-striped mb-0 align-middle">
        <thead><tr>
          <th>#</th><th>{{ __('File / source') }}</th><th>{{ __('Version') }}</th><th>{{ __('Status') }}</th>
          <th class="text-end">{{ __('Records') }}</th><th class="text-end">{{ __('Valid') }}</th>
          <th class="text-end">{{ __('Errors') }}</th><th class="text-end">{{ __('Imported') }}</th>
          <th>{{ __('Date') }}</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($ingests as $i)
          <tr>
            <td><a href="{{ route('library.onix-show', $i->id) }}">{{ $i->id }}</a></td>
            <td>{{ e($i->filename ?? '(pasted)') }} <span class="badge bg-light text-dark">{{ $i->source }}</span></td>
            <td>{{ e($i->onix_version ?? '-') }}</td>
            <td>
              @php $sc = ['parsed' => 'bg-info', 'committed' => 'bg-success', 'failed' => 'bg-danger'][$i->status] ?? 'bg-secondary'; @endphp
              <span class="badge {{ $sc }}">{{ ucfirst($i->status) }}</span>
            </td>
            <td class="text-end">{{ $i->record_count }}</td>
            <td class="text-end text-success">{{ $i->valid_count }}</td>
            <td class="text-end text-danger">{{ $i->error_count }}</td>
            <td class="text-end">{{ $i->imported_count }}</td>
            <td><small>{{ $i->created_at }}</small></td>
            <td class="text-end">
              <a href="{{ route('library.onix-show', $i->id) }}" class="btn btn-sm btn-outline-primary">{{ __('Review') }}</a>
              <form method="POST" action="{{ route('library.onix-destroy', $i->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this ingest log? Committed catalogue records are kept.') }}')">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="text-muted text-center py-3">{{ __('No ONIX ingests yet.') }}</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
