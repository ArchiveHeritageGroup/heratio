<?php

/**
 * DoiServicePhase2Test - structural tests for the four Phase 2 (#654)
 * DataCite enrichment blocks: nameIdentifiers (ORCID), relatedIdentifiers,
 * geoLocations, and fundingReferences.
 *
 * These tests operate on the pure XML serialiser (DoiService::buildXml)
 * and the ORCID normaliser - they do NOT hit the database. DB-backed
 * builders (buildCreators / buildRelatedIdentifiers / etc.) are covered
 * indirectly when smoke-run against a real IO; the XML assertions here
 * are the structural contract DataCite consumes.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgDoiManage\Services\DoiService;
use Tests\TestCase;

class DoiServicePhase2Test extends TestCase
{
    private DoiService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DoiService();
    }

    private function basePayload(array $extraAttrs = []): array
    {
        return [
            'data' => [
                'id' => '10.1234/test.1',
                'type' => 'dois',
                'attributes' => array_merge([
                    'doi' => '10.1234/test.1',
                    'titles' => [['title' => 'Sample record']],
                    'creators' => [['name' => 'Smith, Jane']],
                    'publisher' => 'Test Archive',
                    'publicationYear' => 2026,
                    'types' => ['resourceTypeGeneral' => 'Text'],
                    'url' => 'https://example.org/io/1',
                    'event' => 'publish',
                ], $extraAttrs),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // ORCID normalisation - feeds the nameIdentifiers block
    // ------------------------------------------------------------------

    public function test_orcid_normalises_valid_form(): void
    {
        $this->assertSame('0000-0002-1825-0097', DoiService::normaliseOrcid('0000-0002-1825-0097'));
        $this->assertSame('0000-0002-1825-0097', DoiService::normaliseOrcid('0000000218250097'));
        $this->assertSame('0000-0002-1825-0097', DoiService::normaliseOrcid('https://orcid.org/0000-0002-1825-0097'));
    }

    public function test_orcid_accepts_terminal_x_checksum(): void
    {
        $this->assertSame('0000-0001-5109-3700', DoiService::normaliseOrcid('0000-0001-5109-3700'));
        $this->assertSame('0000-0002-1694-233X', DoiService::normaliseOrcid('0000-0002-1694-233X'));
        $this->assertSame('0000-0002-1694-233X', DoiService::normaliseOrcid('0000-0002-1694-233x'));
    }

    public function test_orcid_drops_malformed_silently(): void
    {
        $this->assertNull(DoiService::normaliseOrcid(null));
        $this->assertNull(DoiService::normaliseOrcid(''));
        $this->assertNull(DoiService::normaliseOrcid('not-an-orcid'));
        $this->assertNull(DoiService::normaliseOrcid('1234'));
        $this->assertNull(DoiService::normaliseOrcid('0000-0002-1825-009Y'));  // Y invalid
    }

    // ------------------------------------------------------------------
    // Creator-ORCID block - nameIdentifiers emitted in XML
    // ------------------------------------------------------------------

    public function test_xml_emits_creator_nameIdentifiers_for_orcid(): void
    {
        $payload = $this->basePayload([
            'creators' => [[
                'name' => 'Smith, Jane',
                'nameType' => 'Personal',
                'nameIdentifiers' => [[
                    'nameIdentifier' => '0000-0002-1825-0097',
                    'nameIdentifierScheme' => 'ORCID',
                    'schemeURI' => 'https://orcid.org/',
                ]],
            ]],
        ]);
        $xml = $this->svc->buildXml($payload);

        $this->assertStringContainsString('<creator>', $xml);
        $this->assertStringContainsString('<creatorName nameType="Personal">Smith, Jane</creatorName>', $xml);
        $this->assertStringContainsString('nameIdentifierScheme="ORCID"', $xml);
        $this->assertStringContainsString('schemeURI="https://orcid.org/"', $xml);
        $this->assertStringContainsString('>0000-0002-1825-0097</nameIdentifier>', $xml);
        $this->assertWellFormedXml($xml);
    }

    // ------------------------------------------------------------------
    // relatedIdentifiers block
    // ------------------------------------------------------------------

    public function test_xml_emits_relatedIdentifiers_block(): void
    {
        $payload = $this->basePayload([
            'relatedIdentifiers' => [
                [
                    'relatedIdentifier' => '10.1234/parent.99',
                    'relatedIdentifierType' => 'DOI',
                    'relationType' => 'IsPartOf',
                ],
                [
                    'relatedIdentifier' => 'https://example.org/io/1/digitalobjects',
                    'relatedIdentifierType' => 'URL',
                    'relationType' => 'IsVariantFormOf',
                ],
                [
                    'relatedIdentifier' => 'https://example.org/exhibitions/7',
                    'relatedIdentifierType' => 'URL',
                    'relationType' => 'IsReferencedBy',
                ],
            ],
        ]);
        $xml = $this->svc->buildXml($payload);

        $this->assertStringContainsString('<relatedIdentifiers>', $xml);
        $this->assertStringContainsString('relatedIdentifierType="DOI"', $xml);
        $this->assertStringContainsString('relationType="IsPartOf"', $xml);
        $this->assertStringContainsString('relationType="IsVariantFormOf"', $xml);
        $this->assertStringContainsString('relationType="IsReferencedBy"', $xml);
        $this->assertStringContainsString('>10.1234/parent.99</relatedIdentifier>', $xml);
        $this->assertWellFormedXml($xml);
    }

    // ------------------------------------------------------------------
    // geoLocations block - point, box, polygon all supported
    // ------------------------------------------------------------------

    public function test_xml_emits_geoLocations_point_box_polygon(): void
    {
        $payload = $this->basePayload([
            'geoLocations' => [
                [
                    'geoLocationPlace' => 'Cape Town',
                    'geoLocationPoint' => ['pointLongitude' => 18.4241, 'pointLatitude' => -33.9249],
                ],
                [
                    'geoLocationPlace' => 'Western Cape',
                    'geoLocationBox' => [
                        'westBoundLongitude' => 17.0,
                        'eastBoundLongitude' => 23.0,
                        'southBoundLatitude' => -34.8,
                        'northBoundLatitude' => -30.5,
                    ],
                ],
                [
                    'geoLocationPlace' => 'Custom Region',
                    'geoLocationPolygon' => [
                        'polygonPoints' => [
                            ['pointLongitude' => 18.0, 'pointLatitude' => -33.0],
                            ['pointLongitude' => 19.0, 'pointLatitude' => -33.0],
                            ['pointLongitude' => 19.0, 'pointLatitude' => -34.0],
                            ['pointLongitude' => 18.0, 'pointLatitude' => -33.0],
                        ],
                    ],
                ],
            ],
        ]);
        $xml = $this->svc->buildXml($payload);

        $this->assertStringContainsString('<geoLocations>', $xml);
        $this->assertStringContainsString('<geoLocationPlace>Cape Town</geoLocationPlace>', $xml);
        $this->assertStringContainsString('<geoLocationPoint>', $xml);
        $this->assertStringContainsString('<pointLongitude>18.4241</pointLongitude>', $xml);
        $this->assertStringContainsString('<geoLocationBox>', $xml);
        $this->assertStringContainsString('<westBoundLongitude>17</westBoundLongitude>', $xml);
        $this->assertStringContainsString('<geoLocationPolygon>', $xml);
        $this->assertStringContainsString('<polygonPoint>', $xml);
        $this->assertWellFormedXml($xml);
    }

    // ------------------------------------------------------------------
    // fundingReferences block
    // ------------------------------------------------------------------

    public function test_xml_emits_fundingReferences_block(): void
    {
        $payload = $this->basePayload([
            'fundingReferences' => [
                [
                    'funderName' => 'National Research Foundation',
                    'funderIdentifier' => 'https://ror.org/05bjb6e90',
                    'funderIdentifierType' => 'ROR',
                    'awardNumber' => 'NRF-2026-001',
                    'awardURI' => 'https://nrf.example.org/grants/NRF-2026-001',
                    'awardTitle' => 'Digital preservation of southern African archives',
                ],
                [
                    'funderName' => 'Anonymous Donor',
                ],
            ],
        ]);
        $xml = $this->svc->buildXml($payload);

        $this->assertStringContainsString('<fundingReferences>', $xml);
        $this->assertStringContainsString('<funderName>National Research Foundation</funderName>', $xml);
        $this->assertStringContainsString('funderIdentifierType="ROR"', $xml);
        $this->assertStringContainsString('>https://ror.org/05bjb6e90</funderIdentifier>', $xml);
        $this->assertStringContainsString('awardURI="https://nrf.example.org/grants/NRF-2026-001"', $xml);
        $this->assertStringContainsString('>NRF-2026-001</awardNumber>', $xml);
        $this->assertStringContainsString('<awardTitle>Digital preservation of southern African archives</awardTitle>', $xml);
        $this->assertStringContainsString('<funderName>Anonymous Donor</funderName>', $xml);
        $this->assertWellFormedXml($xml);
    }

    // ------------------------------------------------------------------
    // helper
    // ------------------------------------------------------------------

    private function assertWellFormedXml(string $xml): void
    {
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $this->assertNotFalse($doc, 'XML failed to parse: '.($errors[0]->message ?? 'unknown'));
        $this->assertEmpty($errors, 'XML parse warnings: '.json_encode(array_map(fn ($e) => $e->message, $errors)));
    }
}
