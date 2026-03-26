<div class="row g-3 mb-3 masonry">

@foreach($pager->getResults() as $hit)
  @php $doc = $hit->getData(); @endphp
  @php $title = get_search_i18n(
      $doc,
      'title',
      ['allowEmpty' => false, 'culture' => $selectedCulture]
  ); @endphp

  <div class="col-sm-6 col-lg-4 masonry-item">
    <div class="card">
      @if(!empty($doc['hasDigitalObject']))
        @php // Get thumbnail or generic icon path
            if (
                isset($doc['digitalObject']['thumbnailPath'])
                && QubitAcl::check(
                    QubitInformationObject::getById($hit->getId()),
                    'readThumbnail'
                )
            ) {
                $imagePath = $doc['digitalObject']['thumbnailPath'];
            } else {
                $imagePath = QubitDigitalObject::getGenericIconPathByMediaTypeId(
                    $doc['digitalObject']['mediaTypeId'] ?: null
                );
            } @endphp
        <a href="@php echo url_for(
            ['module' => 'informationobject', 'slug' => $doc['slug']]
        ); @endphp">
          @php echo image_tag($imagePath, [
              'alt' => $doc['digitalObject']['digitalObjectAltText'] ?: strip_markdown($title),
              'class' => 'card-img-top',
          ]); @endphp
        </a>
      @else
        <a class="p-3" href="@php echo url_for(['module' => 'informationobject', 'slug' => $doc['slug']]); @endphp">
          @php echo render_title($title); @endphp
        </a>
      @endif

      <div class="card-body">
        <div class="card-text d-flex align-items-start gap-2">
          <span>@php echo render_title($title); @endphp</span>
          @php echo get_component('clipboard', 'button', [
              'slug' => $doc['slug'],
              'wide' => false,
              'type' => 'informationObject',
          ]); @endphp
        </div>
      </div>
    </div>
  </div>
@endforeach
</div>
