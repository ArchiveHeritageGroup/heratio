@extends('theme::layouts.1col')
@section('title', 'HTR Model Training')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Training</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-graduation-cap me-2"></i>HTR Model Training</h1>

<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Annotation Counts</div>
  <div class="card-body table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>{{ __('Document Type') }}</th><th>{{ __('Annotations') }}</th><th>{{ __('Minimum Required') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
      @php
        $types = ['type_a' => 'Type A — Death Certificate', 'type_b' => 'Type B — Register', 'type_c' => 'Type C — Narrative'];
        $canTrain = false;
        $totalAnnotations = 0;
      @endphp
      @foreach($types as $key => $label)
        @php
          $count = $status['counts'][$key] ?? 0;
          $totalAnnotations += $count;
          $ready = $count >= 50;
          if($ready) $canTrain = true;
        @endphp
        <tr>
          <td>{{ $label }}</td>
          <td><strong>{{ $count }}</strong></td>
          <td>50</td>
          <td><span class="badge {{ $ready ? 'bg-success' : ($count > 0 ? 'bg-info' : 'bg-warning') }}">{{ $ready ? 'Ready' : ($count > 0 ? $count . ' / 50' : 'Need 50') }}</span></td>
        </tr>
      @endforeach
      <tr class="table-light"><td><strong>{{ __('Total') }}</strong></td><td><strong>{{ $totalAnnotations }}</strong></td><td></td><td></td></tr>
      </tbody>
    </table>
  </div>
</div>

<div id="training-status-panel">
@if(isset($status['training_active']) && $status['training_active'])
<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Training is in progress... <span id="training-progress">{{ $status['training_progress'] ?? '' }}</span></div>
@else
<div class="alert alert-secondary"><i class="fas fa-circle me-2"></i>No training running.</div>
@endif
</div>

<div class="d-flex gap-2">
  <form method="POST" action="{{ route('admin.ai.htr.startTraining') }}">@csrf
    <button type="submit" class="btn atom-btn-outline-success" {{ $canTrain ? '' : 'disabled' }}><i class="fas fa-play me-1"></i>{{ __('Start Fine-tuning') }}</button>
  </form>
  <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white"><i class="fas fa-pen me-1"></i>{{ __('Annotate') }}</a>
  <a href="{{ route('admin.ai.htr.bulkAnnotate') }}" class="btn atom-btn-white"><i class="fas fa-magic me-1"></i>{{ __('Bulk Annotate') }}</a>
  <a href="{{ route('admin.ai.htr.fsOverlay') }}" class="btn atom-btn-white"><i class="fas fa-layer-group me-1"></i>{{ __('FS Overlay') }}</a>
</div>
@endsection

@push('js')
<script>
(function() {
    const panel = document.getElementById('training-status-panel');
    if (!panel) return;
    let polling = null;

    function poll() {
        fetch('{{ route("admin.ai.htr.trainingStatus") }}')
            .then(r => r.json())
            .then(data => {
                if (data.training_active) {
                    let info = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Training is in progress...';
                    if (data.started_at) info += ' <small class="text-muted">Started: ' + data.started_at + '</small>';
                    if (data.total) info += ' <small class="ms-2">(' + data.total + ' annotations)</small>';
                    info += '</div>';
                    panel.innerHTML = info;
                } else {
                    panel.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Training complete.</div>';
                    clearInterval(polling);
                }
            })
            .catch(() => {});
    }

    @if(isset($status['training_active']) && $status['training_active'])
        polling = setInterval(poll, 5000);
    @else
        // Check once in case training was started via API/CLI
        poll();
        polling = setInterval(poll, 10000);
    @endif
})();
</script>
@endpush
