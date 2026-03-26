@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$headingCondition = false;
if (auth()->check()) {
    $headingCondition = in_array(auth()->user()->role ?? '', ['editor', 'administrator']);
}
$headingsUrl = route('io.digitalobject.edit', $resource->id ?? 0);
$headingsTitle = __('Edit %1%', ['%1%' => $digitalObjectLabel]);
@endphp

<section>

  @include('ahg-theme-b5::partials._section-heading', [
      'heading' => __('%1% (%2%) rights area', ['%1%' => $digitalObjectLabel, '%2%' => $resource->usage ?? '']),
      'condition' => $headingCondition,
      'link' => $headingsUrl,
      'title' => $headingsTitle,
  ])

  @foreach($resource->getRights ?? [] as $item)
    @include('ahg-information-object-manage::rights._right', [
        'resource' => $item->object ?? $item,
        'object' => $item,
    ])
  @endforeach

</section>

<section>

  @php
    $referenceChild = null;
    if (method_exists($resource, 'getChildByUsageId')) {
        $referenceChild = $resource->getChildByUsageId(config('atom.term.REFERENCE_ID'));
    }
  @endphp

  @if($referenceChild)

    @include('ahg-theme-b5::partials._section-heading', [
        'heading' => __('%1% (%2%) rights area', ['%1%' => $digitalObjectLabel, '%2%' => $referenceChild->usage ?? '']),
        'condition' => $headingCondition,
        'link' => $headingsUrl,
        'title' => $headingsTitle,
    ])

    @foreach($referenceChild->getRights ?? [] as $item)
      @include('ahg-information-object-manage::rights._right', [
          'resource' => $item->object ?? $item,
          'object' => $resource,
      ])
    @endforeach

  @endif

</section>

<section>

  @php
    $thumbnailChild = null;
    if (method_exists($resource, 'getChildByUsageId')) {
        $thumbnailChild = $resource->getChildByUsageId(config('atom.term.THUMBNAIL_ID'));
    }
  @endphp

  @if($thumbnailChild)

    @include('ahg-theme-b5::partials._section-heading', [
        'heading' => __('%1% (%2%) rights area', ['%1%' => $digitalObjectLabel, '%2%' => $thumbnailChild->usage ?? '']),
        'condition' => $headingCondition,
        'link' => $headingsUrl,
        'title' => $headingsTitle,
    ])

    @foreach($thumbnailChild->getRights ?? [] as $item)
      @include('ahg-information-object-manage::rights._right', [
          'resource' => $item->object ?? $item,
          'object' => $resource,
      ])
    @endforeach

  @endif

</section>
