{{-- rightsHolderManage/viewSuccess - redirects to main show view --}}
@extends('theme::layouts.1col')

@section('title', 'View rights holder')
@section('body-class', 'view rightsholder')

@section('title-block')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">View rights holder</h1>
    <span class="small" id="heading-label">{{ $rightsHolder->authorized_form_of_name ?? '[Untitled]' }}</span>
  </div>
@endsection

@section('content')
  @include('ahg-rights-holder-manage::show', ['rightsHolder' => $rightsHolder, 'contacts' => $contacts ?? collect(), 'rights' => $rights ?? collect(), 'basisNames' => $basisNames ?? [], 'extendedRights' => $extendedRights ?? collect(), 'extendedRightsTkLabels' => $extendedRightsTkLabels ?? []])
@endsection
