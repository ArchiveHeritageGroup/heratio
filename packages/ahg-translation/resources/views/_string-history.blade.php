{{--
  Audit history for one (locale, key) pair. Returned by the
  ahgtranslation.strings.history endpoint as a partial; the parent
  view injects this into a modal body.

  Source of truth: ui_string_change rows for this (locale, key_text),
  newest first. Includes pending + approved + rejected so editors see
  the full chain (who proposed, who reviewed, what landed).
--}}
<div class="ui-string-history-wrap">
  <div class="mb-2 small text-muted">
    {{ __('Locale') }}: <code>{{ $locale }}</code>
    &nbsp;-&nbsp;
    {{ __('Key') }}: <code>{{ $key }}</code>
  </div>

  @if($rows->isEmpty())
    <div class="alert alert-info py-2 small mb-0">
      {{ __('No prior versions on record. The current value (if any) is the only one ever saved through this editor.') }}
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:13%;">{{ __('When') }}</th>
            <th style="width:11%;">{{ __('Status') }}</th>
            <th style="width:25%;">{{ __('Old value') }}</th>
            <th style="width:25%;">{{ __('New value') }}</th>
            <th style="width:13%;">{{ __('Submitted by') }}</th>
            <th style="width:13%;">{{ __('Reviewed by') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $r)
            @php
              $statusBadge = match ($r->status) {
                'approved' => 'bg-success',
                'rejected' => 'bg-danger',
                'pending'  => 'bg-warning text-dark',
                default    => 'bg-secondary',
              };
            @endphp
            <tr>
              <td class="small text-nowrap">
                {{ \Carbon\Carbon::parse($r->submitted_at)->format('Y-m-d H:i') }}
                <div class="text-muted">{{ \Carbon\Carbon::parse($r->submitted_at)->diffForHumans() }}</div>
              </td>
              <td><span class="badge {{ $statusBadge }}">{{ $r->status }}</span></td>
              <td class="small"><pre class="m-0 small" style="white-space:pre-wrap;">{{ $r->old_value ?? '' }}</pre></td>
              <td class="small"><pre class="m-0 small" style="white-space:pre-wrap;">{{ $r->new_value ?? '' }}</pre></td>
              <td class="small">
                {{ $r->submitter_name ?? $r->submitter_username ?? ('user#' . $r->submitted_by_user_id) }}
              </td>
              <td class="small">
                @if($r->reviewed_by_user_id)
                  {{ $r->reviewer_name ?? $r->reviewer_username ?? ('user#' . $r->reviewed_by_user_id) }}
                  @if($r->reviewed_at)
                    <div class="text-muted">{{ \Carbon\Carbon::parse($r->reviewed_at)->format('Y-m-d H:i') }}</div>
                  @endif
                  @if($r->review_note)
                    <div class="text-muted fst-italic">"{{ \Illuminate\Support\Str::limit($r->review_note, 60) }}"</div>
                  @endif
                @else
                  <span class="text-muted">-</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
