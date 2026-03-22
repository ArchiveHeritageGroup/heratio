<section id="popular-this-week" class="card mb-3">
  <h2 class="h5 p-3 mb-0">
    {{ __('Popular this week') }}
  </h2>
  <div class="list-group list-group-flush">
    @foreach($popularThisWeek as $item)
      @php $object = QubitObject::getById($item[0]); @endphp
      @if($object instanceof QubitInformationObject)
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break"
           href="@php echo url_for([$object, 'module' => 'informationobject']); @endphp">
          @php echo render_title($object); @endphp
          <span class="ms-3 text-nowrap">
            {{ __('%1% visits', ['%1%' => $item[1]]) }}
          </span>
        </a>
      @elseif($object instanceof QubitRepository)
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break"
           href="@php echo url_for([$object, 'module' => 'repository']); @endphp">
          @php echo render_title($object); @endphp
          <span class="ms-3 text-nowrap">
            {{ __('%1% visits', ['%1%' => $item[1]]) }}
          </span>
        </a>
      @elseif($object instanceof QubitActor)
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break"
           href="@php echo url_for([$object, 'module' => 'actor']); @endphp">
          @php echo render_title($object); @endphp
          <span class="ms-3 text-nowrap">
            {{ __('%1% visits', ['%1%' => $item[1]]) }}
          </span>
        </a>
      @elseif($object)
        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break"
           href="/index.php/@php echo $object->slug; @endphp">
          @php echo render_title($object); @endphp
          <span class="ms-3 text-nowrap">
            {{ __('%1% visits', ['%1%' => $item[1]]) }}
          </span>
        </a>
      @endif
    @endforeach
  </div>
</section>
