@extends('theme::layouts.1col')

@section('title', __('Article 30 - Record of Processing Activities'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="d-flex align-items-center">
    <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to privacy dashboard') }}">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="h2 mb-0">
      <i class="fas fa-clipboard-list me-2"></i>{{ __('Article 30 - Record of Processing Activities') }}
    </h1>
  </div>
  <div>
    <a href="{{ route('ahgprivacy.article-30.create') }}" class="btn btn-primary">
      <i class="fas fa-plus me-1"></i>{{ __('Add activity') }}
    </a>
    <a href="{{ route('ahgprivacy.autopilot') }}" class="btn btn-primary ms-2" title="{{ __('Scan the catalogue for personal data and auto-draft a ROPA entry') }}">
      <i class="fas fa-robot me-1"></i>{{ __('Compliance Autopilot') }}
    </a>
    <div class="btn-group ms-2" role="group">
      <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-download me-1"></i>{{ __('Export') }}
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ route('ahgprivacy.article-30.export', ['format' => 'json']) }}">JSON</a></li>
        <li><a class="dropdown-item" href="{{ route('ahgprivacy.article-30.export', ['format' => 'csv']) }}">CSV</a></li>
        <li><a class="dropdown-item" href="{{ route('ahgprivacy.article-30.export', ['format' => 'markdown']) }}">Markdown</a></li>
      </ul>
    </div>
  </div>
</div>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Activity') }}</th>
          <th>{{ __('Lawful basis') }}</th>
          <th>{{ __('Retention') }}</th>
          <th>{{ __('Cross-border?') }}</th>
          <th>{{ __('DPIA') }}</th>
          <th>{{ __('Status') }}</th>
          <th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($activities as $a)
          <tr>
            <td>
              <div class="fw-semibold">{{ $a->name }}</div>
              <div class="text-muted small">{{ \Illuminate\Support\Str::limit($a->purpose, 120) }}</div>
            </td>
            <td><code class="small">{{ $a->lawful_basis }}</code></td>
            <td>{{ $a->retention_period ?: '-' }}</td>
            <td>
              @if ($a->transfers_outside_eea)
                <span class="badge bg-warning text-dark">{{ __('yes') }}</span>
              @else
                <span class="badge bg-light text-dark">{{ __('no') }}</span>
              @endif
            </td>
            <td>
              @if ($a->dpia_required && $a->dpia_completed)
                <span class="badge bg-success" title="{{ $a->dpia_date ? $a->dpia_date->format('Y-m-d') : '' }}">{{ __('completed') }}</span>
              @elseif ($a->dpia_required)
                <span class="badge bg-danger">{{ __('required') }}</span>
              @else
                <span class="badge bg-light text-dark">{{ __('n/a') }}</span>
              @endif
            </td>
            <td>
              @if ($a->is_active)
                <span class="badge bg-success">{{ __('active') }}</span>
              @else
                <span class="badge bg-secondary">{{ __('inactive') }}</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('ahgprivacy.article-30.edit', ['id' => $a->id]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-pen"></i>
              </a>
              <form method="POST" action="{{ route('ahgprivacy.article-30.destroy', ['id' => $a->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Deactivate this activity?') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  <i class="fas fa-times"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="text-center text-muted p-4">
              {{ __('No processing activities registered yet.') }}
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="small text-muted mt-3 mb-0">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('GDPR Article 30 requires every controller to maintain a written record of all processing activities. Export this register as JSON, CSV or Markdown for regulator submissions.') }}
</p>
@endsection
