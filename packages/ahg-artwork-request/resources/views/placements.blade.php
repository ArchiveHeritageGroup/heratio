{{-- Artwork placements - out on campus (#1459) --}}
@extends('theme::layouts.1col')

@section('title', 'Artworks out on campus')

@section('content')
<div class="container-fluid py-4">

  @include('ahg-artwork-request::_flash')

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h3 mb-0"><i class="fas fa-map-marker-alt me-2"></i>Artworks out on campus</h1>
    <div class="btn-group">
      <a href="{{ route('artwork-request.placements') }}" class="btn btn-outline-secondary {{ $overdueOnly ? '' : 'active' }}">All</a>
      <a href="{{ route('artwork-request.placements', ['overdue' => 1]) }}" class="btn btn-outline-danger {{ $overdueOnly ? 'active' : '' }}">Overdue only</a>
    </div>
  </div>

  @if(empty($placements))
    <div class="alert alert-info">{{ $overdueOnly ? 'Nothing is overdue.' : 'Nothing is currently out.' }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr><th>Work</th><th>Request</th><th>With</th><th>Placement</th><th>Due back</th><th></th></tr>
        </thead>
        <tbody>
          @foreach($placements as $p)
            @php $overdue = $p->requested_to && $p->requested_to < $today; @endphp
            <tr class="{{ $overdue ? 'table-danger' : '' }}">
              <td>{{ $p->object_title ?: '-' }}
                @if($p->object_identifier)<span class="small text-muted d-block">{{ $p->object_identifier }}</span>@endif</td>
              <td><a href="{{ route('artwork-request.view', ['id' => \Illuminate\Support\Facades\DB::table('artwork_request')->where('request_number', $p->request_number)->value('id')]) }}">{{ $p->request_number }}</a></td>
              <td class="small">{{ $p->placement_occupant ?: $p->requester_name }}
                @if($p->department)<span class="text-muted d-block">{{ $p->department }}</span>@endif</td>
              <td class="small">{{ trim(($p->placement_building ?? '').' '.($p->placement_room ?? '')) ?: '-' }}</td>
              <td>{{ $p->requested_to ?: '-' }}
                @if($overdue)<span class="badge bg-danger ms-1">overdue</span>@endif</td>
              <td><span class="badge bg-secondary">{{ $p->status }}</span></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
