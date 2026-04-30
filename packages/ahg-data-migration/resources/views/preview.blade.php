@extends('theme::layouts.1col')

@section('title', 'Preview - Data Migration')
@section('body-class', 'admin data-migration preview')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-eye me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Import Preview') }}</h1>
      <span class="small text-muted">{{ __('Review transformed data before importing') }}</span>
    </div>
  </div>

  <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('data-migration.index') }}">Data Migration</a></li>
      <li class="breadcrumb-item"><a href="{{ route('data-migration.map') }}">Map Fields</a></li>
      <li class="breadcrumb-item active">Preview</li>
    </ol>
  </nav>

  <div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    Target type: <strong>{{ $targetType }}</strong> |
    Total rows in file: <strong>{{ number_format($totalRows) }}</strong> |
    Showing first <strong>{{ count($transformedRows) }}</strong> rows after mapping
  </div>

  @if(count($transformedRows) > 0)
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0"><i class="fas fa-table"></i> Transformed Data</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-striped table-sm mb-0">
            <thead>
              <tr>
                <th style="width: 40px;">#</th>
                @foreach($targetHeaders as $header)
                  <th class="text-nowrap">{{ $header }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($transformedRows as $index => $row)
                <tr>
                  <td class="text-muted">{{ $index + 1 }}</td>
                  @foreach($targetHeaders as $header)
                    <td>{{ Str::limit($row[$header] ?? '', 120) }}</td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle"></i> No data to preview. Check that your mapping has at least one column mapped to a target field.
    </div>
  @endif

  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('data-migration.map') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left"></i> {{ __('Back to Mapping') }}
    </a>
    @if(count($transformedRows) > 0)
      <form method="POST" action="{{ route('data-migration.execute') }}" class="d-inline" onsubmit="return confirm('Are you sure you want to execute this import?')">
        @csrf
        <input type="hidden" name="mapping" value="{{ json_encode($mapping) }}">
        <input type="hidden" name="name" value="CSV Import {{ now()->format('Y-m-d H:i:s') }}">
        <button type="submit" class="btn atom-btn-outline-success">
          <i class="fas fa-play"></i> Execute Import ({{ number_format($totalRows) }} rows)
        </button>
      </form>
    @endif
  </div>
@endsection
