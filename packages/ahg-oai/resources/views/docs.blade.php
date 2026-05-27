@extends('theme::layouts.2col')

@section('title', __('OAI-PMH Endpoint'))

@section('content')
<div class="container py-4">
  <h1>{{ __('OAI-PMH 2.0 Endpoint') }}</h1>
  <p class="lead">
    Heratio exposes archival metadata for harvesting via the
    <a href="https://www.openarchives.org/OAI/openarchivesprotocol.html" target="_blank" rel="noopener">OAI-PMH 2.0</a>
    protocol. Use this page to test the endpoint or build a harvester.
  </p>
  <p>
    <strong>Base URL:</strong>
    <code>{{ $baseUrl }}</code>
  </p>

  <h2 class="h4 mt-4">{{ __('Supported verbs') }}</h2>
  <ul>
    <li><code>Identify</code> — repository identification + admin contacts + linked friends</li>
    <li><code>ListMetadataFormats</code> — formats available for harvesting</li>
    <li><code>ListSets</code> — top-level archival collections offered as sets</li>
    <li><code>ListIdentifiers</code> — record identifiers + datestamps</li>
    <li><code>ListRecords</code> — full records in the selected format</li>
    <li><code>GetRecord</code> — single record by identifier</li>
  </ul>
  <p class="small text-muted">Each verb accepts both GET and POST as required by the OAI-PMH spec.</p>

  <h2 class="h4 mt-4">{{ __('Metadata formats') }}</h2>
  <table class="table table-sm table-striped">
    <thead><tr><th>{{ __('metadataPrefix') }}</th><th>{{ __('Name') }}</th><th>{{ __('Schema') }}</th></tr></thead>
    <tbody>
      <tr>
        <td><code>oai_dc</code></td>
        <td>Dublin Core (simple)</td>
        <td><a href="http://www.openarchives.org/OAI/2.0/oai_dc.xsd" target="_blank" rel="noopener">oai_dc.xsd</a></td>
      </tr>
      <tr>
        <td><code>oai_ead</code></td>
        <td>EAD 2002 (full hierarchy with descendants)</td>
        <td><a href="http://www.loc.gov/ead/ead.xsd" target="_blank" rel="noopener">ead.xsd</a></td>
      </tr>
      <tr>
        <td><code>oai_ead3</code></td>
        <td>EAD 3</td>
        <td><a href="https://www.loc.gov/ead/ead3.xsd" target="_blank" rel="noopener">ead3.xsd</a></td>
      </tr>
      <tr>
        <td><code>mods</code></td>
        <td>MODS 3.5</td>
        <td><a href="http://www.loc.gov/standards/mods/v3/mods-3-5.xsd" target="_blank" rel="noopener">mods-3-5.xsd</a></td>
      </tr>
      <tr>
        <td><code>marcxml</code></td>
        <td>MARC21 (in MARCXML slim envelope)</td>
        <td><a href="http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd" target="_blank" rel="noopener">MARC21slim.xsd</a></td>
      </tr>
    </tbody>
  </table>

  <h2 class="h4 mt-4">{{ __('Sample queries') }}</h2>
  <pre class="bg-light p-3 small"><code>{{ $baseUrl }}?verb=Identify
{{ $baseUrl }}?verb=ListMetadataFormats
{{ $baseUrl }}?verb=ListSets
{{ $baseUrl }}?verb=ListIdentifiers&amp;metadataPrefix=oai_dc
{{ $baseUrl }}?verb=ListRecords&amp;metadataPrefix=mods
{{ $baseUrl }}?verb=ListRecords&amp;metadataPrefix=oai_ead3&amp;from=2026-01-01
{{ $baseUrl }}?verb=GetRecord&amp;identifier=oai:host:LOCAL_ID&amp;metadataPrefix=marcxml</code></pre>

  <h2 class="h4 mt-4">{{ __('Deleted records') }}</h2>
  <p>
    The endpoint advertises <code>&lt;deletedRecord&gt;transient&lt;/deletedRecord&gt;</code>.
    Records that have been deleted or permanently unpublished are returned
    with <code>&lt;header status="deleted"&gt;</code> so harvesters can purge their copy.
  </p>

  <h2 class="h4 mt-4">{{ __('Rate limiting') }}</h2>
  <p>
    The endpoint is rate-limited to <strong>120 requests per minute per IP</strong>.
    Harvesters honouring <code>resumptionToken</code> pagination will not normally hit the limit.
  </p>

  <h2 class="h4 mt-4">{{ __('Compression') }}</h2>
  <p>
    Responses are gzipped at the nginx layer when the harvester sends
    <code>Accept-Encoding: gzip</code>. Set this header on your client to roughly halve transferred bytes.
  </p>

  <h2 class="h4 mt-4">{{ __('Authentication') }}</h2>
  <p>
    Anonymous harvesting is on by default. Operators can require API-key
    authentication by enabling <code>oai_authentication_enabled</code> in
    <em>Admin → AHG Settings → OAI-PMH</em>; clients then supply
    <code>X-API-Key</code> / <code>Authorization: Bearer</code> / <code>?api=</code>.
  </p>

  <p class="mt-4 small text-muted">
    Issue tracker: <a href="https://github.com/ArchiveHeritageGroup/heratio/issues/655" target="_blank" rel="noopener">#655</a>
  </p>
</div>
@endsection
