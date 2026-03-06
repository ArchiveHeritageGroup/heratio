@extends('theme::layouts.1col')
@section('title', 'Extract Entities (NER) — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-brain',
    'featureTitle' => 'Extract Entities (NER)',
    'featureDescription' => 'Named Entity Recognition — extract persons, organizations, places, dates',
  ])

  @if(isset($io->scope_and_content) && $io->scope_and_content)
    <div class="card mb-3">
      <div class="card-header fw-bold">Source text</div>
      <div class="card-body">
        <p>{{ $io->scope_and_content }}</p>
      </div>
    </div>
  @endif

  @if($entities->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No entities extracted yet.
    </div>
    <button class="btn atom-btn-outline-success" id="run-ner-btn" data-object-id="{{ $io->id }}">
      <i class="fas fa-play me-1"></i> Run NER extraction
    </button>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Entity</th>
            <th>Type</th>
            <th>Confidence</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($entities as $entity)
            <tr>
              <td>{{ $entity->entity_text ?? '—' }}</td>
              <td><span class="badge bg-secondary">{{ $entity->entity_type ?? '—' }}</span></td>
              <td>{{ isset($entity->confidence) ? number_format($entity->confidence * 100, 1) . '%' : '—' }}</td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button class="btn btn-outline-success" title="Create access point"><i class="fas fa-plus"></i></button>
                  <button class="btn btn-outline-danger" title="Dismiss"><i class="fas fa-times"></i></button>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
