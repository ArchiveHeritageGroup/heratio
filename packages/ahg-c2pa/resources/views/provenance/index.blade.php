{{--
  Heratio - C2PA provenance record list for an information object (issue #1201).

  @copyright Copyright (c) 2026, Plain Sailing Information Systems
  @author    Johan Pieterse <johan@plainsailingisystems.co.za>
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Content Credentials & Provenance'))
@section('body-class', 'admin c2pa')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-fingerprint me-2"></i>{{ __('Content Credentials & Provenance') }}</h1>
  <div>
    <a href="{{ route('c2pa.provenance.create', ['informationObjectId' => $informationObjectId]) }}" class="btn btn-sm btn-primary">
      <i class="fas fa-plus me-1"></i>{{ __('Record digitisation') }}
    </a>
    @if(!empty($object?->slug))
      <a href="{{ url('/' . $object->slug) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to record') }}</a>
    @endif
  </div>
</div>

@if($object)
  <p class="text-muted">
    {{ __('Information object') }}:
    <strong>{{ $object->title ?? $object->identifier ?? ('#' . $object->id) }}</strong>
    <span class="badge bg-secondary ms-1">IO #{{ $object->id }}</span>
  </p>
@endif

@if(session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="alert {{ $capability['can_embed_media'] ? 'alert-info' : 'alert-warning' }}">
  <i class="fas fa-info-circle me-1"></i>
  <strong>{{ __('Signing status') }}:</strong> {{ $capability['summary'] }}
</div>

@if(empty($records))
  <div class="card"><div class="card-body text-center text-muted py-5">
    <i class="fas fa-fingerprint fa-2x mb-2 d-block"></i>
    {{ __('No provenance records yet. Record a digitisation to create a verifiable content-credentials manifest.') }}
  </div></div>
@else
  <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>{{ __('Captured by') }}</th>
          <th>{{ __('Device') }}</th>
          <th>{{ __('Captured at') }}</th>
          <th>{{ __('Signing') }}</th>
          <th>{{ __('Recorded') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach($records as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td>{{ $r->captured_by ?? '-' }}</td>
            <td>{{ $r->capture_device ?? '-' }}</td>
            <td>{{ $r->captured_at ?? '-' }}</td>
            <td>
              @if($r->sign_status === 'signed')
                <span class="badge bg-success"><i class="fas fa-certificate me-1"></i>{{ __('Signed') }}</span>
              @else
                <span class="badge bg-secondary">{{ __('Unsigned') }}</span>
              @endif
            </td>
            <td>{{ $r->created_at }}</td>
            <td class="text-end">
              <a href="{{ route('c2pa.provenance.show', ['informationObjectId' => $informationObjectId, 'provenanceId' => $r->id]) }}" class="btn btn-sm atom-btn-white">
                <i class="fas fa-shield-alt me-1"></i>{{ __('Verify') }}
              </a>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
