@extends('theme::layouts.1col')

@section('title', __('Data Protection Impact Assessments'))

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div class="d-flex align-items-center">
    <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary btn-sm me-3" title="{{ __('Back to privacy dashboard') }}">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="h2 mb-0">
      <i class="fas fa-shield-alt me-2"></i>{{ __('Data Protection Impact Assessments') }}
    </h1>
  </div>
  <a href="{{ route('ahgprivacy.dpia.create') }}" class="btn btn-primary">
    <i class="fas fa-plus me-1"></i>{{ __('Start DPIA') }}
  </a>
</div>

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Name') }}</th>
          <th>{{ __('Status') }}</th>
          <th>{{ __('Linked activity') }}</th>
          <th>{{ __('DPO consulted') }}</th>
          <th>{{ __('Signed off') }}</th>
          <th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($dpias as $d)
          <tr>
            <td>
              <div class="fw-semibold">{{ $d->name }}</div>
              <div class="text-muted small">{{ \Illuminate\Support\Str::limit($d->description ?? '', 100) }}</div>
            </td>
            <td>
              @php
                $badge = match ($d->status) {
                  'draft' => 'bg-secondary',
                  'review' => 'bg-info text-dark',
                  'completed' => 'bg-success',
                  'archived' => 'bg-dark',
                  default => 'bg-light text-dark',
                };
              @endphp
              <span class="badge {{ $badge }}">{{ $d->status }}</span>
            </td>
            <td>
              @if ($d->processing_activity_id)
                <a href="{{ route('ahgprivacy.article-30.edit', ['id' => $d->processing_activity_id]) }}">#{{ $d->processing_activity_id }}</a>
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
            <td>{{ optional($d->dpo_consulted_at)->format('Y-m-d') ?? '-' }}</td>
            <td>{{ optional($d->signed_off_at)->format('Y-m-d') ?? '-' }}</td>
            <td class="text-end">
              <a href="{{ route('ahgprivacy.dpia.edit', ['id' => $d->id]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-pen"></i>
              </a>
              @if ($d->status === 'completed')
                <form method="POST" action="{{ route('ahgprivacy.dpia.archive', ['id' => $d->id]) }}" class="d-inline" onsubmit="return confirm('{{ __('Archive this DPIA?') }}');">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-dark">
                    <i class="fas fa-archive"></i>
                  </button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted p-4">
              {{ __('No DPIAs yet. Start one when launching high-risk processing.') }}
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="small text-muted mt-3 mb-0">
  <i class="fas fa-info-circle me-1"></i>
  {{ __('GDPR Article 35 requires a DPIA for processing likely to result in a high risk to the rights and freedoms of natural persons. Sign-off is recorded in the tamper-evident audit chain.') }}
</p>
@endsection
