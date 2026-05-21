@extends('theme::layouts.1col')

@section('title', __('AI Inventory & Governance'))

@section('content')
<div class="container py-4">

  <div class="mb-4">
    <h4 class="mb-1"><i class="fas fa-shield-alt me-2"></i>{{ __('AI Inventory & Governance') }}</h4>
    <p class="text-muted mb-0">{{ __('Operator visibility into configured LLMs and recent AI inference activity.') }}</p>
  </div>

  {{-- Stat cards --}}
  <div class="row g-3 mb-4">
    @php
      $cards = [
        ['label' => __('LLM configs'),        'value' => $stats['models_total'],     'icon' => 'fa-microchip'],
        ['label' => __('Active'),             'value' => $stats['models_active'],    'icon' => 'fa-check-circle'],
        ['label' => __('Inferences (total)'), 'value' => $stats['inferences_total'], 'icon' => 'fa-stream'],
        ['label' => __('Inferences (7 days)'),'value' => $stats['inferences_7d'],    'icon' => 'fa-calendar-week'],
        ['label' => __('Avg confidence'),
         'value' => $stats['avg_confidence'] !== null ? number_format(((float) $stats['avg_confidence']) * 100, 1) . '%' : '—',
         'icon'  => 'fa-percentage'],
      ];
    @endphp
    @foreach($cards as $c)
      <div class="col-6 col-md">
        <div class="card h-100">
          <div class="card-body text-center py-3">
            <i class="fas {{ $c['icon'] }} fa-lg text-muted mb-2"></i>
            <div class="fs-4 fw-bold">{{ $c['value'] }}</div>
            <div class="small text-muted">{{ $c['label'] }}</div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- LLM configurations --}}
  <div class="card mb-4">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
      <span><i class="fas fa-microchip me-1"></i>{{ __('LLM Configurations') }}</span>
      <span class="badge bg-secondary">{{ count($models) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>{{ __('Provider') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Model') }}</th>
            <th class="text-end">{{ __('Max tokens') }}</th>
            <th class="text-end">{{ __('Temp') }}</th>
            <th class="text-end">{{ __('Inferences') }}</th>
            <th>{{ __('Last used') }}</th>
            <th>{{ __('Manifest') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($models as $m)
            <tr>
              <td>{{ $m->provider }}</td>
              <td>
                {{ $m->name }}
                @if($m->is_default)<span class="badge bg-primary ms-1">{{ __('default') }}</span>@endif
                @if($m->is_active)
                  <span class="badge bg-success ms-1">{{ __('active') }}</span>
                @else
                  <span class="badge bg-secondary ms-1">{{ __('inactive') }}</span>
                @endif
              </td>
              <td><code>{{ $m->model }}</code></td>
              <td class="text-end">{{ $m->max_tokens }}</td>
              <td class="text-end">{{ $m->temperature }}</td>
              <td class="text-end">{{ $m->inference_count }}</td>
              <td class="small">{{ $m->last_used ? \Illuminate\Support\Str::limit((string) $m->last_used, 16, '') : '—' }}</td>
              <td class="small text-muted" title="{{ __('Pending heratio#135') }}">{{ $m->model_manifest ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-3">{{ __('No LLM configurations.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Recent inferences --}}
  <div class="card mb-3">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
      <span><i class="fas fa-stream me-1"></i>{{ __('Recent Inferences') }}</span>
      <span class="badge bg-secondary">{{ count($inferences) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>{{ __('When') }}</th>
            <th>{{ __('Service') }}</th>
            <th>{{ __('Model') }}</th>
            <th>{{ __('Target') }}</th>
            <th>{{ __('Field') }}</th>
            <th class="text-end">{{ __('Confidence') }}</th>
            <th class="text-end">{{ __('Elapsed') }}</th>
            <th>{{ __('Signed') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($inferences as $i)
            <tr>
              <td class="small">{{ $i->occurred_at ? \Illuminate\Support\Str::limit((string) $i->occurred_at, 16, '') : '—' }}</td>
              <td>{{ $i->service_name ?? '—' }}</td>
              <td><code>{{ $i->model_name ?? '—' }}</code>@if($i->model_version)<span class="text-muted small"> {{ $i->model_version }}</span>@endif</td>
              <td class="small">{{ $i->target_entity_type ? $i->target_entity_type . ' #' . $i->target_entity_id : '—' }}</td>
              <td class="small">{{ $i->target_field ?? '—' }}</td>
              <td class="text-end">
                @if($i->confidence !== null)
                  {{ number_format(((float) $i->confidence) * 100, 1) }}%
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end small">{{ $i->elapsed_ms !== null ? $i->elapsed_ms . ' ms' : '—' }}</td>
              <td>
                @if($i->signed)
                  <span class="badge bg-success">{{ __('signed') }}</span>
                @else
                  <span class="badge bg-light text-muted border" title="{{ __('Pending heratio#136') }}">{{ __('unsigned') }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-3">{{ __('No inference activity recorded yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <p class="text-muted small">
    <i class="fas fa-info-circle me-1"></i>{{ __('Model manifests (heratio#135) and Ed25519 inference signing (heratio#136) are not yet wired - those columns show a placeholder until then.') }}
  </p>

</div>
@endsection
