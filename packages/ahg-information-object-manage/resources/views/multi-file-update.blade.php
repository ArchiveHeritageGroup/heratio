@extends('theme::layout_1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Update digital object titles') }}
    </h1>
    <span class="small" id="heading-label">
      {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
    </span>
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  <form method="post" action="{{ route('informationobject.multiFileUpdate', ['slug' => $resource->slug, 'items' => request('items')]) }}" id="bulk-title-update-form">
    @csrf

    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Object') }}</th>
            <th id="title-label">{{ __('Title') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($informationObjects ?? [] as $io)
            <tr>
              <td class="thumbnail-container">
                @if(isset($io->thumbnail))
                  <img src="{{ $io->thumbnail }}" alt="{{ __($io->digitalObjectAltText ?: 'Original %1% not accessible', ['%1%' => config('app.ui_label_digitalobject', 'digital object')]) }}" class="img-thumbnail">
                @elseif(isset($io->genericIcon))
                  <img src="{{ $io->genericIcon }}" alt="{{ __($io->digitalObjectAltText ?: 'Original %1% not accessible', ['%1%' => config('app.ui_label_digitalobject', 'digital object')]) }}" class="img-thumbnail">
                @endif
              </td>
              <td>
                @if(!empty($io->defaultTranslation))
                  <div class="default-translation">
                    {{ $io->defaultTranslation }}
                  </div>
                @endif

                <div class="mb-3">
                  <input type="text" class="form-control" name="titles[{{ $io->id }}]"
                    value="{{ old("titles.{$io->id}", $io->title ?? '') }}"
                    aria-labelledby="title-label">
                </div>

                @if(isset($io->digitalObjectName))
                  <div class="mb-3">
                    <h3 class="fs-6 mb-2">
                      {{ __('Filename') }}
                    </h3>
                    <span class="text-muted">
                      {{ $io->digitalObjectName }}
                    </span>
                  </div>
                @endif

                @if(isset($io->levelOfDescription))
                  <div class="mb-3">
                    <h3 class="fs-6 mb-2">
                      {{ __('Level of description') }}
                    </h3>
                    <span class="text-muted">
                      {{ $io->levelOfDescription }}
                    </span>
                  </div>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" id="rename-form-submit" type="submit" value="{{ __('Save') }}">
    </section>
  </form>

@endsection
