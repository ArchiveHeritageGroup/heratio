@extends('theme::layouts.2col')
@section('title', 'Preservation & Backup Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-cloud-upload-alt me-2"></i>Preservation & Backup Settings</h1>
@endsection

@section('content')
    <p class="text-muted">Configure backup replication targets for digital preservation</p>

    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white"><div class="card-body text-center">
          <h3 class="mb-0">{{ $stats['total_targets'] ?? 0 }}</h3><small>{{ __('Total Targets') }}</small>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white"><div class="card-body text-center">
          <h3 class="mb-0">{{ $stats['active_targets'] ?? 0 }}</h3><small>{{ __('Active Targets') }}</small>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white"><div class="card-body text-center">
          <h3 class="mb-0">{{ $stats['successful_syncs'] ?? 0 }}</h3><small>{{ __('Successful Syncs') }}</small>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card bg-{{ ($stats['failed_syncs'] ?? 0) > 0 ? 'danger' : 'secondary' }} text-white"><div class="card-body text-center">
          <h3 class="mb-0">{{ $stats['failed_syncs'] ?? 0 }}</h3><small>{{ __('Failed Syncs') }}</small>
        </div></div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-server me-2"></i>{{ __('Replication Targets') }}</span>
        <button class="btn atom-btn-white btn-sm" data-bs-toggle="modal" data-bs-target="#addTargetModal">
          <i class="fas fa-plus me-1"></i>{{ __('Add Target') }}
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Path/Bucket') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last Sync') }}</th><th>{{ __('Actions') }}</th></tr>
          </thead>
          <tbody>
            @forelse($targets ?? [] as $target)
              <tr>
                <td>{{ $target->name }}</td>
                <td><span class="badge bg-secondary">{{ $target->type }}</span></td>
                <td><code>{{ $target->path ?? $target->bucket ?? '-' }}</code></td>
                <td>
                  @if($target->is_active ?? false)
                    <span class="badge bg-success">{{ __('Active') }}</span>
                  @else
                    <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                  @endif
                </td>
                <td>{{ $target->last_sync_at ?? 'Never' }}</td>
                <td>
                  <a href="#" class="btn btn-sm atom-btn-white"><i class="fas fa-sync-alt"></i></a>
                  <a href="#" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i></a>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-server fa-2x d-block mb-2"></i>No replication targets configured.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <a href="{{ route('settings.index') }}" class="btn atom-btn-white">Back to Settings</a>
@endsection
