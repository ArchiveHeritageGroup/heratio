<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\OnixIngestService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use ReflectionClass;

/**
 * Unit coverage for the ONIX parser + identifier validators (heratio#1094).
 * These paths are DB-free, so the service is built without its constructor
 * dependencies. The DB-backed duplicate check + commit pipeline are exercised
 * by the live rolled-back smoke test in the build session.
 */
class OnixIngestServiceTest extends AhgLibraryTestCase
{
    private OnixIngestService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = (new ReflectionClass(OnixIngestService::class))->newInstanceWithoutConstructor();
    }

    public function test_parses_onix_3_message_into_records(): void
    {
        $parsed = $this->svc->parse($this->loadFixture('sample-onix.xml'));

        $this->assertSame('3.0', $parsed['version']);
        $this->assertCount(2, $parsed['records']);

        $first = $parsed['records'][0];
        $this->assertSame('9780262033848', $first['isbn']);
        $this->assertSame('Introduction to Algorithms', $first['title']);
        $this->assertSame('Third Edition', $first['subtitle']);
        $this->assertSame('The MIT Press', $first['publisher']);
        $this->assertSame('2009', $first['pub_year']);
        $this->assertSame('3', $first['edition']);
        $this->assertSame('monograph', $first['material_type']);
        $this->assertSame(1299.00, $first['price']);
        $this->assertSame('ZAR', $first['currency']);
        $this->assertSame('Acme Distributors', $first['supplier']);
        $this->assertSame('Thomas H. Cormen; Charles E. Leiserson', $first['author']);
        $this->assertCount(2, $first['creators']);
        $this->assertSame('author', $first['creators'][0]['role']);
    }

    public function test_malformed_xml_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->svc->parse('<ONIXMessage><Product></ONIX'); // unterminated
    }

    public function test_empty_payload_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->svc->parse('   ');
    }

    public function test_isbn13_checksum(): void
    {
        $this->assertTrue($this->svc->isValidIsbn13('9780262033848'));
        $this->assertFalse($this->svc->isValidIsbn13('9780262033840')); // bad check digit
        $this->assertFalse($this->svc->isValidIsbn13('978026203384'));  // too short
    }

    public function test_isbn10_checksum(): void
    {
        $this->assertTrue($this->svc->isValidIsbn10('0262033844'));
        $this->assertTrue($this->svc->isValidIsbn10('080442957X'));     // X check digit
        $this->assertFalse($this->svc->isValidIsbn10('0262033840'));
    }

    public function test_issn_checksum(): void
    {
        $this->assertTrue($this->svc->isValidIssn('20493630'));
        $this->assertTrue($this->svc->isValidIssn('2434561X'));         // X check digit
        $this->assertFalse($this->svc->isValidIssn('20493631'));
    }

    public function test_validate_record_flags_missing_title_and_identifier(): void
    {
        // validateRecord short-circuits on title/identifier before any DB call.
        $noTitle = $this->svc->validateRecord(['title' => '', 'isbn' => '9780262033848']);
        $this->assertSame('invalid', $noTitle['status']);

        $noId = $this->svc->validateRecord(['title' => 'A Book', 'isbn' => null, 'issn' => null]);
        $this->assertSame('invalid', $noId['status']);

        $badIsbn = $this->svc->validateRecord(['title' => 'A Book', 'isbn' => '9780262033840']);
        $this->assertSame('invalid', $badIsbn['status']);
        $this->assertStringContainsString('checksum', $badIsbn['error']);
    }

    public function test_contributor_role_and_form_mapping(): void
    {
        $parsed = $this->svc->parse(<<<'XML'
        <ONIXMessage release="3.0">
          <Product>
            <RecordReference>r1</RecordReference>
            <ProductIdentifier><ProductIDType>02</ProductIDType><IDValue>0262033844</IDValue></ProductIdentifier>
            <DescriptiveDetail>
              <ProductForm>DG</ProductForm>
              <TitleDetail><TitleElement><TitleText>Ebook Title</TitleText></TitleElement></TitleDetail>
              <Contributor><ContributorRole>B01</ContributorRole><PersonName>Edna Editor</PersonName></Contributor>
            </DescriptiveDetail>
          </Product>
        </ONIXMessage>
        XML);

        $rec = $parsed['records'][0];
        $this->assertSame('ebook', $rec['material_type']);          // ProductForm DG -> ebook
        $this->assertSame('editor', $rec['creators'][0]['role']);   // ContributorRole B01 -> editor
        $this->assertSame('0262033844', $rec['isbn']);              // ISBN-10 fallback
    }
}
