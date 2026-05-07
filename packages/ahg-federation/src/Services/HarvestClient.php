<?php

/**
 * HarvestClient - OAI-PMH harvest client for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgFederation\Services;

class HarvestClient
{
    protected string $userAgent = 'Heratio-Federation-Harvester/1.0';
    protected int $timeout = 60;
    protected int $maxRetries = 3;
    protected int $retryDelay = 5;

    public function identify(string $baseUrl): array
    {
        $response = $this->request($baseUrl, ['verb' => 'Identify']);
        $xml = $this->parseResponse($response);

        $identify = $xml->Identify;
        if (!$identify) {
            throw new HarvestException('Invalid Identify response');
        }

        return [
            'repositoryName' => (string) $identify->repositoryName,
            'baseURL' => (string) $identify->baseURL,
            'protocolVersion' => (string) $identify->protocolVersion,
            'adminEmail' => (string) $identify->adminEmail,
            'earliestDatestamp' => (string) $identify->earliestDatestamp,
            'deletedRecord' => (string) $identify->deletedRecord,
            'granularity' => (string) $identify->granularity,
            'compression' => isset($identify->compression) ? (array) $identify->compression : [],
            'description' => isset($identify->description) ? $this->parseDescription($identify->description) : null,
        ];
    }

    public function listMetadataFormats(string $baseUrl, ?string $identifier = null): array
    {
        $params = ['verb' => 'ListMetadataFormats'];
        if ($identifier) {
            $params['identifier'] = $identifier;
        }

        $response = $this->request($baseUrl, $params);
        $xml = $this->parseResponse($response);

        $formats = [];
        foreach ($xml->ListMetadataFormats->metadataFormat as $format) {
            $formats[] = [
                'metadataPrefix' => (string) $format->metadataPrefix,
                'schema' => (string) $format->schema,
                'metadataNamespace' => (string) $format->metadataNamespace,
            ];
        }

        return $formats;
    }

    public function listSets(string $baseUrl): array
    {
        $sets = [];
        $resumptionToken = null;

        do {
            $params = $resumptionToken
                ? ['verb' => 'ListSets', 'resumptionToken' => $resumptionToken]
                : ['verb' => 'ListSets'];

            $response = $this->request($baseUrl, $params);
            $xml = $this->parseResponse($response);

            if (isset($xml->error)) {
                $errorCode = (string) $xml->error['code'];
                if ($errorCode === 'noSetHierarchy') {
                    return [];
                }
                throw new HarvestException("OAI error: $errorCode - " . (string) $xml->error);
            }

            foreach ($xml->ListSets->set as $set) {
                $sets[] = [
                    'setSpec' => (string) $set->setSpec,
                    'setName' => (string) $set->setName,
                    'setDescription' => isset($set->setDescription)
                        ? (string) $set->setDescription->children('http://purl.org/dc/elements/1.1/')->description
                        : null,
                ];
            }

            $resumptionToken = isset($xml->ListSets->resumptionToken)
                ? (string) $xml->ListSets->resumptionToken
                : null;
        } while ($resumptionToken);

        return $sets;
    }

    public function listRecords(string $baseUrl, array $params): \Generator
    {
        $requestParams = [
            'verb' => 'ListRecords',
            'metadataPrefix' => $params['metadataPrefix'] ?? 'oai_dc',
        ];
        foreach (['from', 'until', 'set'] as $opt) {
            if (!empty($params[$opt])) {
                $requestParams[$opt] = $params[$opt];
            }
        }

        $resumptionToken = null;

        do {
            if ($resumptionToken) {
                $requestParams = ['verb' => 'ListRecords', 'resumptionToken' => $resumptionToken];
            }

            $response = $this->request($baseUrl, $requestParams);
            $xml = $this->parseResponse($response);

            if (isset($xml->error)) {
                $errorCode = (string) $xml->error['code'];
                if ($errorCode === 'noRecordsMatch') {
                    return;
                }
                throw new HarvestException("OAI error: $errorCode - " . (string) $xml->error);
            }

            foreach ($xml->ListRecords->record as $record) {
                yield $this->parseRecord($record, $params['metadataPrefix'] ?? 'oai_dc');
            }

            $resumptionToken = isset($xml->ListRecords->resumptionToken)
                ? (string) $xml->ListRecords->resumptionToken
                : null;
        } while ($resumptionToken);
    }

    public function listIdentifiers(string $baseUrl, array $params): \Generator
    {
        $requestParams = [
            'verb' => 'ListIdentifiers',
            'metadataPrefix' => $params['metadataPrefix'] ?? 'oai_dc',
        ];
        foreach (['from', 'until', 'set'] as $opt) {
            if (!empty($params[$opt])) {
                $requestParams[$opt] = $params[$opt];
            }
        }

        $resumptionToken = null;

        do {
            if ($resumptionToken) {
                $requestParams = ['verb' => 'ListIdentifiers', 'resumptionToken' => $resumptionToken];
            }

            $response = $this->request($baseUrl, $requestParams);
            $xml = $this->parseResponse($response);

            if (isset($xml->error)) {
                $errorCode = (string) $xml->error['code'];
                if ($errorCode === 'noRecordsMatch') {
                    return;
                }
                throw new HarvestException("OAI error: $errorCode - " . (string) $xml->error);
            }

            foreach ($xml->ListIdentifiers->header as $header) {
                yield $this->parseHeader($header);
            }

            $resumptionToken = isset($xml->ListIdentifiers->resumptionToken)
                ? (string) $xml->ListIdentifiers->resumptionToken
                : null;
        } while ($resumptionToken);
    }

    public function getRecord(string $baseUrl, string $identifier, string $metadataPrefix): array
    {
        $response = $this->request($baseUrl, [
            'verb' => 'GetRecord',
            'identifier' => $identifier,
            'metadataPrefix' => $metadataPrefix,
        ]);

        $xml = $this->parseResponse($response);

        if (isset($xml->error)) {
            $errorCode = (string) $xml->error['code'];
            throw new HarvestException("OAI error: $errorCode - " . (string) $xml->error);
        }

        return $this->parseRecord($xml->GetRecord->record, $metadataPrefix);
    }

    protected function request(string $baseUrl, array $params): string
    {
        $url = $baseUrl . '?' . http_build_query($params);

        // SSRF protection: block cloud metadata endpoints + private/reserved IPs.
        $parsed = parse_url($baseUrl);
        $host = $parsed['host'] ?? '';

        $blockedHosts = ['169.254.169.254', 'metadata.google.internal', 'metadata.internal'];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            throw new HarvestException('Blocked host (metadata endpoint): ' . $host);
        }

        $resolvedIps = @gethostbynamel($host);
        if ($resolvedIps !== false) {
            foreach ($resolvedIps as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    throw new HarvestException('OAI endpoint resolves to private/reserved IP: ' . $ip);
                }
            }
        }

        $ch = curl_init();
        $port = $parsed['port'] ?? (($parsed['scheme'] ?? 'https') === 'https' ? 443 : 80);

        $curlOpts = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_MAXFILESIZE => 50 * 1024 * 1024,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: text/xml, application/xml'],
        ];

        // Pin resolved IP to defeat DNS rebinding attacks.
        if ($resolvedIps !== false && !empty($resolvedIps)) {
            $curlOpts[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $resolvedIps[0]];
        }

        curl_setopt_array($ch, $curlOpts);

        $retries = 0;
        $response = false;
        $error = '';

        while ($retries < $this->maxRetries) {
            $response = curl_exec($ch);

            if ($response !== false) {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode === 200) {
                    break;
                }
                if ($httpCode === 503) {
                    $retries++;
                    sleep($this->retryDelay * $retries);
                    continue;
                }
                curl_close($ch);
                throw new HarvestException("HTTP error: $httpCode for URL: $url");
            }

            $error = curl_error($ch);
            $retries++;
            sleep($this->retryDelay * $retries);
        }

        curl_close($ch);

        if ($response === false) {
            throw new HarvestException("Failed to fetch: $url - $error");
        }

        return $response;
    }

    protected function parseResponse(string $response): \SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new HarvestException('Failed to parse XML response: ' . $this->formatXmlErrors($errors));
        }

        $xml->registerXPathNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');

        return $xml;
    }

    protected function parseRecord(\SimpleXMLElement $record, string $metadataPrefix): array
    {
        $header = $this->parseHeader($record->header);

        $result = [
            'header' => $header,
            'metadata' => null,
            'about' => null,
        ];

        if ($header['status'] === 'deleted') {
            return $result;
        }

        if (isset($record->metadata)) {
            $result['metadata'] = $this->parseMetadata($record->metadata, $metadataPrefix);
            $result['rawMetadata'] = $record->metadata->asXML();
        }

        if (isset($record->about)) {
            $result['about'] = $record->about->asXML();
        }

        return $result;
    }

    protected function parseHeader(\SimpleXMLElement $header): array
    {
        return [
            'identifier' => (string) $header->identifier,
            'datestamp' => (string) $header->datestamp,
            'setSpec' => isset($header->setSpec) ? array_map('strval', iterator_to_array($header->setSpec)) : [],
            'status' => isset($header['status']) ? (string) $header['status'] : 'active',
        ];
    }

    protected function parseMetadata(\SimpleXMLElement $metadata, string $metadataPrefix): array
    {
        switch ($metadataPrefix) {
            case 'oai_dc':
                return $this->parseDublinCore($metadata);
            case 'oai_heritage':
                return $this->parseHeritage($metadata);
            case 'oai_ead':
                return $this->parseEad($metadata);
            default:
                return ['raw' => $metadata->asXML()];
        }
    }

    protected function parseDublinCore(\SimpleXMLElement $metadata): array
    {
        $dc = $metadata->children('http://www.openarchives.org/OAI/2.0/oai_dc/');
        if (!$dc->dc) {
            $dc = $metadata->children('http://purl.org/dc/elements/1.1/');
        }

        $result = [];
        foreach ($dc->children('http://purl.org/dc/elements/1.1/') as $element) {
            $name = $element->getName();
            if (!isset($result[$name])) {
                $result[$name] = [];
            }
            $result[$name][] = (string) $element;
        }

        return $result;
    }

    protected function parseHeritage(\SimpleXMLElement $metadata): array
    {
        $ns = 'https://heritage.example.org/oai/heritage/';
        $heritage = $metadata->children($ns);

        $result = [
            'identifier' => (string) $heritage->identifier,
            'title' => (string) $heritage->title,
            'description' => (string) $heritage->description,
            'levelOfDescription' => (string) $heritage->levelOfDescription,
            'extent' => (string) $heritage->extent,
            'referenceCode' => (string) $heritage->referenceCode,
            'accessConditions' => (string) $heritage->accessConditions,
            'provenance' => (string) $heritage->provenance,
            'publicationStatus' => (string) $heritage->publicationStatus,
            'parentIdentifier' => (string) $heritage->parentIdentifier,
            'collectionIdentifier' => (string) $heritage->collectionIdentifier,
            'createdAt' => (string) $heritage->createdAt,
            'updatedAt' => (string) $heritage->updatedAt,
        ];

        if (isset($heritage->repository)) {
            $result['repository'] = [
                'name' => (string) $heritage->repository->name,
                'identifier' => (string) $heritage->repository->identifier,
            ];
        }

        $result['dates'] = [];
        foreach ($heritage->date as $date) {
            $result['dates'][] = [
                'type' => (string) $date['type'],
                'start' => (string) $date->start,
                'end' => (string) $date->end,
                'display' => (string) $date->display,
            ];
        }

        $result['creators'] = [];
        foreach ($heritage->creator as $creator) {
            $result['creators'][] = [
                'name' => (string) $creator->name,
                'dates' => (string) $creator->dates,
                'type' => (string) $creator->type,
            ];
        }

        $result['subjects'] = [];
        foreach ($heritage->subject as $subject) {
            $result['subjects'][] = [
                'term' => (string) $subject->term,
                'taxonomy' => (string) $subject['taxonomy'],
            ];
        }

        $result['places'] = [];
        foreach ($heritage->place as $place) {
            $result['places'][] = (string) $place->name;
        }

        $result['digitalObjects'] = [];
        foreach ($heritage->digitalObject as $digitalObject) {
            $result['digitalObjects'][] = [
                'reference' => (string) $digitalObject->reference,
                'mimeType' => (string) $digitalObject->mimeType,
                'byteSize' => (string) $digitalObject->byteSize,
                'checksum' => (string) $digitalObject->checksum,
                'checksumType' => (string) $digitalObject->checksumType,
                'mediaType' => (string) $digitalObject->mediaType,
            ];
        }

        $result['notes'] = [];
        foreach ($heritage->note as $note) {
            $result['notes'][] = [
                'type' => (string) $note['type'],
                'content' => (string) $note,
            ];
        }

        $result['languages'] = [];
        foreach ($heritage->language as $language) {
            $result['languages'][] = (string) $language;
        }

        return $result;
    }

    protected function parseEad(\SimpleXMLElement $metadata): array
    {
        return ['raw' => $metadata->asXML()];
    }

    protected function parseDescription(\SimpleXMLElement $description): array
    {
        $result = [];

        $oaiId = $description->children('http://www.openarchives.org/OAI/2.0/oai-identifier/');
        if ($oaiId->{'oai-identifier'}) {
            $result['oaiIdentifier'] = [
                'scheme' => (string) $oaiId->{'oai-identifier'}->scheme,
                'repositoryIdentifier' => (string) $oaiId->{'oai-identifier'}->repositoryIdentifier,
                'delimiter' => (string) $oaiId->{'oai-identifier'}->delimiter,
                'sampleIdentifier' => (string) $oaiId->{'oai-identifier'}->sampleIdentifier,
            ];
        }

        return $result;
    }

    protected function formatXmlErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = sprintf('Line %d: %s', $error->line, trim($error->message));
        }
        return implode('; ', $messages);
    }

    public function setTimeout(int $seconds): self { $this->timeout = $seconds; return $this; }
    public function setMaxRetries(int $retries): self { $this->maxRetries = $retries; return $this; }
    public function setRetryDelay(int $seconds): self { $this->retryDelay = $seconds; return $this; }
    public function setUserAgent(string $userAgent): self { $this->userAgent = $userAgent; return $this; }
}

class HarvestException extends \Exception
{
}
