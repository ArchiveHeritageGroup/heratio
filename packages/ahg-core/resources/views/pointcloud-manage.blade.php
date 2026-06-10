{{-- heratio#1183 Point clouds: upload .las/.laz/.ply -> Potree octree, list + view. --}}
@extends('theme::layouts.1col')
@section('title', __('Point clouds'))

@section('content')
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-2">
    <h1 class="h4 mb-0"><i class="fas fa-cubes me-2 text-primary"></i>{{ __('Point clouds') }}</h1>
    <span class="text-muted small">{{ __('LiDAR / photogrammetry scans - rock-art panels, sites, objects') }}</span>
  </div>
  <p class="text-muted small">{{ __('Upload a 3D scan (.las, .laz or .ply). Heratio converts it to a streaming octree you can explore in the browser. Very large scans are best converted on the server with') }} <code>ahg:pointcloud-convert</code>.</p>

  @if(session('pc_success'))<div class="alert alert-success alert-dismissible fade show">{{ session('pc_success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if(session('pc_error'))<div class="alert alert-warning alert-dismissible fade show">{{ session('pc_error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
  @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('pointclouds.store') }}" enctype="multipart/form-data" class="card card-body mb-4" style="max-width:680px">
    @csrf
    <div class="mb-2">
      <label class="form-label small mb-1">{{ __('Title') }}</label>
      <input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('e.g. Shelter 3 - main painted panel') }}" maxlength="200">
    </div>
    <div class="mb-2">
      <label class="form-label small mb-1">{{ __('Point cloud file') }} <span class="text-muted">(.las, .laz, .ply)</span></label>
      <input type="file" name="cloud" class="form-control form-control-sm" accept=".las,.laz,.ply" required>
    </div>
    <div><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>{{ __('Upload & convert') }}</button></div>
  </form>

  @if(empty($clouds))
    <div class="alert alert-info">{{ __('No point clouds yet.') }}</div>
  @else
    <table class="table table-sm table-hover align-middle">
      <thead class="table-light"><tr><th>{{ __('Title') }}</th><th>{{ __('Status') }}</th><th>{{ __('Points') }}</th><th>{{ __('Source') }}</th><th>{{ __('Added') }}</th><th></th></tr></thead>
      <tbody>
        @foreach($clouds as $c)
          <tr data-slug="{{ $c->slug }}">
            <td class="fw-bold">{{ $c->title }}</td>
            <td class="pc-status">
              @switch($c->status)
                @case('ready') <span class="badge bg-success">{{ __('Ready') }}</span> @break
                @case('failed') <span class="badge bg-danger" title="{{ $c->error }}">{{ __('Failed') }}</span> @break
                @case('processing') <span class="badge bg-info text-dark">{{ __('Processing') }}</span> @break
                @default <span class="badge bg-secondary">{{ __('Pending') }}</span>
              @endswitch
            </td>
            <td class="small text-muted">{{ $c->point_count ? number_format($c->point_count) : '—' }}</td>
            <td class="small text-muted">{{ $c->source_filename }}</td>
            <td class="small text-muted">{{ $c->created_at }}</td>
            <td class="text-end">
              @if($c->status === 'ready')
                <a href="{{ route('pointclouds.show', ['slug' => $c->slug]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>

<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  // Poll any pending/processing rows so the curator sees them flip to Ready without a manual reload.
  var rows = Array.prototype.filter.call(document.querySelectorAll('tr[data-slug]'), function (tr) {
    var b = tr.querySelector('.pc-status .badge');
    return b && (b.textContent.trim() === '{{ __('Pending') }}' || b.textContent.trim() === '{{ __('Processing') }}');
  });
  if (!rows.length) { return; }
  var base = '{{ url('pointcloud') }}';
  var timer = setInterval(function () {
    var still = 0;
    rows.forEach(function (tr) {
      var slug = tr.getAttribute('data-slug');
      fetch(base + '/' + encodeURIComponent(slug) + '/status', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok && (d.status === 'ready' || d.status === 'failed')) { location.reload(); }
          else { still++; }
        }).catch(function () {});
    });
    if (!still) { /* reload handles it */ }
  }, 5000);
  setTimeout(function () { clearInterval(timer); }, 600000);
})();
</script>
@endsection
