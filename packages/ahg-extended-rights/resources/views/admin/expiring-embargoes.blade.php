@extends('ahg-theme-b5::layout')

@section('title', 'Expiring Embargoes')

@section('content')
<div class="container-fluid mt-3">
  @include('ahg-extended-rights::admin._sidebar')

  <h1><i class="fas fa-clock"></i> {{ __('Expiring Embargoes') }}</h1>

  <form method="GET" class="mb-3">
    <div class="row">
      <div class="col-md-3">
        <select name="days" class="form-select" onchange="this.form.submit()">
          <option value="7" {{ ($days ?? 30) == 7 ? 'selected' : '' }}>{{ __('Next 7 days') }}</option>
          <option value="30" {{ ($days ?? 30) == 30 ? 'selected' : '' }}>{{ __('Next 30 days') }}</option>
          <option value="60" {{ ($days ?? 30) == 60 ? 'selected' : '' }}>{{ __('Next 60 days') }}</option>
          <option value="90" {{ ($days ?? 30) == 90 ? 'selected' : '' }}>{{ __('Next 90 days') }}</option>
        </select>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="card-body table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr><th>{{ __('Object') }}</th><th>{{ __('Type') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Days Left') }}</th><th>{{ __('Auto-Release') }}</th><th>{{ __('Actions') }}</th></tr>
        </thead>
        <tbody>
          @forelse($embargoes ?? [] as $emb)
          <tr class="{{ ($emb->days_remaining ?? 99) <= 7 ? 'table-danger' : (($emb->days_remaining ?? 99) <= 14 ? 'table-warning' : '') }}">
            <td>
              @if(!empty($emb->slug))
                <a href="/{{ $emb->slug }}">{{ e($emb->title ?? 'Untitled') }}</a>
              @else
                {{ e($emb->title ?? 'Object #' . ($emb->object_id ?? '')) }}
              @endif
            </td>
            <td><span class="badge bg-{{ $emb->embargo_type === 'full' ? 'danger' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $emb->embargo_type ?? '')) }}</span></td>
            <td>{{ $emb->end_date ?? '' }}</td>
            <td><strong>{{ $emb->days_remaining ?? '' }}</strong></td>
            <td>
              @if($emb->auto_release ?? false)
                <span class="badge bg-success">{{ __('Yes') }}</span>
              @else
                <span class="badge bg-secondary">No</span>
              @endif
            </td>
            <td>
              <a href="{{ route('ext-rights-admin.embargo-edit', ['id' => $emb->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
              <form method="POST" action="{{ route('ext-rights-admin.embargo-lift', ['id' => $emb->id]) }}" class="d-inline"
                    onsubmit="return confirm('Lift this embargo?')">
                @csrf
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-unlock"></i> {{ __('Lift') }}</button>
              </form>
              <form method="POST" action="{{ route('ext-rights-admin.embargo-extend', ['id' => $emb->id]) }}" class="d-inline">
                @csrf
                <input type="hidden" name="extend_days" value="30">
                <button class="btn btn-sm btn-outline-warning"><i class="fas fa-calendar-plus"></i> +30d</button>
              </form>
            </td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted">No embargoes expiring within {{ $days ?? 30 }} days.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
