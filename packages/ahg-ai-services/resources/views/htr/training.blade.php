@extends('theme::layouts.1col')
@section('title', 'HTR Model Training')
@section('body-class', 'admin ai-services htr')
@section('content')
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('admin.ai.index') }}">AI Services</a></li><li class="breadcrumb-item"><a href="{{ route('admin.ai.htr.dashboard') }}">HTR</a></li><li class="breadcrumb-item active">Training</li></ol></nav>
@include('ahg-ai-services::htr._nav')
<h1><i class="fas fa-graduation-cap me-2"></i>HTR Model Training</h1>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-4">
  <div class="card-header" style="background: var(--ahg-primary); color: white;">Annotation Counts</div>
  <div class="card-body table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>Document Type</th><th>Annotations</th><th>Minimum Required</th><th>Status</th></tr></thead>
      <tbody>
      @php
        $types = ['type_a' => 'Type A — Death Certificate', 'type_b' => 'Type B — Register', 'type_c' => 'Type C — Narrative'];
        $canTrain = true;
      @endphp
      @foreach($types as $key => $label)
        @php $count = $status['counts'][$key] ?? 0; $ready = $count >= 50; if(!$ready) $canTrain = false; @endphp
        <tr>
          <td>{{ $label }}</td>
          <td>{{ $count }}</td>
          <td>50</td>
          <td><span class="badge {{ $ready ? 'bg-success' : 'bg-warning' }}">{{ $ready ? 'Ready' : 'Need ' . (50 - $count) . ' more' }}</span></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>

@if(isset($status['training_active']) && $status['training_active'])
<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Training is in progress... {{ $status['training_progress'] ?? '' }}</div>
@endif

<div class="d-flex gap-2">
  <form method="POST" action="{{ route('admin.ai.htr.startTraining') }}">@csrf
    <button type="submit" class="btn atom-btn-outline-success" {{ $canTrain ? '' : 'disabled' }}><i class="fas fa-play me-1"></i>Start Fine-tuning</button>
  </form>
  <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white"><i class="fas fa-pen me-1"></i>Add Annotations</a>
</div>
@endsection
