@extends('theme::layouts.1col')
@section('title', __('Storage containers'))
@section('content')
<div class="container-fluid">
  <h1 class="h4 mb-3"><i class="fas fa-box me-2"></i>{{ __('Storage containers') }}</h1>

  @if($containers->isEmpty())
    <div class="alert alert-info">{{ __('No storage containers recorded.') }}</div>
  @else
  <form method="POST" action="{{ route('ahglabel.container.labels') }}" target="_blank">
    @csrf
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead><tr>
          <th style="width:32px"></th>
          <th>{{ __('Container') }}</th>
          <th>{{ __('Type') }}</th>
          <th>{{ __('Location') }}</th>
          <th class="text-end">{{ __('Holdings') }}</th>
        </tr></thead>
        <tbody>
        @foreach($containers as $c)
          <tr>
            <td><input class="form-check-input" type="checkbox" name="ids[]" value="{{ $c->id }}"></td>
            <td><a href="{{ route('ahglabel.container', ['id' => $c->id]) }}">{{ $c->name ?: __('(unnamed)') }}</a></td>
            <td>{{ $c->type_name ?: '-' }}</td>
            <td>{{ $c->location ?: '-' }}</td>
            {{-- An empty box is worth seeing at a glance: it is either a mistake
                 or a box waiting to be filled, and both are actionable. --}}
            <td class="text-end">{{ $c->holdings ?: 0 }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <button type="submit" class="btn atom-btn-secondary btn-sm">
      <i class="fas fa-qrcode me-1"></i>{{ __('Print labels with QR for selected') }}
    </button>
  </form>
  @endif
</div>
@endsection
