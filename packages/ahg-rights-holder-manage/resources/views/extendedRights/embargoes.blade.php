@extends('theme::layouts.1col')

@section('title', 'Embargo Management')
@section('body-class', 'extended-rights embargoes')

@section('title-block')
  <h1 class="mb-0">Embargo Management</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Active Embargoes</h5>
  </div>
  <div class="card-body">
    @if(!empty($embargoes) && count($embargoes) > 0)
      <table class="table table-striped table-hover">
        <thead>
          <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <th>Title</th><th>Type</th><th>Start Date</th><th>End Date</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($embargoes as $embargo)
            <tr>
              <td>
                @if(!empty($embargo->slug))
                  <a href="{{ route('informationobject.show', $embargo->slug) }}">{{ $embargo->title ?? 'Untitled' }}</a>
                @else
                  {{ $embargo->title ?? 'Untitled' }}
                @endif
              </td>
              <td>
                <span class="badge bg-{{ ($embargo->embargo_type ?? 'full') === 'full' ? 'danger' : 'warning' }}">
                  {{ ucfirst($embargo->embargo_type ?? 'full') }}
                </span>
              </td>
              <td>{{ $embargo->start_date ?? '-' }}</td>
              <td>
                @if(!empty($embargo->end_date))
                  {{ $embargo->end_date }}
                @else
                  <span class="text-muted">Indefinite</span>
                @endif
              </td>
              <td>
                <a href="{{ route('extended-rights.lift-embargo', $embargo->id) }}" class="btn btn-sm atom-btn-white" onclick="return confirm('Are you sure you want to lift this embargo?');">
                  <i class="fas fa-unlock"></i> Lift
                </a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="alert alert-info"><i class="fas fa-info-circle"></i> No active embargoes found.</div>
    @endif
  </div>
</div>

<div class="mt-3">
  <a href="{{ route('extended-rights.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> Back to Extended Rights</a>
</div>
@endsection
