@extends('theme::layouts.1col')

@section('title', $storage->name ?? 'Physical storage')
@section('body-class', 'view physicalobject')

@section('content')
  <h1>{{ $storage->name ?? '[Untitled]' }}</h1>

  {{-- Identity area --}}
  <section class="mb-4">
    <h2 class="fs-5 border-bottom pb-2">Identity area</h2>

    @if($storage->name)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Name</div>
        <div class="col-md-9">{{ $storage->name }}</div>
      </div>
    @endif

    @if($typeName)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Type</div>
        <div class="col-md-9">{{ $typeName }}</div>
      </div>
    @endif

    @if($storage->location)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Location</div>
        <div class="col-md-9">{{ $storage->location }}</div>
      </div>
    @endif

    @if($storage->description)
      <div class="row mb-2">
        <div class="col-md-3 fw-bold">Description</div>
        <div class="col-md-9">{!! nl2br(e($storage->description)) !!}</div>
      </div>
    @endif
  </section>

  {{-- Related descriptions --}}
  @if($descriptions->isNotEmpty())
    <section class="mb-4">
      <h2 class="fs-5 border-bottom pb-2">Related descriptions</h2>
      <div class="table-responsive">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>Title</th>
            </tr>
          </thead>
          <tbody>
            @foreach($descriptions as $desc)
              <tr>
                <td>
                  <a href="{{ route('informationobject.show', $desc->slug) }}">
                    {{ $desc->title ?: '[Untitled]' }}
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
  @endif
@endsection
