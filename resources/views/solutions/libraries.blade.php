@php
  $demoLinks = [
    ['Public catalogue (OPAC)', route('library.opac')],
    ['Library collection', url('/library/browse')],
    ['BIBFRAME', url('/bibframe')],
    ['Work clustering', url('/frbr')],
    ['SRU explain record', url('/sru?operation=explain')],
  ];
@endphp
@extends('solutions._layout')

@section('intro')
  <p>{{ __('Heratio runs a library from the order to the shelf to the patron: acquisitions and serials, MARC21 cataloguing with copy cataloguing over Z39.50, and circulation with holds, fines and patron accounts.') }}</p>
  <p>{{ __('Because the library sits in the same system as the archive and the museum, a book, the letter tucked inside it and the object it describes can be found in one search and linked to each other.') }}</p>
@endsection
