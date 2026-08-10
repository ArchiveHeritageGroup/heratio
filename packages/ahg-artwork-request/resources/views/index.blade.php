{{-- Artwork placement requests - my requests (#1459) --}}
@extends('theme::layouts.1col')

@section('title', 'My artwork requests')

@section('content')
<div class="container-fluid py-4">

  @include('ahg-artwork-request::_flash')

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="fas fa-palette me-2"></i>My artwork requests</h1>
    <div class="d-flex gap-2">
      <a href="{{ route('artwork-request.new') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i> New request</a>
      @if(\AhgCore\Services\AclService::hasPermission(auth()->id(), 'update'))
        <a href="{{ route('artwork-request.review') }}" class="btn btn-outline-secondary">Review queue</a>
        <a href="{{ route('artwork-request.placements') }}" class="btn btn-outline-secondary">Out on campus</a>
      @endif
    </div>
  </div>

  @if(empty($requests))
    <div class="alert alert-info">You have not requested any artworks yet.
      <a href="{{ route('artwork-request.new') }}" class="alert-link">Make a request</a>.</div>
  @else
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Request</th><th>Works</th><th>Period</th><th>Placement</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($requests as $r)
            <tr>
              <td><a href="{{ route('artwork-request.view', ['id' => $r->id]) }}">{{ $r->request_number }}</a></td>
              <td>
                @foreach($works[$r->id] ?? [] as $w)
                  <div class="small">{{ $w->object_title ?: '#'.$w->information_object_id }}
                    <span class="badge bg-secondary">{{ $w->status }}</span></div>
                @endforeach
              </td>
              <td class="small">{{ $r->requested_from }} - {{ $r->requested_to }}</td>
              <td class="small">{{ trim(($r->placement_building ?? '').' '.($r->placement_room ?? '')) ?: '-' }}</td>
              <td><span class="badge bg-{{ in_array($r->status, ['approved','fulfilled']) ? 'success' : ($r->status === 'declined' ? 'danger' : 'secondary') }}">{{ $r->status }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
