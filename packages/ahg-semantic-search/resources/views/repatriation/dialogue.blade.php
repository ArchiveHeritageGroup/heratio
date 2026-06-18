{{--
  Repatriation claim - staff dialogue workspace (heratio#1207)

  The holding-institution side of one claim: provenance-trace links, the status
  audit trail (change status WITH a note), the two-way threaded dialogue (incl.
  staff-internal notes), and the shared-record access grants (mint / revoke a
  capability token for the origin community). Admin-only. Sensitive subject
  matter: every status / message is where a dialogue stands, never a legal
  outcome. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $sm = $claim['status_meta'] ?? ['label' => $claim['claim_status'] ?? 'registered', 'level' => 'secondary', 'help' => ''];
    $itemTitle = ($claim['item_title'] ?? null) ?: (__('Record').' #'.($claim['item_ref'] ?? ''));
@endphp

@section('title', __('Claim dialogue').': '.$itemTitle)

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-comments me-2"></i>{{ __('Claim dialogue and shared record') }}</h1>
            <p class="text-muted mb-0">{{ $itemTitle }} <span class="badge text-bg-{{ $sm['level'] }} ms-1">{{ __($sm['label']) }}</span></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('repatriation.claims.edit', ['id' => $claim['id']]) }}" class="btn btn-outline-secondary">
                <i class="fas fa-pen me-1"></i>{{ __('Edit claim') }}
            </a>
            <a href="{{ route('repatriation.claims.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('All claims') }}
            </a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div><strong>{{ __('A documented request and its status, not a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p></div>
    </div>

    <div class="row g-4">

        {{-- LEFT: provenance trace + dialogue thread --}}
        <div class="col-12 col-lg-8">

            {{-- Provenance trace (pillar 1 - links to existing provenance surfaces) --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-route me-2 text-muted"></i>{{ __('Provenance trace') }}</div>
                <div class="card-body">
                    <div class="row g-3 mb-2 small">
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted fw-semibold">{{ __('Place / region of origin') }}</div>
                            <div>{{ $provenance['origin_place'] ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted fw-semibold">{{ __('Current holder') }}</div>
                            <div>{{ $provenance['current_holder'] ?: '-' }}</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($provenance['provenance_url'])
                            <a href="{{ $provenance['provenance_url'] }}" class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener">
                                <i class="fas fa-clock-rotate-left me-1"></i>{{ __('Chain of custody') }}</a>
                        @endif
                        @if($provenance['timeline_url'])
                            <a href="{{ $provenance['timeline_url'] }}" class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener">
                                <i class="fas fa-timeline me-1"></i>{{ __('Provenance timeline') }}</a>
                        @endif
                        @if($provenance['record_url'])
                            <a href="{{ $provenance['record_url'] }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                                <i class="fas fa-up-right-from-square me-1"></i>{{ __('Object record') }}</a>
                        @endif
                        <a href="{{ route('virtual-return.show', ['id' => $claim['id']]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="fas fa-person-walking-arrow-right me-1"></i>{{ __('Virtual return') }}</a>
                    </div>
                    @if(!$provenance['provenance_url'] && !$provenance['timeline_url'])
                        <p class="small text-muted mb-0 mt-2">{{ __('No dedicated provenance record is linked to this item yet. Add provenance events on the object record to build a chain of custody.') }}</p>
                    @endif
                </div>
            </div>

            {{-- Dialogue thread --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-comments me-2 text-muted"></i>{{ __('Dialogue') }}</div>
                <div class="card-body">
                    @if(empty($messages))
                        <p class="text-muted small mb-3">{{ __('No messages yet. Open the conversation with the claimant below, or keep an internal note.') }}</p>
                    @else
                        <div class="d-flex flex-column gap-3 mb-3">
                            @foreach($messages as $m)
                                <div class="border rounded-3 p-3 {{ $m['visibility'] === 'internal' ? 'bg-light' : '' }}">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge text-bg-{{ $m['author_role_meta']['level'] }}">{{ __($m['author_role_meta']['label']) }}</span>
                                        <span class="small text-muted">
                                            @if($m['visibility'] === 'internal')<i class="fas fa-lock me-1" title="{{ __('Internal') }}"></i>@endif
                                            {{ $m['author_name'] ?: __('Unnamed') }} - {{ $m['created_at'] }}
                                        </span>
                                    </div>
                                    <div style="white-space: pre-line;">{{ $m['body'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('repatriation.claims.dialogue.message', ['id' => $claim['id']]) }}">
                        @csrf
                        <div class="mb-2">
                            <label for="body" class="form-label small fw-semibold">{{ __('Add a message') }}</label>
                            <textarea name="body" id="body" rows="3" maxlength="60000" class="form-control" required></textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-end">
                            <div>
                                <label for="author_role" class="form-label small mb-0">{{ __('Speaking as') }}</label>
                                <select name="author_role" id="author_role" class="form-select form-select-sm">
                                    @foreach($roles as $key => $meta)
                                        <option value="{{ $key }}">{{ __($meta['label']) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="visibility" class="form-label small mb-0">{{ __('Visibility') }}</label>
                                <select name="visibility" id="visibility" class="form-select form-select-sm">
                                    @foreach($visibilities as $key => $meta)
                                        <option value="{{ $key }}">{{ __($meta['label']) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm ms-auto"><i class="fas fa-paper-plane me-1"></i>{{ __('Post') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- RIGHT: status change + history + shared-record grants --}}
        <div class="col-12 col-lg-4">

            {{-- Change status with a note --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-flag me-2 text-muted"></i>{{ __('Change status') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('repatriation.claims.dialogue.status', ['id' => $claim['id']]) }}">
                        @csrf
                        <div class="mb-2">
                            <select name="claim_status" class="form-select form-select-sm">
                                @foreach($statuses as $key => $meta)
                                    <option value="{{ $key }}" {{ strcasecmp($claim['claim_status'] ?? 'registered', $key) === 0 ? 'selected' : '' }}>{{ __($meta['label']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="note" maxlength="1024" class="form-control form-control-sm" placeholder="{{ __('Reason / note (recorded in history)') }}">
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">{{ __('Update and record') }}</button>
                    </form>
                </div>
            </div>

            {{-- Status history --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>{{ __('Status history') }}</div>
                <div class="card-body">
                    @if(empty($history))
                        <p class="small text-muted mb-0">{{ __('No status changes recorded yet.') }}</p>
                    @else
                        <ul class="list-unstyled small mb-0">
                            @foreach($history as $h)
                                <li class="border-bottom pb-2 mb-2">
                                    <div>
                                        @if($h['from_status'])<span class="text-muted">{{ ucwords(str_replace('_',' ',$h['from_status'])) }}</span> <i class="fas fa-arrow-right mx-1 text-muted"></i>@endif
                                        <strong>{{ ucwords(str_replace('_',' ',$h['to_status'])) }}</strong>
                                    </div>
                                    @if($h['note'])<div class="text-muted">{{ $h['note'] }}</div>@endif
                                    <div class="text-muted">{{ $h['changed_by_name'] ?: __('System') }} - {{ $h['created_at'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            {{-- Shared-record access grants --}}
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-share-nodes me-2 text-muted"></i>{{ __('Shared record links') }}</div>
                <div class="card-body">
                    @if(session('new_share_url'))
                        <div class="alert alert-success small">
                            <strong>{{ __('New link (copy it now):') }}</strong>
                            <div class="text-break">{{ session('new_share_url') }}</div>
                        </div>
                    @endif

                    <p class="small text-muted">{{ __('Give the origin community a private link to see this record and join the dialogue, without a staff account.') }}</p>

                    @if(!empty($grants))
                        <ul class="list-unstyled small mb-3">
                            @foreach($grants as $g)
                                <li class="border-bottom pb-2 mb-2 d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-semibold">{{ $g['grantee_name'] ?: __('Unnamed') }}
                                            <span class="badge text-bg-{{ $g['is_active'] ? 'success' : 'secondary' }} ms-1">{{ $g['is_active'] ? __('Active') : __('Inactive') }}</span>
                                        </div>
                                        <div class="text-muted">{{ __($g['grantee_role_label']) }}{{ $g['can_message'] ? '' : ' - '.__('read-only') }}</div>
                                        @if($g['expires_at'])<div class="text-muted">{{ __('Expires') }}: {{ $g['expires_at'] }}</div>@endif
                                    </div>
                                    @if($g['is_active'])
                                        <form method="POST" action="{{ route('repatriation.claims.dialogue.revoke', ['id' => $claim['id'], 'grant' => $g['id']]) }}" onsubmit="return confirm('{{ __('Revoke this link?') }}');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Revoke') }}</button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="POST" action="{{ route('repatriation.claims.dialogue.grant', ['id' => $claim['id']]) }}">
                        @csrf
                        <div class="mb-2">
                            <input type="text" name="grantee_name" maxlength="255" class="form-control form-control-sm" placeholder="{{ __('Representative name') }}">
                        </div>
                        <div class="mb-2">
                            <select name="grantee_role" class="form-select form-select-sm">
                                @foreach($granteeRoles as $key => $label)
                                    <option value="{{ $key }}">{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="can_message" id="can_message" value="1" checked>
                            <label class="form-check-label small" for="can_message">{{ __('May join the dialogue') }}</label>
                        </div>
                        <div class="mb-2">
                            <label for="expires_at" class="form-label small mb-0">{{ __('Expires (optional)') }}</label>
                            <input type="date" name="expires_at" id="expires_at" class="form-control form-control-sm">
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-plus me-1"></i>{{ __('Create shared link') }}</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
