@extends('theme::layouts.1col')

@section('title', 'Expiring Embargoes')
@section('body-class', 'extended-rights expiring-embargoes')

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-clock me-2"></i>Expiring Embargoes</h1>
@endsection

@section('content')
@php $days = $days ?? 30; $embargoes = $embargoes ?? []; @endphp

<div class="card">
  <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
    <h4 class="mb-0">Embargoes Expiring Within {{ $days }} Days</h4>
    <div class="btn-group btn-group-sm">
      <a href="{{ route('extended-rights.expiring-embargoes', ['days' => 7]) }}" class="btn {{ $days == 7 ? 'atom-btn-white' : 'btn-outline-dark' }}">7 days</a>
      <a href="{{ route('extended-rights.expiring-embargoes', ['days' => 30]) }}" class="btn {{ $days == 30 ? 'atom-btn-white' : 'btn-outline-dark' }}">30 days</a>
      <a href="{{ route('extended-rights.expiring-embargoes', ['days' => 90]) }}" class="btn {{ $days == 90 ? 'atom-btn-white' : 'btn-outline-dark' }}">90 days</a>
    </div>
  </div>
  <div class="card-body p-0">
    @if(empty($embargoes))
      <div class="alert alert-success m-3"><i class="fas fa-check-circle me-2"></i>No embargoes expiring within the next {{ $days }} days.</div>
    @else
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
              <th>Title</th><th>Expiry Date</th><th>Days Remaining</th><th>Restriction</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($embargoes as $embargo)
            @php
              $embargo = (object) $embargo;
              $daysRemaining = (int) ($embargo->days_remaining ?? 0);
              $urgencyClass = $daysRemaining <= 7 ? 'table-danger' : ($daysRemaining <= 14 ? 'table-warning' : '');
            @endphp
            <tr class="{{ $urgencyClass }}">
              <td>
                <a href="{{ route('informationobject.show', $embargo->slug ?? $embargo->object_id) }}">{{ $embargo->title ?? 'Untitled' }}</a>
              </td>
              <td>{{ $embargo->end_date ?? '' }}</td>
              <td>
                @if($daysRemaining <= 7)
                  <span class="badge bg-danger">{{ $daysRemaining }} days</span>
                @elseif($daysRemaining <= 14)
                  <span class="badge bg-warning text-dark">{{ $daysRemaining }} days</span>
                @else
                  <span class="badge bg-info">{{ $daysRemaining }} days</span>
                @endif
              </td>
              <td>{{ ($embargo->embargo_type ?? '') . ' - ' . ($embargo->reason ?? '-') }}</td>
              <td>
                <a href="{{ route('extended-rights.lift-embargo', $embargo->id) }}" class="btn btn-sm btn-outline-success" title="Lift Embargo"><i class="fas fa-unlock"></i></a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
  <div class="card-footer text-muted">Total: {{ count($embargoes) }} embargoes</div>
</div>
@endsection
