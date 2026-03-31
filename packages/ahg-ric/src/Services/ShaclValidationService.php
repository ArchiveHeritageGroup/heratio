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
    public function validateAgainstShapes(array $ricEntity): array
    {
        // Check if Python validator exists
        if (!file_exists($this->validatorScript)) {
            Log::warning('SHACL validator script not found, skipping shape validation');
            return ['valid' => true, 'violations' => []];
        }

        // Convert entity to TTL for validation
        $ttl = $this->toTurtle($ricEntity);
        $tempFile = tempnam(sys_get_temp_dir(), 'ric_validation_');
        file_put_contents($tempFile, $ttl);

        $command = sprintf(
            'python3 %s --data %s --shapes %s 2>&1',
            escapeshellcmd($this->validatorScript),
            escapeshellarg($tempFile),
            escapeshellarg($this->shapesPath)
        );

        $output = shell_exec($command);
        unlink($tempFile);

        // Parse output
        $violations = [];
        if (strpos($output, 'FAIL') !== false) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                if (strpos($line, 'Violation') !== false) {
                    $violations[] = trim($line);
                }
            }
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'raw_output' => $output,
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
        
        return DB::table('actor')
            ->where('slug', $slug)
            ->exists()
            || DB::table('information_object')
                ->where('slug', $slug)
                ->exists()
            || DB::table('repository')
                ->where('slug', $slug)
                ->exists();
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
        $ttl = "@prefix rico: <https://www.ica.org/standards/RiC/ontology#> .\n";
        $ttl .= "@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .\n";
        $ttl .= "@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .\n";
        
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
