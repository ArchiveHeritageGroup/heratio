<?php

/**
 * ShaclValidationService - SHACL validation integration for CRUD operations
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 */

namespace AhgRic\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Service for validating RIC-O entities against SHACL shapes.
 * 
 * Hooks into CRUD operations to validate data before save/update.
 */
class ShaclValidationService
{
    private string $shapesPath;
    private string $fusekiEndpoint;
    private string $validatorScript;

    public function __construct()
    {
        $this->shapesPath = __DIR__ . '/../../tools/ric_shacl_shapes.ttl';
        $this->fusekiEndpoint = config('heratio.fuseki_endpoint', 'http://localhost:3030/heratio');
        $this->validatorScript = __DIR__ . '/../../tools/ric_shacl_validator.py';
    }

    /**
     * Path to the STRICT conformance shapes (sh:Violation-only structural rules)
     * used by the ahg:ric-conformance CI gate - distinct from the advisory
     * data-quality shapes in ric_shacl_shapes.ttl. See ADR-0003 / #1319.
     */
    public function conformanceShapesPath(): string
    {
        return __DIR__ . '/../../tools/ric_conformance_shapes.ttl';
    }

    /**
     * Validate an entity before CRUD operation
     */
    public function validateBeforeSave(array $ricEntity, string $entityType): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Run SHACL validation
        $validation = $this->validateAgainstShapes($ricEntity);
        
        if (!$validation['valid']) {
            $result['valid'] = false;
            $result['errors'] = $validation['violations'];
        }

        // Check mandatory fields for entity type
        $mandatoryCheck = $this->checkMandatoryFields($ricEntity, $entityType);
        if (!$mandatoryCheck['valid']) {
            $result['valid'] = false;
            $result['errors'] = array_merge($result['errors'], $mandatoryCheck['errors']);
        }

        // Check referential integrity
        $refCheck = $this->checkReferentialIntegrity($ricEntity);
        if (!$refCheck['valid']) {
            $result['warnings'] = array_merge($result['warnings'], $refCheck['warnings']);
        }

        return $result;
    }

    /**
     * Validate JSON-LD against SHACL shapes
     */
    public function validateAgainstShapes(array $ricEntity, ?string $shapesPath = null): array
    {
        $shapesPath = $shapesPath ?? $this->shapesPath;
        // Check if Python validator exists
        if (!file_exists($this->validatorScript)) {
            Log::warning('SHACL validator script not found, skipping shape validation');
            return ['valid' => true, 'ran' => false, 'reason' => 'validator script missing', 'violations' => []];
        }

        // Convert entity to TTL for validation
        $ttl = $this->toTurtle($ricEntity);
        $tempFile = tempnam(sys_get_temp_dir(), 'ric_validation_') . '.ttl';
        file_put_contents($tempFile, $ttl);

        // The validator's contract: `--validate --file <data> --shapes <shapes>`,
        // exit 0 = conforms, exit 1 = does NOT conform. (The old `--data` flag
        // was wrong and the FAIL/Violation string-match never matched its
        // output, so validation silently passed.)
        $command = sprintf(
            'python3 %s --validate --file %s --shapes %s 2>&1',
            escapeshellarg($this->validatorScript),
            escapeshellarg($tempFile),
            escapeshellarg($shapesPath)
        );

        $output = [];
        $rc = 0;
        exec($command, $output, $rc);
        @unlink($tempFile);
        $raw = implode("\n", $output);

        // A missing pyshacl/rdflib also exits non-zero (import error), which we
        // must NOT confuse with a real violation. Mark such runs ran=false so
        // runtime callers degrade gracefully while the CI gate fails loudly.
        if (preg_match('/No module named|ModuleNotFoundError|pip install pyshacl|Traceback \(most recent/i', $raw)) {
            Log::warning('[shacl] validator could not run (pyshacl/rdflib missing): ' . mb_substr($raw, 0, 500));
            return ['valid' => true, 'ran' => false, 'reason' => 'pyshacl/rdflib not installed', 'violations' => [], 'raw_output' => $raw];
        }

        $conforms = ($rc === 0);
        $violations = [];
        if (!$conforms) {
            foreach ($output as $line) {
                if (stripos($line, 'violation') !== false || stripos($line, 'Result Path') !== false
                    || stripos($line, 'Message') !== false || stripos($line, 'Focus') !== false) {
                    $violations[] = trim($line);
                }
            }
            if (empty($violations)) {
                $violations[] = 'Non-conformant (validator exit ' . $rc . ')';
            }
        }

        return [
            'valid' => $conforms,
            'ran' => true,
            'violations' => $violations,
            'raw_output' => $raw,
        ];
    }

    /**
     * Check mandatory RIC-O fields for entity type
     */
    private function checkMandatoryFields(array $entity, string $type): array
    {
        $errors = [];
        
        $mandatoryFields = $this->getMandatoryFields($type);
        
        foreach ($mandatoryFields as $field) {
            if (!isset($entity[$field]) || empty($entity[$field])) {
                $errors[] = "Mandatory field '{$field}' is missing for {$type}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get mandatory fields for entity type (ISAAR/ISDF/ISAD compliance)
     */
    private function getMandatoryFields(string $type): array
    {
        return match ($type) {
            'Agent', 'Person', 'CorporateBody', 'Family' => [
                'rico:name', // authorizedFormOfName (ISAAR mandatory)
            ],
            'Function' => [
                'rico:name', // authorizedFormOfName (ISDF mandatory)
            ],
            'Record', 'RecordSet' => [
                'rico:identifier', // ISAD mandatory
            ],
            'Repository' => [
                'rico:name', // ISDIAH mandatory
            ],
            default => [],
        };
    }

    /**
     * Check referential integrity - ensure linked entities exist
     */
    private function checkReferentialIntegrity(array $entity): array
    {
        $warnings = [];
        
        // Check agent references
        if (isset($entity['rico:hasCreator'])) {
            $creators = is_array($entity['rico:hasCreator']) ? $entity['rico:hasCreator'] : [$entity['rico:hasCreator']];
            foreach ($creators as $creator) {
                if (isset($creator['@id'])) {
                    $exists = $this->entityExistsInDatabase($creator['@id']);
                    if (!$exists) {
                        $warnings[] = "Referenced creator does not exist: {$creator['@id']}";
                    }
                }
            }
        }

        // Check repository references
        if (isset($entity['rico:heldBy'])) {
            $repo = $entity['rico:heldBy'];
            if (isset($repo['@id']) && !$this->entityExistsInDatabase($repo['@id'])) {
                $warnings[] = "Referenced repository does not exist: {$repo['@id']}";
            }
        }

        return [
            'valid' => empty($warnings),
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if entity exists in database by URI
     */
    private function entityExistsInDatabase(string $uri): bool
    {
        $slug = $this->extractSlug($uri);
        $objectId = (int) DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return false;
        }
        return DB::table('actor')->where('id', $objectId)->exists()
            || DB::table('information_object')->where('id', $objectId)->exists()
            || DB::table('repository')->where('id', $objectId)->exists();
    }

    /**
     * Extract slug from URI
     */
    private function extractSlug(string $uri): string
    {
        $parts = explode('/', $uri);
        return end($parts);
    }

    /**
     * Convert JSON-LD to Turtle for SHACL validation
     */
    private function toTurtle(array $entity): string
    {
        // Declare every prefix the serializers emit. Missing openric:/owl:/skos:
        // declarations produced malformed Turtle (undeclared-prefix parse error),
        // which surfaced as a false "non-conformant" for places (openric:localType,
        // owl:sameAs). openric: is the canonical public namespace (governance pin).
        $ttl = "@prefix rico: <https://www.ica.org/standards/RiC/ontology#> .\n";
        $ttl .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
        $ttl .= "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n";
        $ttl .= "@prefix skos: <http://www.w3.org/2004/02/skos/core#> .\n";
        $ttl .= "@prefix owl: <http://www.w3.org/2002/07/owl#> .\n";
        $ttl .= "@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .\n";
        $ttl .= "@prefix openric: <https://openric.org/ns/v1#> .\n";
        
        $id = $entity['@id'] ?? '_:b0';
        $type = str_replace('https://www.ica.org/standards/RiC/ontology#', 'rico:', $entity['@type'] ?? 'rico:Thing');
        
        $ttl .= "<{$id}> rdf:type {$type} .\n";
        
        foreach ($entity as $key => $value) {
            if (in_array($key, ['@context', '@id', '@type'])) {
                continue;
            }
            
            $prop = str_replace('https://www.ica.org/standards/RiC/ontology#', 'rico:', $key);
            $prop = str_replace('http://www.w3.org/2004/02/skos/core#', 'skos:', $prop);
            
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (is_array($v) && isset($v['@id'])) {
                        $ttl .= "<{$id}> {$prop} <{$v['@id']}> .\n";
                    } elseif (is_string($v)) {
                        $ttl .= "<{$id}> {$prop} \"{$this->escapeString($v)}\" .\n";
                    }
                }
            } elseif (is_string($value)) {
                $ttl .= "<{$id}> {$prop} \"{$this->escapeString($value)}\" .\n";
            }
        }
        
        return $ttl;
    }

    /**
     * Escape string for Turtle
     */
    private function escapeString(string $str): string
    {
        return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $str);
    }

    /**
     * Batch validate multiple entities
     */
    public function validateBatch(array $entities, string $entityType): array
    {
        $results = [];
        
        foreach ($entities as $index => $entity) {
            $results[$index] = $this->validateBeforeSave($entity, $entityType);
        }
        
        return [
            'total' => count($entities),
            'valid' => count(array_filter($results, fn($r) => $r['valid'])),
            'invalid' => count(array_filter($results, fn($r) => !$r['valid'])),
            'results' => $results,
        ];
    }
}
