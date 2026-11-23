<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

/**
 * heratio#1495: loads and strictly validates a mapping file. Unknown keys,
 * targets or operations are refused, so a mapping cannot smuggle in inline
 * option lists or anything executable.
 */
class MappingFile
{
    public const SCALAR_TARGETS = [
        'title', 'extent_and_medium', 'archival_history', 'acquisition', 'scope_and_content',
        'appraisal', 'accruals', 'arrangement', 'access_conditions', 'reproduction_conditions',
        'physical_characteristics', 'finding_aids', 'location_of_originals', 'related_units_of_description',
    ];

    public const NOTE_TARGETS = ['note_general', 'note_publication', 'note_language'];

    public const LINK_TARGETS = ['actor_creator', 'actor_person', 'actor_corporate', 'term_subject', 'term_place'];

    public const SPECIAL_TARGETS = ['identifier', 'hierarchy', 'level', 'event_date', 'alternative_identifier'];

    private const TOP_KEYS = ['source', 'label', 'version', 'signature', 'reference_field', 'parent_field', 'delimiter', 'record_type', 'fields'];

    private const FIELD_KEYS = ['target', 'transforms', 'loss', 'reason'];

    private const LOSSES = ['dropped', 'flattened', 'folded'];

    public static function directory(): string
    {
        return dirname(__DIR__, 2).'/resources/mappings';
    }

    /** @return list<string> */
    public static function available(): array
    {
        return array_map(fn ($f) => basename($f, '.json'), glob(self::directory().'/*.json') ?: []);
    }

    /** Loads a shipped mapping by name. The name never becomes a free path. */
    public static function load(string $name): array
    {
        if (! preg_match('/^[a-z0-9_]+$/', $name) || ! in_array($name, self::available(), true)) {
            throw new \InvalidArgumentException("Unknown mapping: {$name}");
        }
        $json = (string) file_get_contents(self::directory()."/{$name}.json");
        $mapping = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        self::validate($mapping);
        $mapping['_sha256'] = hash('sha256', $json);

        return $mapping;
    }

    public static function validate(mixed $m): void
    {
        if (! is_array($m)) {
            throw new \InvalidArgumentException('Mapping must be an object.');
        }
        self::onlyKeys($m, self::TOP_KEYS, 'mapping');
        foreach (['source', 'reference_field', 'delimiter'] as $key) {
            if (! is_string($m[$key] ?? null) || $m[$key] === '') {
                throw new \InvalidArgumentException("Mapping needs {$key}.");
            }
        }
        if (! is_array($m['fields'] ?? null) || $m['fields'] === []) {
            throw new \InvalidArgumentException('Mapping needs fields.');
        }
        if (isset($m['record_type'])) {
            self::onlyKeys($m['record_type'], ['field', 'taxonomy'], 'record_type');
        }
        $targets = array_merge(self::SCALAR_TARGETS, self::NOTE_TARGETS, self::LINK_TARGETS, self::SPECIAL_TARGETS);
        foreach ($m['fields'] as $name => $field) {
            if (! is_array($field)) {
                throw new \InvalidArgumentException("Field {$name} must be an object.");
            }
            self::onlyKeys($field, self::FIELD_KEYS, "field {$name}");
            if (! array_key_exists('target', $field)) {
                throw new \InvalidArgumentException("Field {$name} needs a target.");
            }
            $target = $field['target'];
            if ($target === null ? ($field['loss'] ?? null) !== 'dropped' : ! in_array($target, $targets, true)) {
                throw new \InvalidArgumentException("Field {$name} has an invalid target.");
            }
            if (isset($field['loss']) && ! in_array($field['loss'], self::LOSSES, true)) {
                throw new \InvalidArgumentException("Field {$name} has an invalid loss type.");
            }
            foreach ($field['transforms'] ?? [] as $transform) {
                self::validateTransform($transform, (string) $name);
            }
        }
    }

    private static function validateTransform(mixed $t, string $field): void
    {
        $op = is_array($t) ? ($t['op'] ?? null) : null;
        if (! is_string($op) || ! array_key_exists($op, TransformVocabulary::OPS)) {
            throw new \InvalidArgumentException("Field {$field} names a transform outside the vocabulary.");
        }
        $params = TransformVocabulary::OPS[$op];
        self::onlyKeys($t, array_merge(['op'], $params), "transform {$op} on {$field}");
        foreach ($params as $param) {
            if (! is_string($t[$param] ?? null) || $t[$param] === '' || mb_strlen($t[$param]) > 100) {
                throw new \InvalidArgumentException("Transform {$op} on {$field} needs {$param}.");
            }
        }
    }

    private static function onlyKeys(mixed $value, array $allowed, string $where): void
    {
        if (! is_array($value)) {
            throw new \InvalidArgumentException("{$where} must be an object.");
        }
        $extra = array_diff(array_keys($value), $allowed);
        if ($extra !== []) {
            throw new \InvalidArgumentException("Unknown key in {$where}: ".implode(', ', $extra));
        }
    }
}
