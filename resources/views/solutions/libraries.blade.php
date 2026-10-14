@php
  $capabilities = [
    ['Cataloguing', 'Catalogue books and other items with authority control.', null],
    ['MARC21', 'Import MARCXML or binary MARC with a preview, then validate, merge, edit and download.', 'MARC21 (MARCXML, ISO 2709)'],
    ['Copy cataloguing', 'Search remote catalogues and import the record you need.', 'Z39.50'],
    ['Catalogue search for other systems', 'Other library systems can query your catalogue.', 'SRU'],
    ['ISBN lookup', 'Look up an ISBN, pre-fill the record and fetch the cover.', null],
    ['Circulation', 'Check out, return and renew at a scan desk, with loan rules, holds, overdues and fines, across branches.', null],
    ['Patron accounts', 'Staff manage patrons; patrons see their loans, holds and fines and renew online.', null],
    ['Acquisitions', 'Orders with a status workflow, fund splits, receiving, budgets and vendors, with EDI trading and ONIX ingest.', 'EDIFACT, ONIX'],
    ['Serials', 'Subscriptions, issue check-in and prediction, claims and bindery.', null],
    ['Interlibrary loan', 'Requests with a status workflow, EDI sending and a request form for patrons.', null],
    ['E-resources', 'Harvest and report usage, serve usage data, manage holdings and resolve links.', 'COUNTER R5, SUSHI, KBART, OpenURL'],
    ['Linked bibliographic data', 'View, import, export and validate BIBFRAME, with a graph editor.', 'BIBFRAME (RDF/XML)'],
    ['Work clustering', 'Group manifestations into works, with overrides when the grouping is wrong.', 'FRBR / LRM'],
    ['Research data', 'A dataset register with deposit, a privacy scan, data management plan links and a landing page per dataset.', null],
  ];
  $demoLinks = [
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
