<?php

/**
 * DataciteEventsServiceTest - pure-payload structural tests for the
 * DataCite Events API client (issue #654 Phase 3).
 *
 * These tests exercise the parts of DataciteEventsService that do not
 * touch the database or the network - JSON-API payload shape, DOI
 * canonicalisation, and the relation-type mapping inside the listener.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgDoiManage\Events\DoiCitation;
use AhgDoiManage\Listeners\RegisterDoiEventsListener;
use AhgDoiManage\Services\DataciteEventsService;
use Tests\TestCase;

class DataciteEventsServiceTest extends TestCase
{
    private DataciteEventsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DataciteEventsService();
    }

    public function test_payload_has_required_json_api_envelope(): void
    {
        $body = $this->svc->buildPayload(
            subjectDoi: '10.5072/heratio.test.1',
            relationTypeId: 'unique-dataset-investigations-regular',
            objectId: '10.5072/heratio.test.1',
            objectIdType: 'doi',
            source: 'heratio-counter',
        );
        $this->assertSame('events', $body['data']['type']);
        $this->assertNotEmpty($body['data']['id']);
        $this->assertSame('unique-dataset-investigations-regular', $body['data']['attributes']['relation-type-id']);
        $this->assertSame('heratio-counter', $body['data']['attributes']['source-id']);
    }

    public function test_doi_is_canonicalised_to_https_uri(): void
    {
        $body = $this->svc->buildPayload(
            subjectDoi: 'doi:10.5072/heratio.test.1',
            relationTypeId: 'references',
            objectId: 'https://doi.org/10.1000/external',
            objectIdType: 'doi',
            source: 'heratio-archive',
        );
        $this->assertSame('https://doi.org/10.5072/heratio.test.1', $body['data']['attributes']['subj-id']);
        $this->assertSame('https://doi.org/10.1000/external', $body['data']['attributes']['obj-id']);
    }

    public function test_url_object_is_passed_through_unchanged(): void
    {
        $body = $this->svc->buildPayload(
            subjectDoi: '10.5072/heratio.test.1',
            relationTypeId: 'unique-dataset-investigations-regular',
            objectId: 'https://archive.example.org/show/abc',
            objectIdType: 'url',
            source: 'heratio-counter',
        );
        $this->assertSame('https://archive.example.org/show/abc', $body['data']['attributes']['obj-id']);
    }

    public function test_endpoint_defaults_to_production(): void
    {
        config(['datacite.test_mode' => false]);
        $this->assertSame('https://api.datacite.org', $this->svc->endpoint());
    }

    public function test_endpoint_uses_sandbox_when_test_mode_set(): void
    {
        config(['datacite.test_mode' => true]);
        $this->assertSame('https://api.test.datacite.org', $this->svc->endpoint());
    }

    public function test_listener_maps_relation_type_to_kebab(): void
    {
        $listener = new RegisterDoiEventsListener($this->svc);
        $event = new DoiCitation(
            subjectDoi: '10.5072/heratio.test.1',
            relatedIdentifier: '10.1000/related',
            relationType: 'IsReferencedBy',
            relatedIdentifierType: 'DOI',
        );
        // We can't easily intercept the register() call without a mock; reuse
        // the protected mapper via reflection to confirm the contract.
        $ref = new \ReflectionMethod($listener, 'mapRelationType');
        $ref->setAccessible(true);
        $this->assertSame('is-referenced-by', $ref->invoke($listener, 'IsReferencedBy'));
        $this->assertSame('is-part-of', $ref->invoke($listener, 'IsPartOf'));
        $this->assertSame('references', $ref->invoke($listener, 'References'));
    }
}
