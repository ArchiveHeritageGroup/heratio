{{--
  Endangered network - PUSH-MODEL peer inbound review queue (heratio#1205)

  At-risk flags pushed to this instance by federation peers (verified, from known
  members) land here 'pending'. A curator ACCEPTS one (it then appears on the
  cross-institution board) or DECLINES it. Neutral, provenance-first framing.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio. Distributed under the GNU AGPL v3 or later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Pushed at-risk flags'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
        <h1 class="h4 mb-0">{{ __('Pushed at-risk flags') }}</h1>
        <span class="badge bg-light text-dark border">{{ __('peer inbound') }}</span>
        <span class="ms-auto small text-muted">
            @foreach($statusCounts as $st => $c)
                <span class="badge text-bg-light border">{{ ucfirst($st) }}: {{ $c }}</span>
            @endforeach
        </span>
    </div>

    <p class="text-muted small">
        {{ __('Federation peers can push records they consider at risk to this instance. Each push is from a known federation member and is cryptographically signature-checked. Pushes land here for review: accepting one adds it to the cross-institution board (tagged as peer-pushed); declining removes it from the queue. A push never changes this institution\'s own register.') }}
    </p>

    @if(session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-warning small">{{ session('error') }}</div>@endif

    @if(empty($pending))
        <div class="alert alert-light border">
            <i class="fas fa-inbox me-2"></i>{{ __('No pending pushed flags. When a peer pushes an at-risk flag, it will appear here for review.') }}
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr class="small text-uppercase text-muted">
                        <th>{{ __('Record') }}</th>
                        <th>{{ __('Risk') }}</th>
                        <th>{{ __('Urgency') }}</th>
                        <th>{{ __('Pushed by') }}</th>
                        <th>{{ __('Trust') }}</th>
                        <th>{{ __('Received') }}</th>
                        <th class="text-end">{{ __('Decision') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pending as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->title ?? $row->reference }}</strong>
                                @if(!empty($row->catalogue_url))
                                    <a href="{{ $row->catalogue_url }}" target="_blank" rel="noopener" class="ms-1 small">
                                        <i class="fas fa-arrow-up-right-from-square"></i>
                                    </a>
                                @endif
                                @if(!empty($row->reason))
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($row->reason, 160) }}</div>
                                @endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ ucwords(str_replace('_', ' ', $row->risk)) }}</span></td>
                            <td>{{ ucfirst($row->urgency) }}</td>
                            <td class="small">{{ $row->source_peer_name ?? $row->source_peer_base_url }}</td>
                            <td>
                                @if($row->peer_verified)
                                    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle" title="{{ $row->key_fingerprint }}"><i class="fas fa-shield-halved me-1"></i>{{ __('verified') }}</span>
                                @else
                                    <span class="badge bg-warning text-dark" title="{{ __('The push was accepted but its signature could not be verified.') }}">{{ __('unverified') }}</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $row->received_at ? \Illuminate\Support\Carbon::parse($row->received_at)->diffForHumans() : '' }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <form method="POST" action="{{ route('endangered.inbound.review', ['id' => $row->id]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="accepted">
                                        <button type="submit" class="btn btn-sm btn-success py-0">{{ __('Accept') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('endangered.inbound.review', ['id' => $row->id]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="declined">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary py-0">{{ __('Decline') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection
