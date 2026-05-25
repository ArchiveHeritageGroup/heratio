{{--
  Heratio - AI model registry index (Article 11 / Annex IV).
  Copyright (c) 2026 Plain Sailing Information Systems.
  Johan Pieterse <johan@plainsailingisystems.co.za>. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')
@section('title', 'AI Model Registry')
@section('body-class', 'admin ai-compliance')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0"><i class="fas fa-microchip me-2"></i>{{ __('AI Model Registry') }}</h1>
      <div>
        <a href="{{ route('ai-compliance.documentation.index') }}" class="btn atom-btn-white me-2"><i class="fas fa-file-contract me-1"></i>{{ __('Annex IV documentation') }}</a>
        <a href="{{ route('ai-compliance.models.create') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Add model') }}</a>
      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>
      {{ __('Per EU AI Act Article 11 / Annex IV, every AI service must have a current model registry entry. Retire (do not delete) a model by setting its retired-at date, then add a new row for its replacement.') }}
    </div>

    @if($models->isEmpty())
      <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        {{ __('No model registry entries. Run') }} <code>php artisan ai-compliance:annex-iv</code> {{ __('after seeding, or click "Add model" to begin.') }}
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-dark">
            <tr>
              <th>{{ __('Service') }}</th>
              <th>{{ __('Model ID') }}</th>
              <th>{{ __('Version') }}</th>
              <th>{{ __('Deployed') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($models as $model)
              <tr>
                <td><span class="badge bg-secondary">{{ $model->service }}</span></td>
                <td><code>{{ $model->model_id }}</code></td>
                <td><code>{{ $model->model_version }}</code></td>
                <td>{{ $model->deployed_at?->format('Y-m-d') }}</td>
                <td>
                  @if($model->retired_at)
                    <span class="badge bg-warning text-dark">{{ __('Retired') }} {{ $model->retired_at->format('Y-m-d') }}</span>
                  @else
                    <span class="badge bg-success">{{ __('Active') }}</span>
                  @endif
                </td>
                <td>
                  <a href="{{ route('ai-compliance.models.edit', $model->id) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i> {{ __('Edit') }}</a>
                  <form method="post" action="{{ route('ai-compliance.models.destroy', $model->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this model registry entry? Retiring (setting retired-at) is usually preferable for the audit trail.') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm atom-btn-outline-danger"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
