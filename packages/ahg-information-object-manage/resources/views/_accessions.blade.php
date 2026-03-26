<div class="field mb-3">
  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Accession number(s)') }}</h3>
  <div>
    <ul class="list-unstyled ms-0">
      @foreach($accessions as $item)
        <li><a href="{{ route('accession.show', $item->object->slug ?? $item->object->id ?? '') }}">{{ $item->object->authorized_form_of_name ?? $item->object->title ?? '' }}</a></li>
      @endforeach
    </ul>
  </div>
</div>
