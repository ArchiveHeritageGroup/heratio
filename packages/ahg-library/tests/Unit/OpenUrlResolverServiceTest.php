<?php

/**
 * OpenUrlResolverServiceTest - unit tests for OpenUrlResolverService.
 *
 * Tests cover the pure (database-free) layers:
 *   - parseContext: KEV field mapping (dotted + underscore variants)
 *   - rft_id identifier decoding (urn:isbn, urn:issn, info:doi, oai)
 *   - title/author fallbacks
 *   - ISBN/ISSN normalisation
 *   - buildContextObjectXml structure + candidate elements
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\OpenUrlResolverService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use ReflectionClass;

class OpenUrlResolverServiceTest extends AhgLibraryTestCase
{
    private OpenUrlResolverService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = (new ReflectionClass(OpenUrlResolverService::class))->newInstanceWithoutConstructor();
    }

    public function test_parses_basic_kev_fields(): void
    {
        $ctx = $this->svc->parseContext([
            'rft.title' => 'Introduction to Algorithms',
            'rft.au'    => 'Cormen, Thomas',
            'rft.isbn'  => '978-0-262-03384-8',
            'rft.date'  => '2009',
            'rft.pub'   => 'The MIT Press',
        ]);

        $this->assertSame('Introduction to Algorithms', $ctx['title']);
        $this->assertSame('Cormen, Thomas', $ctx['author']);
        $this->assertSame('9780262033848', $ctx['isbn']);
        $this->assertSame('2009', $ctx['date']);
        $this->assertSame('The MIT Press', $ctx['publisher']);
    }

    public function test_accepts_underscore_variants(): void
    {
        $ctx = $this->svc->parseContext([
            'rft_title' => 'Networks',
            'rft_issn'  => '12345678',
        ]);

        $this->assertSame('Networks', $ctx['title']);
        $this->assertSame('1234-5678', $ctx['issn']);
    }

    public function test_rft_id_isbn_doi_issn_decoding(): void
    {
        $ctx = $this->svc->parseContext([
            'rft_id' => [
                'urn:isbn:9780262033848',
                'info:doi/10.1000/xyz123',
            ],
        ]);

        $this->assertSame('9780262033848', $ctx['isbn']);
        $this->assertSame('10.1000/xyz123', $ctx['doi']);

        $ctx2 = $this->svc->parseContext(['rft_id' => 'urn:issn:0028-0836']);
        $this->assertSame('0028-0836', $ctx2['issn']);

        $ctx3 = $this->svc->parseContext(['rft_id' => 'info:oai/oai:repo:42']);
        $this->assertSame('oai:repo:42', $ctx3['oai_id']);
    }

    public function test_title_fallback_to_jtitle_then_btitle(): void
    {
        $ctx = $this->svc->parseContext(['rft.jtitle' => 'Nature']);
        $this->assertSame('Nature', $ctx['title']);

        $ctx2 = $this->svc->parseContext(['rft.btitle' => 'A Book']);
        $this->assertSame('A Book', $ctx2['title']);
    }

    public function test_author_fallback_from_aulast_aufirst(): void
    {
        $ctx = $this->svc->parseContext([
            'rft.aulast'  => 'Knuth',
            'rft.aufirst' => 'Donald',
        ]);

        $this->assertSame('Knuth, Donald', $ctx['author']);
    }

    public function test_normalise_isbn_strips_separators(): void
    {
        $this->assertSame('9780262033848', $this->svc->normaliseIsbn('978-0-262-03384-8'));
        $this->assertSame('080442957X', $this->svc->normaliseIsbn('0-8044-2957-x'));
    }

    public function test_normalise_issn_inserts_hyphen(): void
    {
        $this->assertSame('0028-0836', $this->svc->normaliseIssn('00280836'));
        $this->assertSame('0028-0836', $this->svc->normaliseIssn('0028-0836'));
    }

    public function test_empty_params_produce_empty_context(): void
    {
        $this->assertSame([], $this->svc->parseContext([]));
        $this->assertSame([], $this->svc->parseContext(['rft.title' => '   ']));
    }

    public function test_build_context_object_xml_contains_metadata_and_candidates(): void
    {
        $ctx = ['title' => 'Foo & Bar', 'isbn' => '9780262033848'];
        $candidates = [
            (object) ['library_item_id' => 7, 'title' => 'Foo & Bar', 'slug' => 'foo-bar'],
        ];

        $xml = $this->svc->buildContextObjectXml($ctx, $candidates);

        $this->assertStringContainsString('<ctx:context-objects', $xml);
        $this->assertStringContainsString('Foo &amp; Bar', $xml);
        $this->assertStringContainsString('9780262033848', $xml);
        $this->assertStringContainsString('matches="1"', $xml);
        $this->assertStringContainsString('foo-bar', $xml);

        // Must be well-formed XML.
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }

    public function test_build_context_object_xml_zero_candidates(): void
    {
        $xml = $this->svc->buildContextObjectXml(['title' => 'Nothing'], []);
        $this->assertStringContainsString('matches="0"', $xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
    }
}
