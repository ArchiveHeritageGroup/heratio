@extends('theme::layout')

@section('title', $reportTitle . ' — ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container-fluid py-3">
  <div class="row">

    {{-- Sidebar --}}
    <div class="col-md-3">
      <div class="card mb-3">
        <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-info-circle me-1"></i> Context
        </div>
        <div class="list-group list-group-flush">
          <a href="{{ route('informationobject.show', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-arrow-left me-1"></i> Back to description
          </a>
          <a href="{{ route('informationobject.reports', $io->slug) }}" class="list-group-item list-group-item-action small">
            <i class="fas fa-chart-bar me-1"></i> Back to reports
          </a>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header fw-bold small" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-print me-1"></i> Actions
        </div>
        <div class="card-body">
          <button onclick="window.print()" class="btn atom-btn-white btn-sm w-100 mb-2">
            <i class="fas fa-print me-1"></i> Print
          </button>
        </div>
      </div>
    </div>

    {{-- Main content --}}
    <div class="col-md-9">
      <div class="multiline-header d-flex flex-column mb-3">
        <h1 class="mb-0">{{ $reportTitle }}</h1>
        <span class="small text-muted">{{ $io->title ?? 'Untitled' }}</span>
      </div>

      @if($type === 'fileList' || $type === 'itemList')
        @if(count($items) > 0)
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>{{ $reportTitle }}</span>
              <span class="badge bg-primary">{{ count($items) }} items</span>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Identifier') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Scope &amp; Content') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($items as $item)
                    <tr>
                      <td><small>{{ e($item->identifier ?? '') }}</small></td>
                      <td>
                        @if($item->slug ?? null)
                          <a href="{{ url('/' . $item->slug) }}">{{ e($item->title ?? 'Untitled') }}</a>
                        @else
                          {{ e($item->title ?? 'Untitled') }}
                        @endif
                      </td>
                      <td><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($item->scope_and_content ?? '', 150)) }}</small></td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> No {{ $type === 'fileList' ? 'file' : 'item' }}-level children found for this description.
          </div>
        @endif

      @elseif($type === 'storageLocations')
        @if(count($items) > 0)
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>Physical Storage Locations</span>
              <span class="badge bg-primary">{{ count($items) }}</span>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Location') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($items as $item)
                    <tr>
                      <td>{{ e($item->name ?? '') }}</td>
                      <td>{{ e($item->type ?? '') }}</td>
                      <td>{{ e($item->location ?? '') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> No physical storage locations linked to this description.
          </div>
        @endif

      @elseif($type === 'boxLabel')
        @if(count($items) > 0)
          @foreach($items as $item)
            <div class="card mb-3 box-label-card" style="border:2px solid #333;">
              <div class="card-body text-center py-4">
                <h3 class="mb-2">{{ e($item->name ?? '') }}</h3>
                <p class="mb-1 text-muted">{{ e($item->location ?? '') }}</p>
                <p class="mb-0"><small>{{ e($io->title ?? '') }}</small></p>
              </div>
            </div>
          @endforeach
        @else
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-1"></i> No physical objects linked for box labels.
          </div>
        @endif
      @endif
    </div>
  </div>
</div>
@endsection
