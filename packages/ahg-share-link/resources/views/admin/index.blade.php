@extends('theme::layout')

@section('title', __('Share links'))

@section('content')
@php
    $now = time();
    $nowStr = date('Y-m-d H:i:s');
    $statusOptions = [
        'active'    => __('Active'),
        'expired'   => __('Expired'),
        'revoked'   => __('Revoked'),
        'exhausted' => __('Exhausted'),
        'all'       => __('All'),
    ];
    $badgeFor = function ($row) use ($now) {
        if (!empty($row->revoked_at)) return ['bg-secondary', __('Revoked')];
        if (strtotime((string) $row->expires_at) <= $now) return ['bg-warning text-dark', __('Expired')];
        if ($row->max_access !== null && (int) $row->access_count >= (int) $row->max_access) return ['bg-info text-dark', __('Exhausted')];
        return ['bg-success', __('Active')];
    };
@endphp

<div class="container-fluid py-3">
  <style>
    .sl-admin .badge { font-weight: 500; }
    .sl-admin td.tok code { font-size: .85rem; }
    .sl-admin .meta { font-size: .85rem; color: #6c757d; }
  </style>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2>
      <i class="fas fa-share-alt me-1"></i>{{ __('Share links') }}
      <small class="text-muted">{{ sprintf(__('%d total'), $totalCount) }}</small>
    </h2>
  </div>

  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if (session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <form method="get" action="{{ url('/admin/share-links') }}" class="row g-2 mb-3 sl-admin">
    <div class="col-auto">
      <label class="form-label small mb-0">{{ __('Status') }}</label>
      <select name="status" class="form-select form-select-sm">
        @foreach ($statusOptions as $val => $label)
          <option value="{{ $val }}" @selected($filters['status'] === $val)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-0">{{ __('Issuer') }}</label>
      <select name="issuer" class="form-select form-select-sm">
        <option value="">{{ __('Any user') }}</option>
        @foreach ($issuers as $u)
          <option value="{{ (int) $u->issued_by }}" @selected((int) $filters['issuer'] === (int) $u->issued_by)>
            {{ $u->username ?? ('#' . $u->issued_by) }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="col-auto flex-grow-1">
      <label class="form-label small mb-0">{{ __('Search') }}</label>
      <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm"
             placeholder="{{ __('Token, email, or record title') }}">
    </div>
    <div class="col-auto align-self-end">
      <button type="submit" class="btn btn-primary btn-sm">
        <i class="fas fa-filter me-1"></i>{{ __('Filter') }}
      </button>
      <a href="{{ url('/admin/share-links') }}" class="btn btn-link btn-sm">{{ __('Reset') }}</a>
    </div>
  </form>

  @if ($totalCount === 0)
    <div class="alert alert-info">{{ __('No share links match the current filter.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-hover sl-admin">
        <thead>
          <tr>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Record') }}</th>
            <th>{{ __('Issuer') }}</th>
            <th>{{ __('Recipient') }}</th>
            <th>{{ __('Issued') }}</th>
            <th>{{ __('Expires') }}</th>
            <th>{{ __('Visits') }}</th>
            <th class="tok">{{ __('Token') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($tokens as $t)
            @php [$badgeCls, $badgeLabel] = $badgeFor($t); @endphp
            <tr>
              <td><span class="badge {{ $badgeCls }}">{{ $badgeLabel }}</span></td>
              <td>
                {{ $t->io_title ?? ('#' . $t->information_object_id) }}
                <div class="meta">#{{ (int) $t->information_object_id }}</div>
              </td>
              <td>{{ $t->issuer_username ?? ('#' . $t->issued_by) }}</td>
              <td>
                @if ($t->recipient_email){{ $t->recipient_email }}@else<span class="text-muted">—</span>@endif
              </td>
              <td><span class="meta">{{ $t->created_at }}</span></td>
              <td><span class="meta">{{ $t->expires_at }}</span></td>
              <td>
                {{ (int) $t->access_count }}@if ($t->max_access !== null) / {{ (int) $t->max_access }}@endif
              </td>
              <td class="tok"><code>{{ substr($t->token, 0, 12) }}…</code></td>
              <td class="text-end">
                <a href="{{ url('/admin/share-links/' . $t->id) }}" class="btn btn-outline-secondary btn-sm">
                  <i class="fas fa-eye me-1"></i>{{ __('View') }}
                </a>
                @if (empty($t->revoked_at) && strtotime((string) $t->expires_at) > $now)
                  <form action="{{ route('share-link.admin.revoke', ['id' => $t->id]) }}" method="post" class="d-inline ms-1"
                        onsubmit="return confirm('{{ __('Revoke this share link? Recipients will no longer be able to view the record.') }}');">
                    @csrf
                    <input type="hidden" name="back" value="{{ request()->fullUrl() }}">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                      <i class="fas fa-ban me-1"></i>{{ __('Revoke') }}
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    @if ($totalPages > 1)
      @php
        $linkFor = function ($p) use ($filters) {
          return url('/admin/share-links') . '?' . http_build_query([
            'page' => $p,
            'status' => $filters['status'],
            'q' => $filters['q'],
            'issuer' => $filters['issuer'],
          ]);
        };
        $start = max(1, $page - 4);
        $end   = min($totalPages, $page + 4);
      @endphp
      <nav>
        <ul class="pagination pagination-sm">
          <li class="page-item @if($page <= 1)disabled @endif">
            <a class="page-link" href="{{ $linkFor(max(1, $page - 1)) }}">«</a>
          </li>
          @for ($p = $start; $p <= $end; $p++)
            <li class="page-item @if($p === $page)active @endif">
              <a class="page-link" href="{{ $linkFor($p) }}">{{ $p }}</a>
            </li>
          @endfor
          <li class="page-item @if($page >= $totalPages)disabled @endif">
            <a class="page-link" href="{{ $linkFor(min($totalPages, $page + 1)) }}">»</a>
          </li>
        </ul>
      </nav>
    @endif
  @endif
</div>
@endsection
