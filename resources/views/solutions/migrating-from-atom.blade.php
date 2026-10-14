@php
  $capabilities = [
    ['Overlay onto your AtoM database', 'Heratio runs on top of the existing AtoM database. The installer works in eight repeatable stages and has a dry run, so you can see every change before it is made.', null],
    ['Nothing deleted', 'No AtoM row is deleted or rewritten. New tables and columns are added alongside, and your settings survive. To go back, point the web server at AtoM again.', null],
    ['Same records, same addresses', 'Descriptions, authority records, terms, repositories, accessions and their web addresses are read from the AtoM tables directly.', null],
    ['Users keep their passwords', 'Heratio checks AtoM\'s password scheme itself, so staff sign in with the credentials they already have.', null],
    ['Digital objects stay put', 'Existing uploads keep working where they are; new uploads can go to a new storage location.', null],
    ['One-command onboarding', 'Builds the derived layers in order: database updates, hierarchy tables, the full-text index, sector classification and browse facets.', null],
    ['CSV and EAD import', 'ISAD(G) CSV with match-and-update, per-sector CSV importers, EAD 2002 and EAD3 import, and a browser ingest wizard.', 'CSV, EAD 2002, EAD3'],
  ];
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
