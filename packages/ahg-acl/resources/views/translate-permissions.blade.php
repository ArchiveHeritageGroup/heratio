{{--
  Translate ACL matrix - issue #744.

  Rows: ACL groups. Columns: enabled locales (i18n_languages setting, with
  the bundled default list as fallback). Each cell is a checkbox that POSTs
  to /admin/translate-permissions via AJAX.
--}}
@extends('theme::layouts.1col')

@section('title', __('Translate ACL'))
@section('body-class', 'admin translatePermission')

@section('title-block')
  <h1>{{ __('Translate ACL') }}</h1>
@endsection

@section('content')
  <p class="text-muted">
    {{ __('Tick a cell to allow members of the group to translate into that locale. Untick to revoke. Changes save immediately.') }}
  </p>

  @if($groups->isEmpty())
    <div class="alert alert-info">{{ __('No ACL groups defined yet.') }}</div>
  @elseif(empty($locales))
    <div class="alert alert-info">{{ __('No locales enabled yet.') }}</div>
  @else
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-sm align-middle mb-0" id="translate-permission-matrix">
        <thead class="table-light">
          <tr>
            <th>{{ __('Group') }}</th>
            @foreach($locales as $code => $name)
              <th class="text-center small" scope="col" title="{{ $name }}">
                <i class="bi bi-translate" aria-hidden="true"></i>
                <span class="ms-1">{{ strtoupper($code) }}</span>
                <span class="visually-hidden">{{ $name }}</span>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @foreach($groups as $group)
            <tr>
              <th scope="row">{{ $group->name ?? ('Group '.$group->id) }}</th>
              @foreach($locales as $code => $name)
                @php $checked = ! empty($matrix[$group->id][$code]); @endphp
                <td class="text-center">
                  <input type="checkbox"
                    class="form-check-input translate-permission-cell"
                    data-group-id="{{ $group->id }}"
                    data-locale="{{ $code }}"
                    aria-label="{{ ($group->name ?? 'Group '.$group->id).' / '.$name }}"
                    @checked($checked)>
                </td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div id="translate-permission-status" class="small text-muted" aria-live="polite"></div>
  @endif
@endsection

@push('scripts')
<script>
(function () {
  const matrix = document.getElementById('translate-permission-matrix');
  if (!matrix) return;
  const status = document.getElementById('translate-permission-status');
  const url = @json(route('admin.translate-permissions.update'));
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';

  matrix.addEventListener('change', async function (ev) {
    const cell = ev.target;
    if (!cell.classList.contains('translate-permission-cell')) return;
    const payload = {
      group_id: parseInt(cell.dataset.groupId, 10),
      locale: cell.dataset.locale,
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
          ? 'Granted translate (' + payload.locale + ')'
          : 'Revoked translate (' + payload.locale + ')';
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
