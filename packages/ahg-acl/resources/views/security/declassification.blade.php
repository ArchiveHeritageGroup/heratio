{{-- Declassification Management - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/declassificationSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Declassification Management')

@section('content')

<h1><i class="fas fa-unlock"></i> {{ __('Declassification Management') }}</h1>

{{-- Due Now --}}
@if(!empty($dueDeclassifications))
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning">
    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> {{ __('Due for Declassification') }}</h5>
  </div>
  <div class="card-body">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>{{ __('Object') }}</th>
          <th>{{ __('Current') }}</th>
          <th>{{ __('Downgrade To') }}</th>
          <th>{{ __('Scheduled') }}</th>
          <th>{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($dueDeclassifications as $dec)
        <tr>
          <td>
            <a href="{{ url('/' . $dec->object_id) }}">{{ e($dec->title ?? $dec->identifier ?? 'ID: ' . $dec->object_id) }}</a>
          </td>
          <td><span class="badge bg-danger">{{ e($dec->from_classification) }}</span></td>
          <td><span class="badge bg-success">{{ e($dec->to_classification ?? 'Public') }}</span></td>
          <td>{{ $dec->scheduled_date }}</td>
          <td>
            <form action="{{ route('acl.declassify-store') }}" method="post" style="display:inline">
              @csrf
              <input type="hidden" name="object_id" value="{{ $dec->object_id }}">
              <input type="hidden" name="new_classification_id" value="{{ $dec->to_classification_id ?? '' }}">
              <input type="hidden" name="reason" value="Scheduled declassification">
              <button type="submit" class="btn btn-sm btn-success">
                <i class="fas fa-check"></i> {{ __('Process') }}
              </button>
            </form>
            <a href="{{ route('acl.object-view', ['id' => $dec->object_id]) }}" class="btn btn-sm btn-outline-secondary">
              <i class="fas fa-eye"></i>
            </a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@else
<div class="alert alert-success mb-4">
  <i class="fas fa-check-circle"></i> {{ __('No declassifications currently due.') }}
</div>
@endif

{{-- Scheduled --}}
<div class="card">
  <div class="card-header">
    <h5 class="mb-0"><i class="fas fa-calendar"></i> {{ __('Scheduled Declassifications') }}</h5>
  </div>
  <div class="card-body">
    @if(empty($scheduled))
    <p class="text-muted text-center">No future declassifications scheduled.</p>
    @else
    <table class="table table-striped">
      <thead>
        <tr>
          <th>{{ __('Object') }}</th>
          <th>{{ __('Current') }}</th>
          <th>{{ __('Downgrade To') }}</th>
          <th>{{ __('Scheduled Date') }}</th>
          <th>{{ __('Days Until') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($scheduled as $item)
        @php $daysUntil = (strtotime($item->scheduled_date) - time()) / 86400; @endphp
        <tr class="{{ $daysUntil <= 30 ? 'table-warning' : '' }}">
          <td>
            <a href="{{ url('/' . $item->object_id) }}">{{ e($item->title ?? 'ID: ' . $item->object_id) }}</a>
          </td>
          <td>{{ e($item->from_name ?? '') }}</td>
          <td>{{ e($item->to_name ?? 'Public') }}</td>
          <td>{{ $item->scheduled_date }}</td>
          <td>{{ round($daysUntil) }} days</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

@endsection
