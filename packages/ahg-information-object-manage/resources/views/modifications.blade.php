@extends('ahg-theme-b5::layout_1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ __('Modifications') }}
    </h1>
    <span class="small" id="heading-label">
      {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
    </span>
  </div>
@endsection

@section('content')
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>
            {{ __('Date') }}
          </th>
          <th>
            {{ __('Type') }}
          </th>
          <th>
            {{ __('User') }}
          </th>
        </tr>
      </thead>
      <tbody>
        @foreach($modifications as $modification)
          <tr>
            <td>
              {{ \Carbon\Carbon::parse($modification->createdAt)->translatedFormat('F j, Y') }}
            </td>
            <td>
              {{ $modification->actionTypeName ?? '' }}
            </td>
            <td>
              @if(auth()->check() && auth()->user()->isAdministrator() && $modification->userId)
                <a href="{{ route('user.show', $modification->userId) }}">{{ $modification->userName }}</a>
              @else
                {{ $modification->userName }}
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

@section('after-content')
  @if(isset($pager))
    @include('ahg-core::partials._pager', ['pager' => $pager])
  @endif
@endsection
