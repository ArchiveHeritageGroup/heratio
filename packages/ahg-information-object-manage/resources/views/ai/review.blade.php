@extends('theme::layouts.1col')
@section('title', 'NER Review Dashboard')

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-tasks',
    'featureTitle' => 'NER Review Dashboard',
    'featureDescription' => 'Review and approve extracted entities',
  ])

  @if($pending->isEmpty())
    <div class="alert alert-success">
      <i class="fas fa-check-circle me-1"></i> No extractions pending review.
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Object</th>
            <th>Entities found</th>
            <th>Status</th>
            <th>Extracted</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pending as $item)
            <tr>
              <td>{{ $item->object_id ?? '—' }}</td>
              <td>{{ $item->entity_count ?? '—' }}</td>
              <td><span class="badge bg-warning">{{ $item->status ?? 'pending' }}</span></td>
              <td>{{ $item->created_at ?? '—' }}</td>
              <td><a href="#" class="btn btn-sm btn-outline-primary">Review</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
