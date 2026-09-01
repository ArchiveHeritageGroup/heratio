@extends('theme::layouts.1col')
@section('title', __('Stratigraphic Consistency'))
@section('content')
<div class="container py-4">

  <nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('archaeology.site', $site->id) }}">{{ __('Site') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('archaeology.contexts', $site->id) }}">{{ __('Stratigraphy') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Consistency') }}</li>
  </ol></nav>

  <h1 class="h3 mb-3"><i class="fas fa-vials me-2"></i>{{ __('Stratigraphic Consistency') }}</h1>

  <p class="text-secondary">
    {{ __('Cycle detection alone only catches the error that makes a matrix impossible to draw. These checks look for the errors that leave a drawable matrix which is wrong.') }}
  </p>

  @if(empty($findings))
    <div class="alert alert-success">
      <i class="fas fa-check-circle me-2"></i>
      {{ __('Nothing contradictory found in the recorded sequence.') }}
    </div>
  @else
    <div class="card mb-4">
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead><tr><th>{{ __('Severity') }}</th><th>{{ __('Finding') }}</th></tr></thead>
          <tbody>
            @foreach($findings as $f)
              <tr>
                <td class="text-nowrap">
                  <span class="badge bg-{{ $f['severity'] === 'error' ? 'danger' : 'warning text-dark' }}">
                    {{ ucfirst($f['severity']) }}
                  </span>
                  <br><small class="text-muted">{{ $f['kind'] }}</small>
                </td>
                <td>{{ $f['message'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- What was checked matters as much as what was found: a clean report means
       nothing unless the reader knows its scope. --}}
  <div class="card">
    <div class="card-header">{{ __('Checks run') }}</div>
    <div class="card-body">
      <ul class="mb-0">
        @foreach($checked as $c)<li>{{ $c }}</li>@endforeach
      </ul>
      <p class="text-muted small mb-0 mt-2">
        {{ __('Every check is conservative and reports only what the record makes unambiguous. Ordinary excavation messiness is left alone deliberately.') }}
      </p>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2 flex-wrap">
    <a class="btn btn-sm atom-btn-white" href="{{ route('harris.export.dot', $site->id) }}">
      <i class="fas fa-project-diagram me-1"></i>{{ __('Export GraphViz DOT') }}
    </a>
    <a class="btn btn-sm atom-btn-white" href="{{ route('harris.export.datapackage', $site->id) }}">
      <i class="fas fa-box me-1"></i>{{ __('Export Data Package') }}
    </a>
    <a class="btn btn-sm atom-btn-white" href="{{ route('harris.export.phaser', $site->id) }}">
      <i class="fas fa-file-csv me-1"></i>{{ __('Export relationships CSV') }}
    </a>
    <a class="btn btn-sm atom-btn-white" href="{{ route('harris.import.lst', $site->id) }}">
      <i class="fas fa-file-import me-1"></i>{{ __('Import LST') }}
    </a>
    <a class="btn btn-sm atom-btn-white" href="{{ route('harris.import.relationships', $site->id) }}">
      <i class="fas fa-diagram-project me-1"></i>{{ __('Import relationships CSV') }}
    </a>
  </div>

</div>
@endsection
