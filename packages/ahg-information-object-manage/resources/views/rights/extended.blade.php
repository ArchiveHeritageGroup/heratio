@extends('theme::layouts.1col')
@section('title', 'Extended Rights — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-copyright',
    'featureTitle' => 'Extended Rights',
    'featureDescription' => 'PREMIS rights, Creative Commons, RightsStatements.org',
  ])

  @if($rights->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> No rights statements recorded.
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-striped">
        <thead>
          <tr><th>Basis</th><th>Start</th><th>End</th><th>Notes</th></tr>
        </thead>
        <tbody>
          @foreach($rights as $right)
            <tr>
              <td>{{ $right->basis ?? '—' }}</td>
              <td>{{ $right->start_date ?? '—' }}</td>
              <td>{{ $right->end_date ?? '—' }}</td>
              <td>{{ $right->rights_note ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <a href="#" class="btn atom-btn-outline-success" onclick="alert('Extended rights form — migration in progress'); return false;">
    <i class="fas fa-plus me-1"></i> Add rights statement
  </a>
@endsection
