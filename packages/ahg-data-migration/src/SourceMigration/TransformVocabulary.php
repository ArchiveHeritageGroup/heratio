<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

use Illuminate\Support\Facades\DB;

/**
 * heratio#1495: the closed set of operations a mapping file may name. A mapping
 * file is data: there is no expression language and nothing is evaluated.
 * Lookups resolve against the Dropdown Manager and carry no values of their own.
 */
class TransformVocabulary
{
    /** op => the parameters it requires. */
    public const OPS = [
        'identity' => [],
        'parse_date' => [],
        'split' => ['separator'],
        'lookup' => ['taxonomy'],
    ];

    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3, 'apr' => 4, 'april' => 4,
        'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12,
    ];

    /** @var array<string,array<string,array>> */
    private array $lookups = [];

    /** @param list<array{op:string}> $transforms */
    public function apply(array $transforms, mixed $value): mixed
    {
        foreach ($transforms as $transform) {
            $value = match ($transform['op'] ?? null) {
                'identity' => $value,
                'parse_date' => $this->parseDate((string) $value),
                'split' => $this->split((string) $value, (string) $transform['separator']),
                'lookup' => $this->lookup((string) $transform['taxonomy'], (string) $value),
                default => throw new \InvalidArgumentException('Unknown transform.'),
            };
        }

        return $value;
    }

    /** @return list<string> */
    public function split(string $value, string $separator): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode($separator, $value)), fn ($v) => $v !== '')));
    }

    /** @return array{raw:string,code:?string,label:?string,metadata:array} */
    public function lookup(string $taxonomy, string $value): array
    {
        if (! isset($this->lookups[$taxonomy])) {
            $this->lookups[$taxonomy] = [];
            $rows = DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('is_active', 1)->get(['code', 'label', 'metadata']);
            foreach ($rows as $row) {
                $entry = ['code' => $row->code, 'label' => $row->label, 'metadata' => json_decode((string) $row->metadata, true) ?: []];
                $this->lookups[$taxonomy][$this->key($row->label)] = $entry;
                $this->lookups[$taxonomy][$this->key($row->code)] = $entry;
            }
        }

        return ['raw' => $value] + ($this->lookups[$taxonomy][$this->key($value)] ?? ['code' => null, 'label' => null, 'metadata' => []]);
    }

    /**
     * Keeps the display expression whatever happens. Start and end are filled
     * only when the expression is unambiguous: 03/04/1890 stays display-only
     * because day and month order cannot be known.
     *
     * @return array{display:string,start:?string,end:?string,parsed:bool}
     */
    public function parseDate(string $expression): array
    {
        $display = trim($expression);
        $failed = ['display' => $display, 'start' => null, 'end' => null, 'parsed' => false];
        $text = trim((string) preg_replace('/^(?:circa|ca\.?|c\.?)\s*/iu', '', $display));
        if ($text === '') {
            return $failed;
        }

        $parts = preg_split('/\s+(?:-|to|\x{2013}|\x{2014})\s+|(?<=\d)[-\x{2013}\x{2014}](?=\d{4}\b)/iu', $text);
        if (count($parts) > 2) {
            return $failed;
        }
        $first = $this->endpoint($parts[0]);
        $last = isset($parts[1]) ? $this->endpoint(preg_replace('/^(?:circa|ca\.?|c\.?)\s*/iu', '', $parts[1])) : $first;
        if ($first === null || $last === null || $first[0] > $last[1]) {
            return $failed;
        }

        return ['display' => $display, 'start' => $first[0], 'end' => $last[1], 'parsed' => true];
    }

    /** @return array{0:string,1:string}|null */
    private function endpoint(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/^(\d{4})$/', $text, $m)) {
            return ["{$m[1]}-01-01", "{$m[1]}-12-31"];
        }
        if (preg_match('/^(\d{3})0s$/i', $text, $m)) {
            return ["{$m[1]}0-01-01", "{$m[1]}9-12-31"];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)) {
            return $this->day((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/^(\d{1,2})\s+([a-z]+)\.?\s+(\d{4})$/i', $text, $m)) {
            $month = self::MONTHS[strtolower($m[2])] ?? null;

            return $month ? $this->day((int) $m[3], $month, (int) $m[1]) : null;
        }
        if (preg_match('/^([a-z]+)\.?\s+(\d{4})$/i', $text, $m)) {
            $month = self::MONTHS[strtolower($m[1])] ?? null;
            if (! $month) {
                return null;
            }
            $days = (int) date('t', gmmktime(0, 0, 0, $month, 1, (int) $m[2]));

            return [sprintf('%04d-%02d-01', $m[2], $month), sprintf('%04d-%02d-%02d', $m[2], $month, $days)];
        }

        return null;
    }

    private function day(int $y, int $m, int $d): ?array
    {
        if (! checkdate($m, $d, $y)) {
            return null;
        }
        $date = sprintf('%04d-%02d-%02d', $y, $m, $d);

        return [$date, $date];
    }

    private function key(string $value): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim($value)));
    }
}
