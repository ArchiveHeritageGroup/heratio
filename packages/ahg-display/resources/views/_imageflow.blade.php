{{-- Partial: Image flow --}}
@props(['images' => collect()])
<div class="imageflow-container"><div class="row g-2">@foreach($images as $img)<div class="col-6 col-md-4 col-lg-3"><img src="{{ $img->path ?? '' }}" class="img-fluid rounded" alt="{{ $img->alt ?? '' }}"></div>@endforeach</div></div>
