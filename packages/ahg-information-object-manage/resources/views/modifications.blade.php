@extends('theme::layout_1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      <i class="bi bi-clock-history me-2" aria-hidden="true"></i>
      {{ __('Modifications') }}
    </h1>
    <span class="small" id="heading-label">
      @if(!empty($resource->slug))
        <a href="{{ url('/' . $resource->slug) }}">
          {{ $resource->title ?? $resource->identifier ?? $resource->slug }}
        </a>
      @else
        {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
      @endif
    </span>
  </div>
@endsection

@section('content')
  @if(empty($modifications))
    <div class="alert alert-info" role="alert">
      <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
      {{ __('No modifications have been recorded for this description.') }}
    </div>
  @else
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>
              <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
              {{ __('Date') }}
            </th>
            <th>
              <i class="bi bi-tag me-1" aria-hidden="true"></i>
              {{ __('Type') }}
            </th>
            <th>
              <i class="bi bi-person me-1" aria-hidden="true"></i>
              {{ __('User') }}
            </th>
          </tr>
        </thead>
        <tbody>
          @foreach($modifications as $modification)
            <tr>
              <td>
                {{ \Carbon\Carbon::parse($modification->createdAt)->translatedFormat('F j, Y H:i') }}
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
  @endif
@endsection

@section('after-content')
  @if(isset($pager))
    @include('ahg-core::components.pager', ['pager' => $pager])
  @endif
@endsection
