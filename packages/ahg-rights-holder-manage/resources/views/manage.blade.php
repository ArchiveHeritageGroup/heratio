@extends('ahg-theme-b5::layout_1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
    </h1>
    <span class="small" id="heading-label">
      {{ __('Manage rights inheritance') }}
    </span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="post">
    @csrf

    {!! $form->renderHiddenFields() !!}

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="inheritance-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#inheritance-collapse" aria-expanded="true" aria-controls="inheritance-collapse">
            {{ __('Inheritance options') }}
          </button>
        </h2>
        <div id="inheritance-collapse" class="accordion-collapse collapse show" aria-labelledby="inheritance-heading">
          <div class="accordion-body">
            <div class="well">
              @php echo render_field($form->all_or_digital_only
                  ->label(__('All descendants or just digital objects'))
              ); @endphp
            </div>

            <div class="well">
              @php echo render_field($form->overwrite_or_combine
                  ->help(__('Set if you want to combine the current set of rights with any existing rights, or remove the existing rights and apply these new rights'))
                  ->label(__('Overwrite or combine rights'))
              ); @endphp
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', ['slug' => $resource->slug]) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Apply') }}"></li>
    </ul>

  </form>

@endsection
