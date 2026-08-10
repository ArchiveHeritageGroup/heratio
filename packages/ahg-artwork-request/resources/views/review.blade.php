{{-- Artwork placement request - review queue (#1459) --}}
@extends('theme::layouts.1col')

@section('title', 'Review artwork requests')

@section('content')
<div class="container-fluid py-4">

  @include('ahg-artwork-request::_flash')

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="fas fa-clipboard-check me-2"></i>Review queue</h1>
    <a href="{{ route('artwork-request.placements') }}" class="btn btn-outline-secondary">Out on campus</a>
  </div>

  @if(empty($pending))
    <div class="alert alert-info">Nothing is waiting for review.</div>
  @else
    @foreach($pending as $r)
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><a href="{{ route('artwork-request.view', ['id' => $r->id]) }}">{{ $r->request_number }}</a>
            - {{ $r->requester_name ?: 'A member of staff' }}</span>
          <span class="small text-muted">{{ $r->requested_from }} to {{ $r->requested_to }}</span>
        </div>
        <div class="card-body">
          <div class="row mb-3 small">
            <div class="col-md-4"><strong>Placement:</strong> {{ trim(($r->placement_building ?? '').' '.($r->placement_room ?? '')) ?: '-' }}
              @if($r->placement_occupant) ({{ $r->placement_occupant }}) @endif</div>
            <div class="col-md-2"><strong>Purpose:</strong> {{ $r->purpose ?: '-' }}</div>
            <div class="col-md-2"><strong>Dept:</strong> {{ $r->department ?: '-' }}</div>
          </div>
          @if($r->justification)<p class="text-muted"><em>{{ $r->justification }}</em></p>@endif

          <form method="post" action="{{ route('artwork-request.review') }}">
            @csrf
            <input type="hidden" name="request_id" value="{{ $r->id }}">

            <table class="table table-sm align-middle">
              <thead><tr><th>Work</th><th style="width:220px">Decision</th><th>Availability at request</th></tr></thead>
              <tbody>
                @foreach($works[$r->id] ?? [] as $w)
                  <tr>
                    <td>{{ $w->object_title ?: '#'.$w->information_object_id }}
                      @if($w->object_identifier)<span class="small text-muted">· {{ $w->object_identifier }}</span>@endif</td>
                    <td>
                      <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="decision[{{ $w->id }}]" id="a{{ $w->id }}" value="approved" checked>
                        <label class="btn btn-outline-success" for="a{{ $w->id }}">Approve</label>
                        <input type="radio" class="btn-check" name="decision[{{ $w->id }}]" id="d{{ $w->id }}" value="declined">
                        <label class="btn btn-outline-danger" for="d{{ $w->id }}">Decline</label>
                      </div>
                    </td>
                    <td class="small {{ $w->conflict_note ? 'text-warning' : 'text-muted' }}">{{ $w->conflict_note ?: 'No clash recorded' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>

            <div class="row g-3 align-items-end">
              <div class="col-md-7"><label class="form-label" for="notes{{ $r->id }}">Notes to the requester</label>
                <input type="text" class="form-control" id="notes{{ $r->id }}" name="review_notes"></div>
              <div class="col-md-3"><label class="form-label" for="chan{{ $r->id }}">Decided</label>
                <select class="form-select" id="chan{{ $r->id }}" name="decision_channel">
                  <option value="system">Here, now</option>
                  <option value="offline">Offline (recording a decision already made)</option>
                </select></div>
              <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Record</button></div>
            </div>
          </form>
        </div>
      </div>
    @endforeach
  @endif
</div>
@endsection
