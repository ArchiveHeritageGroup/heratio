{{--
  Term ACL matrix - issue #744.

  Rows: ACL groups. Columns: (taxonomy x action). Each cell is a checkbox
  that POSTs to /admin/term-permissions via AJAX. No page reload.
--}}
@extends('theme::layouts.1col')

@section('title', __('Term ACL'))
@section('body-class', 'admin termPermission')

@section('title-block')
  <h1>{{ __('Term ACL') }}</h1>
@endsection

@section('content')
  <p class="text-muted">
    {{ __('Tick a cell to grant the action on that taxonomy to the group. Untick to revoke. Changes save immediately.') }}
  </p>

  @if($groups->isEmpty())
    <div class="alert alert-info">{{ __('No ACL groups defined yet.') }}</div>
  @elseif($taxonomies->isEmpty())
    <div class="alert alert-info">{{ __('No taxonomies defined yet.') }}</div>
  @else
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-sm align-middle mb-0" id="term-permission-matrix">
        <thead class="table-light">
          <tr>
            <th rowspan="2" class="align-middle">{{ __('Group') }}</th>
            @foreach($taxonomies as $taxonomy)
              <th colspan="{{ count($actions) }}" class="text-center">
                {{ $taxonomy->name }}
              </th>
            @endforeach
          </tr>
          <tr>
            @foreach($taxonomies as $taxonomy)
              @foreach($actions as $actionKey => $actionLabel)
                <th class="text-center small" scope="col" title="{{ $actionLabel }}">
                  @switch($actionKey)
                    @case('create')
                      <i class="bi bi-plus-circle" aria-hidden="true"></i>
                      @break
                    @case('read')
                      <i class="bi bi-eye" aria-hidden="true"></i>
                      @break
                    @case('update')
                      <i class="bi bi-pencil" aria-hidden="true"></i>
                      @break
                    @case('delete')
                      <i class="bi bi-trash" aria-hidden="true"></i>
                      @break
                    @default
                      {{ $actionLabel }}
                  @endswitch
                  <span class="visually-hidden">{{ $actionLabel }}</span>
                </th>
              @endforeach
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($groups as $group)
            <tr>
              <th scope="row">{{ $group->name ?? ('Group '.$group->id) }}</th>
              @foreach($taxonomies as $taxonomy)
                @foreach($actions as $actionKey => $actionLabel)
                  @php $checked = ! empty($matrix[$group->id][$taxonomy->id][$actionKey]); @endphp
                  <td class="text-center">
                    <input type="checkbox"
                      class="form-check-input term-permission-cell"
                      data-group-id="{{ $group->id }}"
                      data-taxonomy-id="{{ $taxonomy->id }}"
                      data-action="{{ $actionKey }}"
                      aria-label="{{ ($group->name ?? 'Group '.$group->id).' / '.$taxonomy->name.' / '.$actionLabel }}"
                      @checked($checked)>
                  </td>
                @endforeach
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div id="term-permission-status" class="small text-muted" aria-live="polite"></div>
  @endif
@endsection

@push('scripts')
<script>
(function () {
  const matrix = document.getElementById('term-permission-matrix');
  if (!matrix) return;
  const status = document.getElementById('term-permission-status');
  const url = @json(route('admin.term-permissions.update'));
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

  matrix.addEventListener('change', async function (ev) {
    const cell = ev.target;
    if (!cell.classList.contains('term-permission-cell')) return;
    const payload = {
      group_id: parseInt(cell.dataset.groupId, 10),
      taxonomy_id: parseInt(cell.dataset.taxonomyId, 10),
      action: cell.dataset.action,
      grant: cell.checked,
    };
    cell.disabled = true;
    status.textContent = 'Saving...';
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });
      if (!res.ok) {
        cell.checked = !payload.grant;
        status.textContent = 'Save failed (HTTP ' + res.status + ')';
      } else {
        const data = await res.json();
        status.textContent = data.granted
          ? 'Granted ' + payload.action
          : 'Revoked ' + payload.action;
      }
    } catch (err) {
      cell.checked = !payload.grant;
      status.textContent = 'Save failed (' + err.message + ')';
    } finally {
      cell.disabled = false;
    }
  });
})();
</script>
@endpush
