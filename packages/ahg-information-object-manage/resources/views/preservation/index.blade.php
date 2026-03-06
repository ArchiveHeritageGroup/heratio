@extends('theme::layouts.1col')
@section('title', 'Digital Preservation — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-shield-alt',
    'featureTitle' => 'Preservation Packages (OAIS)',
    'featureDescription' => 'Archival Information Packages, fixity, PREMIS events',
  ])

  <h5 class="mt-3">Archival Information Packages (AIPs)</h5>
  @if($aips->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No AIPs linked to this description.
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr><th>UUID</th><th>Filename</th><th>Size</th><th>Created</th></tr>
        </thead>
        <tbody>
          @foreach($aips as $aip)
            <tr>
              <td><code>{{ $aip->uuid ?? '—' }}</code></td>
              <td>{{ $aip->filename ?? '—' }}</td>
              <td>{{ isset($aip->size_on_disk) ? number_format($aip->size_on_disk / 1048576, 1) . ' MB' : '—' }}</td>
              <td>{{ $aip->created_at ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <h5 class="mt-4">PREMIS Objects</h5>
  @if($premisObjects->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No PREMIS objects recorded.
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr><th>PUID</th><th>MIME type</th><th>Size</th><th>Ingested</th></tr>
        </thead>
        <tbody>
          @foreach($premisObjects as $po)
            <tr>
              <td>{{ $po->puid ?? '—' }}</td>
              <td>{{ $po->mime_type ?? '—' }}</td>
              <td>{{ isset($po->size) ? number_format($po->size / 1024, 1) . ' KB' : '—' }}</td>
              <td>{{ $po->date_ingested ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
