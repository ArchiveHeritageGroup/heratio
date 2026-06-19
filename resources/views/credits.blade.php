@extends('theme::layouts.1col')
@section('title', 'Credits & Open-source Licenses')
@section('body-class', 'credits')

@section('content')
<div class="row justify-content-center">
  <div class="col-lg-9">
    <h1 class="mb-3"><i class="fas fa-heart me-2"></i>{{ __('Credits & Open-source Licenses') }}</h1>
    <p class="text-muted">
      Heratio is an independent archival management platform that stands on
      substantial work from the wider archival, museum, and open-source
      community. This page records that debt and the licenses under which that
      work is used. The full record is in
      <a href="{{ $sourceUrl }}/blob/main/ACKNOWLEDGMENTS.md" target="_blank" rel="noopener">ACKNOWLEDGMENTS.md</a>.
    </p>

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('AtoM (Access to Memory) - Artefactual Systems Inc. and the AtoM community') }}</div>
      <div class="card-body">
        <p>
          Heratio's archival data model and descriptive-standards structure derive
          from <strong>AtoM (Access to Memory)</strong>, the open-source archival
          description system originally created by
          <a href="https://www.artefactual.com" target="_blank" rel="noopener">Artefactual Systems Inc.</a>
          under contract to the International Council on Archives
          (Copyright &copy; 2006-2014 Artefactual Systems Inc.).
        </p>
        <p class="mb-1">Specifically, Heratio incorporates from AtoM:</p>
        <ul class="mb-2">
          <li>the Qubit database schema - <code>information_object</code>, <code>actor</code>,
              <code>repository</code>, <code>digital_object</code>, <code>event</code>,
              <code>relation</code>, the <code>*_i18n</code> translation tables, and the
              <code>lft</code>/<code>rgt</code> nested-set hierarchy;</li>
          <li>the rendering of the ISAD(G), ISAAR(CPF), ISDIAH and ISDF description standards;</li>
          <li>the bulk of the UI string translations across 49 locales, contributed by
              hundreds of community translators via Artefactual's translation workflow
              (<a href="https://www.transifex.com/artefactual/atom/" target="_blank" rel="noopener">transifex.com/artefactual/atom</a>).</li>
        </ul>
        <p class="mb-0">
          AtoM is licensed under the <strong>GNU Affero General Public License v3.0</strong>.
          Heratio is an independent Laravel re-implementation - not a fork, and it
          contains no AtoM source code - but its data model and standards work derive
          directly from AtoM's design, gratefully acknowledged here.
          See <a href="https://www.accesstomemory.org" target="_blank" rel="noopener">accesstomemory.org</a>.
        </p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('International Council on Archives - standards & Records in Contexts') }}</div>
      <div class="card-body mb-0">
        <p class="mb-0">
          Heratio implements the descriptive standards stewarded by the
          <a href="https://www.ica.org" target="_blank" rel="noopener">International Council on Archives</a>,
          including the Records in Contexts (RiC) conceptual model and ontology.
          We thank the ICA and its Experts Group on Archival Description.
        </p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Open-source software') }}</div>
      <div class="card-body mb-0">
        <p class="mb-0">
          Heratio is built on the Laravel framework and many open-source libraries
          (Elasticsearch, OpenSeadragon, Cantaloupe, and others), each used under its
          own license. Full dependency licenses are listed in the project's
          <code>composer.json</code>, <code>package.json</code>, and vendor metadata.
        </p>
      </div>
    </div>

    <div class="card mb-3 border-secondary">
      <div class="card-header bg-light">{{ __('Heratio license & source code') }}</div>
      <div class="card-body mb-0">
        <p>
          Heratio &copy; The Archive and Heritage Group (Pty) Ltd / Plain Sailing
          Information Systems. Heratio is free software, licensed under the
          <strong>GNU Affero General Public License v3.0</strong>. It is distributed
          in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
          the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
        </p>
        <p class="mb-0">
          In accordance with the AGPL, the complete corresponding source code for
          this running instance is available at
          <a href="{{ $sourceUrl }}" target="_blank" rel="noopener">{{ $sourceUrl }}</a>,
          and the full license text is in the
          <a href="{{ $sourceUrl }}/blob/main/LICENSE" target="_blank" rel="noopener">LICENSE</a> file.
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
