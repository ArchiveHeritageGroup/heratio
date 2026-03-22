{{-- Featured Collection Carousel for Homepage --}}
@php
$collectionId = $collectionId ?? null;
$maxItems = $maxItems ?? 12;
$height = $height ?? '450px';
$autoplay = $autoplay ?? true;
$interval = $interval ?? 5000;
$showCaptions = $showCaptions ?? true;
$items = $items ?? [];
@endphp
@if(count($items) > 0)
<div id="iiif-featured-carousel" class="carousel slide mb-4" @if($autoplay) data-bs-ride="carousel" data-bs-interval="{{ $interval }}" @endif>
  <div class="carousel-inner" style="height:{{ $height }};border-radius:8px;overflow:hidden;">
    @foreach($items as $i => $item)
    <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
      <img src="{{ $item->image_url ?? '' }}" class="d-block w-100" alt="{{ $item->title ?? '' }}" style="height:{{ $height }};object-fit:cover;">
      @if($showCaptions && ($item->title ?? null))
      <div class="carousel-caption d-none d-md-block" style="background:rgba(0,0,0,0.5);border-radius:8px;padding:10px;">
        <h5>{{ $item->title }}</h5>
      </div>
      @endif
    </div>
    @endforeach
  </div>
  @if(count($items) > 1)
  <button class="carousel-control-prev" type="button" data-bs-target="#iiif-featured-carousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
  <button class="carousel-control-next" type="button" data-bs-target="#iiif-featured-carousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
  @endif
</div>
@endif
