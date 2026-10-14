@php
  $demoLinks = [
    ['Browse the demo collections', route('glam.browse')],
    ['Installation guide on GitHub', 'https://github.com/ArchiveHeritageGroup/heratio#installation'],
  ];
@endphp
@extends('solutions._layout')

@section('intro')
  <p>{{ __('If you run AtoM today, you do not have to migrate your data to try Heratio. Heratio can run over the AtoM database you already have, reading the same descriptions, authorities and digital objects, while adding the features the AtoM community has asked for, among them a storage tree, RiC, custom fields, bulk editing, an audit trail and a reading room.') }}</p>
  <p>{{ __('If you prefer a clean start, install Heratio fresh and bring your records across as CSV or EAD.') }}</p>
@endsection

@section('more')
  <h2 class="h4">{{ __('Before you start') }}</h2>
  <ul>
    <li>{{ __('The overlay needs MySQL 8, PHP 8.3, and a reference Heratio database on the same MySQL server, which it uses as the source of the schema.') }}</li>
    <li>{{ __('It does not configure your web server. Setting up the nginx site is a separate step.') }}</li>
    <li>{{ __('Single sign-on (OIDC, SAML or LDAP) is not provided yet. Users sign in with their AtoM usernames and passwords.') }}</li>
    <li>{{ __('Test on a copy of your database first. The dry run shows each change, but a copy lets you try the whole system before your users do.') }}</li>
  </ul>
@endsection
