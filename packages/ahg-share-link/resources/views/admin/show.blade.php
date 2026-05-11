@extends('theme::layout')

@section('title', __('Share link'))

@section('content')
@php
    $statusBadges = [
        'active'    => ['bg-success', __('Active')],
        'expired'   => ['bg-warning text-dark', __('Expired')],
        'revoked'   => ['bg-secondary', __('Revoked')],
        'exhausted' => ['bg-info text-dark', __('Exhausted')],
    ];
    [$badgeCls, $badgeLabel] = $statusBadges[$status] ?? ['bg-light text-dark', $status];

    $accessIcons = [
        'view'           => ['fa-check text-success',  __('Viewed')],
        'denied_expired' => ['fa-clock text-warning',  __('Denied — expired')],
        'denied_revoked' => ['fa-ban text-secondary',  __('Denied — revoked')],
        'denied_quota'   => ['fa-stop text-info',      __('Denied — quota exhausted')],
        'denied_unknown' => ['fa-question text-muted', __('Denied — unknown')],
    ];
@endphp

<div class="container-fluid py-3">
  <style>
    .sl-show .label { color: #6c757d; font-size: .85rem; }
    .sl-show .value { font-size: 1rem; }
    .sl-show code.token { word-break: break-all; }
    .sl-show .table-access td, .sl-show .table-access th { font-size: .9rem; }
  </style>

  <p>
    <a class="btn btn-outline-secondary btn-sm" href="{{ url('/admin/share-links') }}">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to list') }}
    </a>
  </p>

  <div class="d-flex justify-content-between align-items-center">
    <h2 class="mb-0">
      <i class="fas fa-share-alt me-1"></i>{{ __('Share link') }}
      <span class="badge {{ $badgeCls }} ms-2">{{ $badgeLabel }}</span>
    </h2>
    @if ($status === 'active' || $status === 'exhausted')
      <form action="{{ route('share-link.admin.revoke', ['id' => $tokenRow->id]) }}" method="post"
            onsubmit="return confirm('{{ __('Revoke this share link? Recipients will no longer be able to view the record.') }}');">
        @csrf
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="fas fa-ban me-1"></i>{{ __('Revoke') }}
        </button>
      </form>
    @endif
  </div>

  @if (session('success'))
    <div class="alert alert-success mt-2">{{ session('success') }}</div>
  @endif
  @if (session('info'))
    <div class="alert alert-info mt-2">{{ session('info') }}</div>
  @endif

  <div class="row sl-show">
    <div class="col-md-6">
      <p><span class="label">{{ __('Record') }}</span><br>
         <span class="value">{{ $ioTitle }}
           <small class="text-muted">#{{ (int) $tokenRow->information_object_id }}</small>
         </span></p>
      <p><span class="label">{{ __('Issuer') }}</span><br>
         <span class="value">{{ $issuerName }}
           @if ($issuerEmail)<small class="text-muted">({{ $issuerEmail }})</small>@endif
         </span></p>
      <p><span class="label">{{ __('Recipient') }}</span><br>
         <span class="value">
           @if ($tokenRow->recipient_email){{ $tokenRow->recipient_email }}@else<em class="text-muted">{{ __('Any holder of link') }}</em>@endif
         </span></p>
      @if (!empty($tokenRow->recipient_note))
        <p><span class="label">{{ __('Note') }}</span><br>
           <span class="value">{!! nl2br(e($tokenRow->recipient_note)) !!}</span></p>
      @endif
    </div>
    <div class="col-md-6">
      <p><span class="label">{{ __('Issued') }}</span><br><span class="value">{{ $tokenRow->created_at }}</span></p>
      <p><span class="label">{{ __('Expires') }}</span><br><span class="value">{{ $tokenRow->expires_at }}</span></p>
      <p><span class="label">{{ __('Visits') }}</span><br>
         <span class="value">{{ (int) $tokenRow->access_count }}@if ($tokenRow->max_access !== null) / {{ (int) $tokenRow->max_access }}@endif</span></p>
      @if (!empty($tokenRow->revoked_at))
        <p><span class="label">{{ __('Revoked at') }}</span><br><span class="value">{{ $tokenRow->revoked_at }}</span></p>
      @endif
      @if ($tokenRow->classification_level_at_issuance !== null)
        <p><span class="label">{{ __('Classification level at issuance') }}</span><br>
           <span class="value">{{ (int) $tokenRow->classification_level_at_issuance }}</span></p>
      @endif
    </div>
  </div>

  <h5 class="mt-3">{{ __('Public URL') }}</h5>
  <p><code class="token sl-show">{{ $publicUrl }}</code></p>

  <h5 class="mt-4">{{ sprintf(__('Access log (%d)'), count($accessLog)) }}</h5>
  @if (count($accessLog) === 0)
    <div class="alert alert-secondary">{{ __('No access attempts recorded yet.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-hover table-access sl-show">
        <thead>
          <tr>
            <th>{{ __('When') }}</th>
            <th>{{ __('Outcome') }}</th>
            <th>{{ __('IP') }}</th>
            <th>{{ __('User agent') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($accessLog as $a)
            @php [$icon, $label] = $accessIcons[$a->action] ?? ['fa-question', $a->action]; @endphp
            <tr>
              <td>{{ $a->accessed_at }}</td>
              <td><i class="fas {{ $icon }} me-1"></i>{{ $label }}</td>
              <td>{{ $a->ip_address ?? '—' }}</td>
              <td class="text-truncate" style="max-width:300px;">{{ $a->user_agent ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
