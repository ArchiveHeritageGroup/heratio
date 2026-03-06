@extends('theme::layouts.1col')
@section('title', 'Condition Assessment — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-clipboard-check',
    'featureTitle' => 'Condition Assessment',
    'featureDescription' => 'Physical condition reports and photo annotation',
  ])

  @if($checks->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No condition assessments recorded for this description.
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Date</th>
            <th>Assessor</th>
            <th>Condition</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($checks as $check)
            <tr>
              <td>{{ $check->check_date ?? '—' }}</td>
              <td>{{ $check->assessor ?? '—' }}</td>
              <td>{{ $check->condition_rating ?? '—' }}</td>
              <td>{{ \Illuminate\Support\Str::limit($check->notes ?? '', 80) }}</td>
              <td><a href="#" class="btn btn-sm btn-outline-secondary"><i class="fas fa-eye"></i></a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <a href="#" class="btn atom-btn-outline-success" onclick="alert('Condition assessment form — migration in progress'); return false;">
    <i class="fas fa-plus me-1"></i> New assessment
  </a>
@endsection
