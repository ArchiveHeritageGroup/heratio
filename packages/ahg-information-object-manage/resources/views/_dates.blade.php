<div class="field mb-3">
  <h3 class="fs-6 fw-semibold text-body-secondary">{{ __('Date(s)') }}</h3>

    <div xmlns:dc="http://purl.org/dc/elements/1.1/" about="{{ route('informationobject.show', $resource->slug ?? '') }}" class="ms-0">

    <ul class="list-unstyled ms-0">
      @foreach($resource->getDates() as $item)
        <li>
          <div class="date">
            <span property="dc:date" start="{{ $item->startDate ?? '' }}" end="{{ $item->endDate ?? '' }}">
              {{ $item->date ?? '' }}
              @if(!empty($item->startDate) || !empty($item->endDate))
                ({{ $item->startDate ?? '' }} - {{ $item->endDate ?? '' }})
              @endif
            </span>
            @if(($defaultTemplate ?? '') !== 'dc')
              <span class="date-type">({{ $item->type ?? '' }})</span>
            @endif
            <dl class="mb-0">
              @if(isset($item->actor) && !empty($item->type->role ?? null))
                <dt class="fw-normal text-muted">{{ $item->type->role ?? '' }}</dt>
                <dd class="mb-0">{{ $item->actor->authorized_form_of_name ?? $item->actor->title ?? '' }}</dd>
              @endif
              @if(!empty($item->place))
                <dt class="fw-normal text-muted">{{ __('Place') }}</dt>
                <dd class="mb-0">{{ $item->place }}</dd>
              @endif
              @if(!empty($item->description))
                <dt class="fw-normal text-muted">{{ __('Note') }}</dt>
                <dd class="mb-0">{{ $item->description }}</dd>
              @endif
            </dl>

          </div>
        </li>
      @endforeach
    </ul>

  </div>
</div>
