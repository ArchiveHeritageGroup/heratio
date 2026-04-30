@extends('theme::layouts.2col')

@section('title', 'Orphan Works Due Diligence')
@section('body-class', 'admin rights-admin orphan-works')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0"><i class="fas fa-search me-2"></i>Orphan Works Due Diligence</h1>
    <a href="{{ route('ext-rights-admin.orphan-work-new') }}" class="btn btn-sm btn-primary">
      <i class="fas fa-plus me-1"></i> {{ __('New Search') }}
    </a>
  </div>
@endsection

@section('content')
  {{-- Status Filter --}}
  <div class="card mb-4">
    <div class="card-body py-2">
      <div class="btn-group" role="group">
        <a href="{{ route('ext-rights-admin.orphan-works', ['status' => 'all']) }}"
           class="btn btn-{{ ($status ?? 'all') === 'all' ? 'dark' : 'outline-dark' }}">All</a>
        <a href="{{ route('ext-rights-admin.orphan-works', ['status' => 'in_progress']) }}"
           class="btn btn-{{ ($status ?? '') === 'in_progress' ? 'warning' : 'outline-warning' }}">In Progress</a>
        <a href="{{ route('ext-rights-admin.orphan-works', ['status' => 'completed']) }}"
           class="btn btn-{{ ($status ?? '') === 'completed' ? 'success' : 'outline-success' }}">Completed</a>
        <a href="{{ route('ext-rights-admin.orphan-works', ['status' => 'rights_holder_found']) }}"
           class="btn btn-{{ ($status ?? '') === 'rights_holder_found' ? 'info' : 'outline-info' }}">Rights Holder Found</a>
      </div>
    </div>
  </div>

  {{-- Orphan Works Table --}}
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Object') }}</th>
            <th>{{ __('Work Type') }}</th>
            <th>{{ __('Search Started') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Search Steps') }}</th>
            <th>{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($orphanWorks as $work)
          <tr>
            <td>
              <a href="{{ $work->slug ? url($work->slug) : '#' }}">
                {{ $work->object_title ?: 'ID: ' . $work->object_id }}
              </a>
            </td>
            <td>{{ ucfirst(str_replace('_', ' ', $work->work_type ?? '')) }}</td>
            <td>{{ $work->search_started_date ? \Carbon\Carbon::parse($work->search_started_date)->format('d M Y') : '-' }}</td>
            <td>
              @php
                $statusColor = match($work->status ?? '') {
                  'in_progress' => 'warning', 'completed' => 'success', 'rights_holder_found' => 'info', 'abandoned' => 'secondary', default => 'light'
                };
              @endphp
              <span class="badge bg-{{ $statusColor }}">{{ ucfirst(str_replace('_', ' ', $work->status ?? '')) }}</span>
            </td>
            <td>
              @php
                $stepCount = \Illuminate\Support\Facades\Schema::hasTable('rights_orphan_search_step')
                    ? \Illuminate\Support\Facades\DB::table('rights_orphan_search_step')->where('orphan_work_id', $work->id)->count() : 0;
              @endphp
              <span class="badge bg-secondary">{{ $stepCount }}</span>
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <a href="{{ route('ext-rights-admin.orphan-work-edit', $work->id) }}" class="btn btn-outline-secondary" title="{{ __('View/Edit') }}">
                  <i class="fas fa-edit"></i>
                </a>
                @if(($work->status ?? '') === 'in_progress')
                <a href="{{ route('ext-rights-admin.complete-orphan-search', $work->id) }}" class="btn btn-outline-success" title="{{ __('Mark Complete') }}"
                   onclick="return confirm('Mark this search as complete?');">
                  <i class="fas fa-check"></i>
                </a>
                @endif
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-4">No orphan work searches found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Info Panel --}}
  <div class="card mt-4">
    <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
      <h5 class="mb-0">{{ __('About Orphan Works') }}</h5>
    </div>
    <div class="card-body">
      <p>Orphan works are copyrighted works whose rights holders cannot be identified or located after a diligent search.
      Before using an orphan work, institutions should conduct and document a thorough due diligence search.</p>

      <h6>{{ __('Recommended Search Sources:') }}</h6>
      <ul>
        <li>Copyright registries and databases</li>
        <li>Author/artist societies and collecting organizations</li>
        <li>Publisher records and catalogs</li>
        <li>Library and archive catalogs</li>
        <li>Internet searches</li>
        <li>Newspaper and publication archives</li>
      </ul>

      <p class="text-muted mb-0">
        <i class="fas fa-info-circle me-1"></i>
        Document each search step thoroughly, including negative results. This documentation may be required to demonstrate due diligence.
      </p>
    </div>
  </div>
@endsection
