<?php

/*
 * AlmaConnectorTest - Http::fake MARCXML parsing assertions for the Ex Libris
 * Alma SRU federated-search connector (#1330). No DB, no network: deterministic
 * and CI-safe.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgFederation\Tests;

use AhgFederation\Connectors\AlmaConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlmaConnectorTest extends TestCase
{
    private function peer(array $config = [], string $baseUrl = 'https://bc.alma.exlibrisgroup.com/view/sru/01BC_INST'): object
    {
        return (object) [
            'base_url' => $baseUrl,
            'peer_name' => 'Boston College',
            'peer_id' => 9,
            'timeout_ms' => 5000,
            'config' => $config ? json_encode($config) : null,
        ];
    }

    private function sruXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<searchRetrieveResponse xmlns="http://www.loc.gov/zing/srw/">
  <version>1.2</version>
  <numberOfRecords>2</numberOfRecords>
  <records>
    <record>
      <recordSchema>marcxml</recordSchema>
      <recordData>
        <record xmlns="http://www.loc.gov/MARC21/slim">
          <controlfield tag="001">990001</controlfield>
          <controlfield tag="008">120610s2010    xxu      b    000 0 eng d</controlfield>
          <datafield ind1="1" ind2="0" tag="245"><subfield code="a">Africa</subfield><subfield code="b">a history /</subfield></datafield>
          <datafield ind1="1" ind2=" " tag="100"><subfield code="a">Doe, Jane.</subfield></datafield>
          <datafield ind1=" " ind2="1" tag="264"><subfield code="c">2010.</subfield></datafield>
          <datafield ind1=" " ind2=" " tag="020"><subfield code="a">9781234567890</subfield></datafield>
          <datafield ind1=" " ind2=" " tag="520"><subfield code="a">A short summary.</subfield></datafield>
        </record>
      </recordData>
    </record>
    <record>
      <recordData>
        <record xmlns="http://www.loc.gov/MARC21/slim">
          <controlfield tag="001">990002</controlfield>
          <controlfield tag="008">990610s1999    xxu           000 0 eng d</controlfield>
          <datafield ind1="1" ind2="0" tag="245"><subfield code="a">Solo Title</subfield></datafield>
        </record>
      </recordData>
    </record>
  </records>
</searchRetrieveResponse>
XML;
    }

    public function test_parses_sru_marcxml(): void
    {
        Http::fake(['*' => Http::response($this->sruXml(), 200, ['Content-Type' => 'application/xml'])]);

        $c = new AlmaConnector;
        $c->bind($this->peer());
        $rows = $c->search('africa history', [], 10);

        $this->assertCount(2, $rows);

        $first = $rows[0];
        $this->assertSame('990001', $first->sourceId);
        $this->assertSame('Africa a history', $first->title); // 245 a+b, trailing ISBD " /" stripped
        $this->assertSame('A short summary.', $first->snippet);
        $this->assertSame('alma', $first->peerType);
        $this->assertSame('2010', $first->date);              // 264 $c "2010." -> year
        $this->assertSame('Doe, Jane', $first->extras['author']); // trailing "." stripped
        $this->assertSame('9781234567890', $first->extras['isbn']);
        $this->assertSame('990001', $first->extras['mms_id']);
        $this->assertSame('mms:990001', $first->dedupeKey);
        $this->assertEqualsWithDelta(1.0, $first->score, 0.001);

        $second = $rows[1];
        $this->assertSame('Solo Title', $second->title);
        $this->assertSame('1999', $second->date);             // from 008 positions 07-10
        $this->assertNull($second->extras['author']);
    }

    public function test_uses_record_url_template_when_configured(): void
    {
        Http::fake(['*' => Http::response($this->sruXml(), 200)]);

        $c = new AlmaConnector;
        $c->bind($this->peer(['record_url_template' => 'https://primo.example.org/discovery/fulldisplay?docid=alma{mms_id}&vid=X']));
        $rows = $c->search('africa', [], 5);

        $this->assertSame('https://primo.example.org/discovery/fulldisplay?docid=alma990001&vid=X', $rows[0]->url);
    }

    public function test_builds_sru_search_request(): void
    {
        Http::fake(['*' => Http::response($this->sruXml(), 200)]);

        $c = new AlmaConnector;
        $c->bind($this->peer());
        $c->search('africa', [], 5);

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'operation=searchRetrieve')
                && str_contains($url, 'recordSchema=marcxml')
                && str_contains(urldecode($url), 'alma.all_for_ui="africa"');
        });
    }

    public function test_ssrf_blocked_host_returns_empty(): void
    {
        Http::fake(['*' => Http::response($this->sruXml(), 200)]);

        $c = new AlmaConnector;
        $c->bind($this->peer([], 'http://169.254.169.254/view/sru/X'));
        $this->assertSame([], $c->search('anything', [], 5));
        Http::assertNothingSent();
    }

    public function test_non_200_and_empty_query_degrade_to_empty(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);
        $c = new AlmaConnector;
        $c->bind($this->peer());
        $this->assertSame([], $c->search('africa', [], 5));

        // Empty query never calls out.
        Http::fake(['*' => Http::response($this->sruXml(), 200)]);
        $c2 = new AlmaConnector;
        $c2->bind($this->peer());
        $this->assertSame([], $c2->search('   ', [], 5));
    }
}
