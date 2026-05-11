<?php

/**
 * DiffComputer — produces a structured diff between two version snapshots.
 *
 * Mirror of the AtoM-side service at
 *   atom-ahg-plugins/ahgVersionControlPlugin/lib/Services/DiffComputer.php
 *
 * Identical contract, identical output shape, identical inline word-diff
 * algorithm. Byte-equivalent JSON for the same snapshot pair.
 *
 * @phase E
 */

namespace AhgVersionControl\Services;

class DiffComputer
{
    public const LONG_TEXT_THRESHOLD = 200;

    private const BASE_NOISE_FIELDS = ['lft', 'rgt', 'oai_local_identifier'];

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     * @return array<string,mixed>
     */
    public function diff(array $old, array $new): array
    {
        return [
            'scalar_changes'           => $this->diffBase($old['base'] ?? [], $new['base'] ?? []),
            'i18n_changes'             => $this->diffI18n($old['i18n'] ?? [], $new['i18n'] ?? []),
            'access_points_added'      => $this->setDiff($old['access_points'] ?? [], $new['access_points'] ?? [], 'add'),
            'access_points_removed'    => $this->setDiff($old['access_points'] ?? [], $new['access_points'] ?? [], 'remove'),
            'events_added'             => $this->setDiff($old['events'] ?? [], $new['events'] ?? [], 'add'),
            'events_removed'           => $this->setDiff($old['events'] ?? [], $new['events'] ?? [], 'remove'),
            'relations_added'          => $this->setDiff($old['relations'] ?? [], $new['relations'] ?? [], 'add'),
            'relations_removed'        => $this->setDiff($old['relations'] ?? [], $new['relations'] ?? [], 'remove'),
            'physical_objects_added'   => $this->setDiff($old['physical_objects'] ?? [], $new['physical_objects'] ?? [], 'add'),
            'physical_objects_removed' => $this->setDiff($old['physical_objects'] ?? [], $new['physical_objects'] ?? [], 'remove'),
            'custom_fields_changes'    => $this->diffCustomFields($old['custom_fields'] ?? [], $new['custom_fields'] ?? []),
        ];
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     * @return array<int,array<string,mixed>>
     */
    private function diffBase(array $old, array $new): array
    {
        $out = [];
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys);
        foreach ($keys as $key) {
            if (in_array($key, self::BASE_NOISE_FIELDS, true)) {
                continue;
            }
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            if ($oldVal === $newVal) {
                continue;
            }
            $row = ['field' => $key, 'old' => $oldVal, 'new' => $newVal];
            $row = $this->maybeAttachLongTextDiff($row);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $old
     * @param array<int,array<string,mixed>> $new
     * @return array<int,array<string,mixed>>
     */
    private function diffI18n(array $old, array $new): array
    {
        $oldByCulture = $this->indexByCulture($old);
        $newByCulture = $this->indexByCulture($new);
        $cultures = array_unique(array_merge(array_keys($oldByCulture), array_keys($newByCulture)));
        sort($cultures);

        $out = [];
        foreach ($cultures as $culture) {
            $oldRow = $oldByCulture[$culture] ?? null;
            $newRow = $newByCulture[$culture] ?? null;

            if ($oldRow === null && $newRow !== null) {
                foreach ($newRow as $field => $val) {
                    if ($field === 'culture' || $field === 'id' || $val === null || $val === '') {
                        continue;
                    }
                    $out[] = $this->maybeAttachLongTextDiff([
                        'culture' => $culture, 'field' => $field, 'old' => null, 'new' => $val, 'change_kind' => 'culture_added',
                    ]);
                }
                continue;
            }
            if ($oldRow !== null && $newRow === null) {
                foreach ($oldRow as $field => $val) {
                    if ($field === 'culture' || $field === 'id' || $val === null || $val === '') {
                        continue;
                    }
                    $out[] = $this->maybeAttachLongTextDiff([
                        'culture' => $culture, 'field' => $field, 'old' => $val, 'new' => null, 'change_kind' => 'culture_removed',
                    ]);
                }
                continue;
            }
            $fields = array_unique(array_merge(array_keys($oldRow ?? []), array_keys($newRow ?? [])));
            sort($fields);
            foreach ($fields as $field) {
                if ($field === 'culture' || $field === 'id') {
                    continue;
                }
                $oldVal = $oldRow[$field] ?? null;
                $newVal = $newRow[$field] ?? null;
                if ($oldVal === $newVal) {
                    continue;
                }
                $out[] = $this->maybeAttachLongTextDiff([
                    'culture' => $culture, 'field' => $field, 'old' => $oldVal, 'new' => $newVal,
                ]);
            }
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $old
     * @param array<int,array<string,mixed>> $new
     * @param 'add'|'remove' $direction
     * @return array<int,array<string,mixed>>
     */
    private function setDiff(array $old, array $new, string $direction): array
    {
        $oldMap = [];
        foreach ($old as $row) {
            $oldMap[$this->canonicalJson((array) $row)] = (array) $row;
        }
        $newMap = [];
        foreach ($new as $row) {
            $newMap[$this->canonicalJson((array) $row)] = (array) $row;
        }
        if ($direction === 'add') {
            $diffKeys = array_diff(array_keys($newMap), array_keys($oldMap));
            return array_values(array_map(fn ($k) => $newMap[$k], $diffKeys));
        }
        $diffKeys = array_diff(array_keys($oldMap), array_keys($newMap));
        return array_values(array_map(fn ($k) => $oldMap[$k], $diffKeys));
    }

    /**
     * @param array<int,array<string,mixed>> $old
     * @param array<int,array<string,mixed>> $new
     * @return array<int,array<string,mixed>>
     */
    private function diffCustomFields(array $old, array $new): array
    {
        $key = fn (array $r): string =>
            ($r['field_definition_id'] ?? '') . ':' . ($r['sequence'] ?? '');
        $oldMap = [];
        foreach ($old as $r) {
            $oldMap[$key((array) $r)] = (array) $r;
        }
        $newMap = [];
        foreach ($new as $r) {
            $newMap[$key((array) $r)] = (array) $r;
        }
        $all = array_unique(array_merge(array_keys($oldMap), array_keys($newMap)));
        sort($all);
        $out = [];
        foreach ($all as $k) {
            $o = $oldMap[$k] ?? null;
            $n = $newMap[$k] ?? null;
            if ($this->canonicalJson((array) ($o ?? [])) === $this->canonicalJson((array) ($n ?? []))) {
                continue;
            }
            $out[] = [
                'field_definition_id' => $o['field_definition_id'] ?? ($n['field_definition_id'] ?? null),
                'sequence'            => $o['sequence'] ?? ($n['sequence'] ?? null),
                'old' => $o,
                'new' => $n,
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function maybeAttachLongTextDiff(array $row): array
    {
        $old = is_string($row['old'] ?? null) ? $row['old'] : '';
        $new = is_string($row['new'] ?? null) ? $row['new'] : '';
        if (strlen($old) < self::LONG_TEXT_THRESHOLD && strlen($new) < self::LONG_TEXT_THRESHOLD) {
            return $row;
        }
        $row['long_text_diff'] = $this->inlineWordDiff($old, $new);
        return $row;
    }

    private function inlineWordDiff(string $old, string $new): string
    {
        $a = $this->tokenise($old);
        $b = $this->tokenise($new);
        $n = count($a);
        $m = count($b);

        if ($n * $m > 1_000_000) {
            return '<del>' . htmlspecialchars($old, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</del>'
                . '<ins>' . htmlspecialchars($new, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</ins>';
        }

        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $lcs[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $lcs[$i - 1][$j - 1] + 1
                    : max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
            }
        }

        $ops = [];
        $i = $n; $j = $m;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($ops, ['eq', $a[$i - 1]]);
                $i--; $j--;
            } elseif ($lcs[$i - 1][$j] >= $lcs[$i][$j - 1]) {
                array_unshift($ops, ['del', $a[$i - 1]]);
                $i--;
            } else {
                array_unshift($ops, ['ins', $b[$j - 1]]);
                $j--;
            }
        }
        while ($i > 0) {
            array_unshift($ops, ['del', $a[$i - 1]]);
            $i--;
        }
        while ($j > 0) {
            array_unshift($ops, ['ins', $b[$j - 1]]);
            $j--;
        }

        $out = '';
        $kind = null;
        $buf = '';
        $flush = function () use (&$out, &$kind, &$buf): void {
            if ($kind === null || $buf === '') {
                $buf = '';
                $kind = null;
                return;
            }
            $esc = htmlspecialchars($buf, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out .= $kind === 'eq' ? $esc : "<{$kind}>{$esc}</{$kind}>";
            $buf = '';
            $kind = null;
        };
        foreach ($ops as [$op, $token]) {
            if ($op !== $kind) {
                $flush();
                $kind = $op;
            }
            $buf .= $token;
        }
        $flush();
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private function tokenise(string $text): array
    {
        if ($text === '') {
            return [];
        }
        preg_match_all('/(\s+|[^\s]+)/u', $text, $matches);
        return $matches[0] ?? [];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private function indexByCulture(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $culture = $row['culture'] ?? null;
            if (is_string($culture) && $culture !== '') {
                $out[$culture] = (array) $row;
            }
        }
        return $out;
    }

    private function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(',', array_map(fn ($v) => $this->canonicalJson($v), $value)) . ']';
            }
            ksort($value);
            $parts = [];
            foreach ($value as $k => $v) {
                $parts[] = json_encode((string) $k, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    . ':' . $this->canonicalJson($v);
            }
            return '{' . implode(',', $parts) . '}';
        }
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
