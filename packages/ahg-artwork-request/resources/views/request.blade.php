{{-- Artwork placement request form (#1459) --}}
@extends('theme::layouts.1col')

@section('title', 'Request an artwork')

@section('content')
<div class="container-fluid py-4">
  <h1 class="h3 mb-3"><i class="fas fa-palette me-2"></i>Request an artwork for placement</h1>

  <p class="text-muted">Ask to place one or more works in an office or shared space. The gallery is notified and
    records the decision - the conversation itself stays with people.</p>

  @if(!empty($formErrors))
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($formErrors as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('artwork-request.new') }}" id="artworkRequestForm">
    @csrf

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card mb-3">
          <div class="card-header">Works</div>
          <div class="card-body">
            <label class="form-label" for="object_ids_manual">Record id(s)</label>
            <input type="text" class="form-control" id="object_ids_manual" name="object_ids_manual"
                   value="{{ collect($works)->pluck('id')->implode(' ') }}"
                   placeholder="e.g. 12345 12346" aria-describedby="idsHelp">
            <div class="form-text" id="idsHelp">The information-object id of each work, separated by spaces or commas.</div>

            @foreach($works as $w)
              <input type="hidden" name="object_ids[]" value="{{ $w->id }}">
            @endforeach

            @if(!empty($works))
              <ul class="list-group mt-3" id="worksList">
                @foreach($works as $w)
                  <li class="list-group-item d-flex justify-content-between align-items-start" data-object-id="{{ $w->id }}">
                    <div>
                      <div>{{ $w->title ?: '(untitled)' }} @if(!$w->exists)<span class="badge bg-danger">no such record</span>@endif</div>
                      <div class="small text-muted">#{{ $w->id }} {{ $w->identifier ? '· '.$w->identifier : '' }}</div>
                    </div>
                    <span class="availability small text-muted">&nbsp;</span>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">Placement</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label" for="placement_building">Building</label>
                <input type="text" class="form-control" id="placement_building" name="placement_building" value="{{ $values['placement_building'] }}"></div>
              <div class="col-md-3"><label class="form-label" for="placement_floor">Floor</label>
                <input type="text" class="form-control" id="placement_floor" name="placement_floor" value="{{ $values['placement_floor'] }}"></div>
              <div class="col-md-3"><label class="form-label" for="placement_room">Room</label>
                <input type="text" class="form-control" id="placement_room" name="placement_room" value="{{ $values['placement_room'] }}"></div>
              <div class="col-md-6"><label class="form-label" for="placement_occupant">Occupant</label>
                <input type="text" class="form-control" id="placement_occupant" name="placement_occupant" value="{{ $values['placement_occupant'] }}"></div>
              <div class="col-md-6"><label class="form-label" for="department">Department</label>
                <input type="text" class="form-control" id="department" name="department" value="{{ $values['department'] }}"></div>
              <div class="col-12"><label class="form-label" for="placement_notes">Placement notes</label>
                <textarea class="form-control" id="placement_notes" name="placement_notes" rows="2">{{ $values['placement_notes'] }}</textarea></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card mb-3">
          <div class="card-header">When and why</div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-6"><label class="form-label" for="requested_from">From</label>
                <input type="date" class="form-control" id="requested_from" name="requested_from" value="{{ $values['requested_from'] }}"></div>
              <div class="col-6"><label class="form-label" for="requested_to">To</label>
                <input type="date" class="form-control" id="requested_to" name="requested_to" value="{{ $values['requested_to'] }}"></div>
              <div class="col-12"><label class="form-label" for="purpose">Purpose</label>
                <select class="form-select" id="purpose" name="purpose">
                  @foreach(['' => '- choose -', 'office' => 'Office', 'boardroom' => 'Boardroom', 'shared workspace' => 'Shared workspace', 'event' => 'Event', 'other' => 'Other'] as $k => $lbl)
                    <option value="{{ $k }}" @selected($values['purpose'] === $k)>{{ $lbl }}</option>
                  @endforeach
                </select></div>
              <div class="col-12"><label class="form-label" for="justification">Justification</label>
                <textarea class="form-control" id="justification" name="justification" rows="4">{{ $values['justification'] }}</textarea></div>
            </div>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane me-1"></i> Submit request</button>
        </div>
        <p class="form-text mt-2">Availability is shown as a warning only. A clash does not stop you asking - the gallery decides.</p>
      </div>
    </div>
  </form>
</div>

<script>
// Live availability: warn as works + dates change. Progressive - the form
// submits fine without this running.
(function () {
  var form = document.getElementById('artworkRequestForm');
  if (!form) return;
  var url = @json(route('artwork-request.availability'));

  function check() {
    var from = document.getElementById('requested_from').value;
    var to = document.getElementById('requested_to').value;
    if (!from || !to) return;
    document.querySelectorAll('#worksList li[data-object-id]').forEach(function (li) {
      var id = li.getAttribute('data-object-id');
      var cell = li.querySelector('.availability');
      fetch(url + '?object_id=' + encodeURIComponent(id) + '&from=' + from + '&to=' + to,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.free) { cell.className = 'availability small text-success'; cell.textContent = 'Free'; }
          else {
            cell.className = 'availability small text-warning';
            cell.textContent = (d.conflicts || []).map(function (c) { return c.detail; }).join('; ') || 'Clash';
          }
        }).catch(function () {});
    });
  }
  ['requested_from', 'requested_to'].forEach(function (idf) {
    var el = document.getElementById(idf);
    if (el) el.addEventListener('change', check);
  });
  check();
})();
</script>
@endsection
