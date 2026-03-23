@php
  $class = $class ?? 'QubitRepository';
  $enableInstitutionalScoping = config('app.enable_institutional_scoping', false);
@endphp

@if('QubitRepository' !== $class)
  @include('ahg-repository-manage::_logo', ['resource' => $resource ?? $repository])
@else

  @if($enableInstitutionalScoping)
    @include('ahg-repository-manage::_holdings-institution', ['resource' => $resource ?? $repository])
    @include('ahg-repository-manage::_holdings-list', ['resource' => $resource ?? $repository])
    @include('ahg-repository-manage::_upload-limit', ['resource' => $resource ?? $repository])
  @else
    @include('ahg-repository-manage::_logo', ['resource' => $resource ?? $repository])
    @include('ahg-repository-manage::_upload-limit', ['resource' => $resource ?? $repository])
    @include('ahg-repository-manage::_holdings', ['resource' => $resource ?? $repository])
    @include('ahg-repository-manage::_holdings-list', ['resource' => $resource ?? $repository])
  @endif

@endif
