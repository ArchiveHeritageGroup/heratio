{{-- Partial: Repository card view --}}
@props(['repositories' => collect()])
<div class="row g-3">@foreach($repositories as $repo)
  <div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body">
    <h5 class="card-title"><a href="{{ route('repository.show', $repo->slug ?? '') }}">{{ $repo->authorized_form_of_name ?? '[Untitled]' }}</a></h5>
    @if($repo->city ?? false)<p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i>{{ $repo->city }}</p>@endif
    @if($repo->history ?? false)<p class="small mb-0">{{ Str::limit(strip_tags($repo->history), 100) }}</p>@endif
  </div></div></div>
@endforeach</div>
