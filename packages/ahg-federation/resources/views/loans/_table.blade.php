{{--
  Federation loans - shared worklist table partial (#1203).
  Expects: $rows (array of loan rows, decorated with requesting_name /
  holding_name), $statusBadge (closure status => bootstrap badge class).

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
<table class="table table-sm mb-0 align-middle">
    <thead>
        <tr>
            <th>{{ __('Item') }}</th>
            <th>{{ __('From') }}</th>
            <th>{{ __('To') }}</th>
            <th>{{ __('Window') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>
                    <a href="{{ route('federation.loans.show', $row->id) }}">
                        {{ $row->item_title ?: ($row->item_ref ?: __('(untitled item)')) }}
                    </a>
                    @if ($row->item_ref && $row->item_title)
                        <div class="small text-muted">{{ __('Ref:') }} {{ $row->item_ref }}</div>
                    @endif
                </td>
                <td class="small">{{ $row->requesting_name }}</td>
                <td class="small">{{ $row->holding_name }}</td>
                <td class="small text-muted">
                    @if ($row->needed_from || $row->needed_to)
                        {{ $row->needed_from ?: '?' }} &rarr; {{ $row->needed_to ?: '?' }}
                    @else
                        -
                    @endif
                </td>
                <td>
                    <span class="badge {{ $statusBadge($row->status) }}">
                        {{ __(ucwords(str_replace('_', ' ', $row->status))) }}
                    </span>
                </td>
                <td class="text-end">
                    <a href="{{ route('federation.loans.show', $row->id) }}"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
