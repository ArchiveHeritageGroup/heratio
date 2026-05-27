{{--
  inbox.blade.php - curator inbox for publish requests (#745).
  Admin-only. Filterable by status tab. Renders status counts as badges.
--}}
@extends('theme::layouts.1col')

@section('title', __('Publish Requests Inbox'))
@section('body-class', 'publish-request inbox')

@section('content')
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-inbox fa-2x text-primary me-3" aria-hidden="true"></i>
    <div>
      <h1 class="h3 mb-0">{{ __('Publish Requests Inbox') }}</h1>
      <p class="text-muted mb-0">{{ __('Anonymous research requests pending curator review.') }}</p>
    </div>
  </div>

  @if(empty($tableExists))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      {{ __('Publish-request table not configured. Run the ServiceProvider boot or install_publish_request.sql.') }}
    </div>
    @return
  @endif

  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white p-0">
      <ul class="nav nav-tabs card-header-tabs" role="tablist">
        @foreach(['all','pending','approved','rejected','edited'] as $tab)
          <li class="nav-item">
            <a class="nav-link {{ $status === $tab ? 'active' : '' }}"
               href="{{ route('publish-requests.inbox', ['status' => $tab]) }}">
              {{ ucfirst($tab) }}
              <span class="badge bg-secondary ms-1">{{ (int) ($counts[$tab] ?? 0) }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="card-body p-0">
      @if(count($rows) > 0)
        <div class="table-responsive">
          <table class="table table-bordered table-hover table-striped mb-0">
            <thead>
              <tr>
                <th style="width: 110px;">{{ __('Status') }}</th>
                <th>{{ __('Archival Item') }}</th>
                <th>{{ __('Submitter') }}</th>
                <th>{{ __('Submitted') }}</th>
                <th>{{ __('Decided') }}</th>
                <th style="width: 80px;">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $r)
                @php
                  $badge = match($r->status) {
                    'approved' => 'bg-success',
                    'rejected' => 'bg-danger',
                    'edited'   => 'bg-info text-dark',
                    default    => 'bg-warning text-dark',
                  };
                @endphp
                <tr>
                  <td><span class="badge {{ $badge }}">{{ ucfirst($r->status) }}</span></td>
                  <td>
                    @if(!empty($r->object_title))
                      <a href="/{{ $r->object_slug ?? '' }}">{{ $r->object_title }}</a>
                    @elseif(!empty($r->information_object_id))
                      <span class="text-muted">#{{ $r->information_object_id }}</span>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td>
                    <strong>{{ $r->submitter_name ?: '-' }}</strong><br>
                    <small class="text-muted">{{ $r->submitter_email }}</small>
                  </td>
                  <td>
                    @if(!empty($r->created_at))
                      <small>{{ \Carbon\Carbon::parse($r->created_at)->format('d M Y H:i') }}</small>
                    @endif
                  </td>
                  <td>
                    @if(!empty($r->decided_at))
                      <small>{{ \Carbon\Carbon::parse($r->decided_at)->format('d M Y H:i') }}</small>
                    @else
                      <span class="text-muted">-</span>
                    @endif
                  </td>
                  <td class="text-center">
                    <a href="{{ route('publish-requests.edit', $r->id) }}"
                       class="btn btn-sm btn-outline-primary" title="{{ __('Review') }}">
                      <i class="fas fa-eye"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="text-center py-5">
          <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">{{ __('Inbox empty') }}</h5>
          <p class="text-muted mb-0">{{ __('No publish requests match this filter.') }}</p>
        </div>
      @endif
    </div>
  </div>
@endsection
