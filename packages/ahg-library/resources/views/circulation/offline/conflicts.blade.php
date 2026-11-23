@extends('theme::layouts.1col')
@section('title', __('Offline Conflicts'))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
        <a href="{{ route('library.circulation.offline') }}" class="btn btn-outline-secondary btn-sm me-2" title="{{ __('Back') }}">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Offline Conflicts') }}</h1>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="get" class="d-flex align-items-center gap-2 mb-3">
        <label for="status" class="form-label mb-0 small">{{ __('Status') }}</label>
        <select name="status" id="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
            <option value="" @selected($status === '')>{{ __('All') }}</option>
            @foreach($statuses as $code => $label)
                <option value="{{ $code }}" @selected($status === $code)>{{ __($label) }}</option>
            @endforeach
        </select>
        <noscript><button class="btn btn-sm atom-btn-white">{{ __('Filter') }}</button></noscript>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Happened (UTC)') }}</th>
                        <th scope="col">{{ __('Transaction') }}</th>
                        <th scope="col">{{ __('Copy') }}</th>
                        <th scope="col">{{ __('Patron') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Resolve') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ $row->occurred_at }}</td>
                        <td>{{ __($actionLabels[$row->action] ?? $row->action) }}</td>
                        <td>{{ $row->copy_barcode }}@if($row->title)<br><span class="small text-secondary">{{ $row->title }}</span>@endif</td>
                        <td>{{ trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ($row->patron_card ?? '') }}</td>
                        <td>
                            {{ __($statuses[$row->status] ?? $row->status) }}
                            @if($row->conflict_code)
                                <br><span class="small">{{ __($conflictLabels[$row->conflict_code] ?? $row->conflict_code) }}</span>
                                @php $detail = json_decode((string) $row->conflict_detail, true) ?: []; @endphp
                                @if($row->conflict_code === 'fine_reduction' && isset($detail['current'], $detail['proposed']))
                                    <br><span class="small text-secondary">{{ __('Fine now :current, after this return :proposed.', ['current' => number_format((float) $detail['current'], 2), 'proposed' => number_format((float) $detail['proposed'], 2)]) }}</span>
                                @elseif(isset($detail['skew_seconds']))
                                    <br><span class="small text-secondary">{{ __('Desk clock differed from the server by :minutes minutes at sync.', ['minutes' => (int) ceil(abs($detail['skew_seconds']) / 60)]) }}</span>
                                @elseif(isset($detail['max_days']))
                                    <br><span class="small text-secondary">{{ __('The offline window is :days days.', ['days' => (int) $detail['max_days']]) }}</span>
                                @endif
                            @endif
                            @if($row->rule_basis === 'earliest_known')
                                <br><span class="small text-secondary">{{ __('Judged by the earliest recorded loan rules.') }}</span>
                            @endif
                            @if($row->resolution_note)
                                <br><span class="small text-secondary">{{ $row->resolution_note }}</span>
                            @endif
                        </td>
                        <td>
                            @if($row->status === 'conflict')
                                <form method="post" action="{{ route('library.circulation.offline.resolve', $row->id) }}" class="d-flex gap-1 flex-wrap">
                                    @csrf
                                    <label class="visually-hidden" for="res-{{ $row->id }}">{{ __('Resolution') }}</label>
                                    <select name="resolution" id="res-{{ $row->id }}" class="form-select form-select-sm w-auto" required>
                                        @foreach($resolutions as $code => $label)
                                            @continue($code === 'apply' && ! in_array($row->conflict_code, $overridable, true))
                                            <option value="{{ $code }}">{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    <label class="visually-hidden" for="note-{{ $row->id }}">{{ __('Note') }}</label>
                                    <input type="text" name="note" id="note-{{ $row->id }}" maxlength="1000" class="form-control form-control-sm w-auto" placeholder="{{ __('Note (needed to apply or reject)') }}">
                                    <button class="btn btn-sm atom-btn-white">{{ __('Go') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-3">{{ __('Nothing to show.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection
