<?php

/**
 * LibraryLabelService - spine, barcode and combined labels on sheet stationery
 * (#1476 item 2). All geometry is in millimetres so the printed sheet matches
 * the stock when printed at 100% scale.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgCore\Support\I18nFallback;
use AhgLibrary\Support\Code128;
use AhgLibrary\Support\LibraryBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LibraryLabelService
{
    public const MAX_LABELS = 2000;

    /** Formats this renderer can draw; the dropdown decides which are offered. */
    private const RENDERABLE = ['spine', 'barcode', 'combined'];

    private const PADDING_MM = 1.5;

    /** Narrowest bar most handheld scanners read reliably. */
    private const MIN_MODULE_MM = 0.19;

    private const MAX_MODULE_MM = 0.33;

    private const MIN_BAR_HEIGHT_MM = 5.0;

    /** Below roughly 4pt a printed call number stops being legible. */
    private const MIN_FONT_MM = 1.4;

    private const MAX_FONT_MM = 4.5;

    public function available(): bool
    {
        return Schema::hasTable('library_label_template');
    }

    /** code => label for active, renderable formats, from the Dropdown Manager. */
    public function formats(): array
    {
        if (! Schema::hasTable('ahg_dropdown')) {
            return [];
        }

        return DB::table('ahg_dropdown')
            ->where('taxonomy', 'library_label_format')
            ->where('is_active', 1)
            ->whereIn('code', self::RENDERABLE)
            ->orderBy('sort_order')
            ->pluck('label', 'code')
            ->all();
    }

    public function templates(bool $activeOnly = true): array
    {
        return DB::table('library_label_template')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderByDesc('is_builtin')->orderBy('name')
            ->get()->all();
    }

    public function template(int $id): ?object
    {
        return DB::table('library_label_template')->where('id', $id)->first();
    }

    /** Sheet geometry problems, keyed by form field. Empty means it fits. */
    public function geometryErrors(array $t): array
    {
        $errors = [];
        $across = (int) $t['columns_across'];
        $down = (int) $t['rows_down'];

        if ($across > 1 && (float) $t['pitch_x_mm'] < (float) $t['label_width_mm']) {
            $errors['pitch_x_mm'] = __('Horizontal pitch cannot be smaller than the label width.');
        }
        if ($down > 1 && (float) $t['pitch_y_mm'] < (float) $t['label_height_mm']) {
            $errors['pitch_y_mm'] = __('Vertical pitch cannot be smaller than the label height.');
        }
        $right = (float) $t['margin_left_mm'] + ($across - 1) * (float) $t['pitch_x_mm'] + (float) $t['label_width_mm'];
        if ($right > (float) $t['page_width_mm'] + 0.01) {
            $errors['columns_across'] = __('The labels run :mm mm past the right edge of the page.', ['mm' => round($right - (float) $t['page_width_mm'], 2)]);
        }
        $bottom = (float) $t['margin_top_mm'] + ($down - 1) * (float) $t['pitch_y_mm'] + (float) $t['label_height_mm'];
        if ($bottom > (float) $t['page_height_mm'] + 0.01) {
            $errors['rows_down'] = __('The labels run :mm mm past the bottom edge of the page.', ['mm' => round($bottom - (float) $t['page_height_mm'], 2)]);
        }

        return $errors;
    }

    /** Shift problems for this sheet, keyed by form field. Empty means every label stays on the page. */
    public function nudgeErrors(object $t, float $nudgeX, float $nudgeY): array
    {
        $errors = [];
        $check = function (string $field, float $start, float $end, float $page, float $nudge, string $before, string $after) use (&$errors) {
            if ($start + $nudge < -0.01) {
                $errors[$field] = __($before, ['mm' => round(-($start + $nudge), 2)]);
            } elseif ($end + $nudge > $page + 0.01) {
                $errors[$field] = __($after, ['mm' => round($end + $nudge - $page, 2)]);
            }
        };

        $check('nudge_x', (float) $t->margin_left_mm,
            (float) $t->margin_left_mm + ((int) $t->columns_across - 1) * (float) $t->pitch_x_mm + (float) $t->label_width_mm,
            (float) $t->page_width_mm, $nudgeX,
            'This shift puts the labels :mm mm past the left edge of the page.',
            'This shift puts the labels :mm mm past the right edge of the page.');
        $check('nudge_y', (float) $t->margin_top_mm,
            (float) $t->margin_top_mm + ((int) $t->rows_down - 1) * (float) $t->pitch_y_mm + (float) $t->label_height_mm,
            (float) $t->page_height_mm, $nudgeY,
            'This shift puts the labels :mm mm past the top edge of the page.',
            'This shift puts the labels :mm mm past the bottom edge of the page.');

        return $errors;
    }

    /** Create or update a custom template. Built-in rows are never changed. */
    public function saveTemplate(array $data, ?int $id, ?int $userId): int
    {
        $row = array_intersect_key($data, array_flip([
            'name', 'page_width_mm', 'page_height_mm', 'margin_top_mm', 'margin_left_mm',
            'label_width_mm', 'label_height_mm', 'pitch_x_mm', 'pitch_y_mm', 'columns_across', 'rows_down',
        ]));
        $row['updated_at'] = now();

        if ($id !== null) {
            DB::table('library_label_template')->where('id', $id)->where('is_builtin', 0)->update($row);

            return $id;
        }

        return (int) DB::table('library_label_template')->insertGetId($row + [
            'code' => 'custom_'.Str::lower(Str::random(12)),
            'is_builtin' => 0, 'is_active' => 1, 'created_by' => $userId, 'created_at' => now(),
        ]);
    }

    public function deleteTemplate(int $id): bool
    {
        return DB::table('library_label_template')->where('id', $id)->where('is_builtin', 0)->delete() > 0;
    }

    /**
     * Copies named by scanned identifiers (barcode or accession number) and/or
     * a whole accession. A trailing * on the accession matches by prefix, for
     * services that number copies within an accession (2026/014-1, -2, ...).
     *
     * @return array{copies: array, not_found: string[], other_branch: int, no_branch: int, truncated: bool}
     */
    public function resolveCopies(array $identifiers, ?string $accession): array
    {
        $identifiers = array_values(array_unique(array_filter(array_map('trim', $identifiers), 'strlen')));
        $accession = trim((string) $accession);
        if (self::isBareWildcard($accession)) {
            $accession = '';
        }

        $q = DB::table('library_copy as c')
            ->join('library_item as li', 'li.id', '=', 'c.library_item_id')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'li.information_object_id', app()->getLocale()))
            ->where(function ($w) use ($identifiers, $accession) {
                $w->whereRaw('1 = 0');
                if ($identifiers !== []) {
                    $w->orWhereIn('c.barcode', $identifiers)->orWhereIn('c.accession_number', $identifiers);
                }
                if ($accession !== '') {
                    if (str_ends_with($accession, '*')) {
                        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], rtrim($accession, '*'));
                        $w->orWhereRaw("c.accession_number LIKE ? ESCAPE '!'", [$prefix.'%']);
                    } else {
                        $w->orWhere('c.accession_number', $accession);
                    }
                }
            })
            ->orderBy('c.accession_number')->orderBy('c.copy_number')->orderBy('c.id')
            ->select(
                'c.id', 'c.barcode', 'c.accession_number', 'c.copy_number', 'c.call_number_suffix',
                'li.call_number', 'li.dewey_decimal', 'li.classification_number', 'li.cutter_number',
                'li.volume_designation',
                I18nFallback::col('i18n', 'title', 'title'),
            );

        // Scoped in SQL so another branch's copies cannot use up the run limit.
        $branch = LibraryBranch::operatorBranchId();
        $otherBranch = 0;
        $noBranch = 0;
        if ($branch !== null && Schema::hasColumn('library_copy', 'branch_id')) {
            $otherBranch = (clone $q)->reorder()->where('c.branch_id', '!=', $branch)->count();
            // NULL is unattributed, not another branch, and needs a different fix.
            $noBranch = (clone $q)->reorder()->whereNull('c.branch_id')->count();
            $q->where('c.branch_id', $branch);
        }

        $rows = $q->limit(self::MAX_LABELS + 1)->get()->all();
        $truncated = count($rows) > self::MAX_LABELS;
        $rows = array_slice($rows, 0, self::MAX_LABELS);

        $found = [];
        foreach ($rows as $r) {
            $found[(string) $r->barcode] = true;
            $found[(string) $r->accession_number] = true;
        }
        $notFound = $this->identifiersWithNoCopy(array_values(array_filter($identifiers, fn ($i) => ! isset($found[$i]))));

        return ['copies' => $rows, 'not_found' => $notFound, 'other_branch' => $otherBranch, 'no_branch' => $noBranch, 'truncated' => $truncated];
    }

    /** True when an accession value is only wildcards, which would select every copy. */
    public static function isBareWildcard(string $accession): bool
    {
        return $accession !== '' && trim($accession, "* \t\r\n") === '';
    }

    /**
     * Which identifiers match no copy in any branch. The database decides, so
     * the answer follows the columns' collation exactly as the lookup did.
     */
    private function identifiersWithNoCopy(array $identifiers): array
    {
        $missing = [];
        foreach (array_chunk($identifiers, 200) as $chunk) {
            $test = '(EXISTS (SELECT 1 FROM library_copy WHERE barcode = ?) OR EXISTS (SELECT 1 FROM library_copy WHERE accession_number = ?))';
            $columns = [];
            $bindings = [];
            foreach ($chunk as $n => $identifier) {
                $columns[] = $test.' AS m'.$n;
                array_push($bindings, $identifier, $identifier);
            }
            $flags = (array) DB::selectOne('SELECT '.implode(', ', $columns), $bindings);
            foreach ($chunk as $n => $identifier) {
                if (! (int) $flags['m'.$n]) {
                    $missing[] = $identifier;
                }
            }
        }

        return $missing;
    }

    /** Call number broken into the lines a spine label carries. */
    public function spineLines(object $copy): array
    {
        $call = trim((string) $copy->call_number);
        if ($call === '') {
            $call = trim(($copy->classification_number ?: $copy->dewey_decimal).' '.$copy->cutter_number);
        }
        if ($call === '') {
            return [];
        }

        $lines = preg_split('/\s+/', $call);
        foreach ([$copy->call_number_suffix, $copy->volume_designation] as $extra) {
            if (trim((string) $extra) !== '') {
                $lines[] = trim((string) $extra);
            }
        }
        if ((int) $copy->copy_number > 1) {
            $lines[] = 'c.'.(int) $copy->copy_number;
        }

        return $lines;
    }

    /**
     * Lay out one label, or say why it cannot be printed. A label that would
     * not scan or read is refused rather than printed on paid-for stock.
     *
     * @return array{0: ?array, 1: ?string}
     */
    public function buildLabel(object $copy, string $format, object $t): array
    {
        $w = (float) $t->label_width_mm - 2 * self::PADDING_MM;
        $h = (float) $t->label_height_mm - 2 * self::PADDING_MM;
        $label = ['format' => $format, 'lines' => [], 'font' => 0.0, 'title' => null, 'barcode' => null, 'svg' => null];

        $spineW = $format === 'combined' ? $w * 0.36 : $w;
        if ($format !== 'barcode') {
            $lines = $this->spineLines($copy);
            if ($lines === []) {
                return [null, __('no call number')];
            }
            $longest = max(array_map('mb_strlen', $lines));
            // Monospace: a glyph is 0.6 em wide, a line 1.15 em high.
            $font = min(self::MAX_FONT_MM, $h / (count($lines) * 1.15), $spineW / ($longest * 0.6));
            if ($font < self::MIN_FONT_MM) {
                return [null, __('call number too long for this label')];
            }
            $label['lines'] = $lines;
            $label['font'] = floor($font * 10) / 10;
        }

        if ($format !== 'spine') {
            $code = trim((string) $copy->barcode);
            if ($code === '') {
                return [null, __('no barcode')];
            }
            if (! Code128::encodable($code)) {
                return [null, __('barcode has characters Code 128 cannot carry')];
            }
            $barW = $format === 'combined' ? $w - $spineW - 1 : $w;
            $module = min(self::MAX_MODULE_MM, $barW / Code128::modules($code));
            if ($module < self::MIN_MODULE_MM) {
                return [null, __('barcode too wide to scan on this label')];
            }
            $showTitle = $format === 'barcode' && (float) $t->label_height_mm >= 20;
            $barH = $h - 3.0 - ($showTitle ? 3.0 : 0);
            if ($barH < self::MIN_BAR_HEIGHT_MM) {
                return [null, __('label too short for a barcode')];
            }
            $label['barcode'] = $code;
            $label['svg'] = Code128::svg($code, $module, min($barH, 15.0));
            $label['title'] = $showTitle ? Str::limit((string) $copy->title, (int) ($w / 1.2), '...') : null;
        }

        return [$label, null];
    }

    /**
     * Build the printable sheets. $skip leaves that many positions empty on the
     * first sheet so a part-used sheet can go back through the printer.
     *
     * @return array{pages: array, skipped: array, count: int, fonts: float[]}
     */
    public function sheets(array $copies, string $format, object $t, int $skip = 0, float $nudgeX = 0.0, float $nudgeY = 0.0): array
    {
        $labels = [];
        $skipped = [];
        foreach ($copies as $copy) {
            [$label, $reason] = $this->buildLabel($copy, $format, $t);
            if ($label === null) {
                $skipped[] = ['ref' => $copy->barcode ?: ($copy->accession_number ?: '#'.$copy->id), 'reason' => $reason];

                continue;
            }
            $labels[] = $label;
        }

        $across = (int) $t->columns_across;
        $perPage = $across * (int) $t->rows_down;
        $pages = [];
        $fonts = [];
        foreach ($labels as $i => $label) {
            $slot = $i + $skip;
            $page = intdiv($slot, $perPage);
            $pos = $slot % $perPage;
            $label['x'] = round((float) $t->margin_left_mm + $nudgeX + ($pos % $across) * (float) $t->pitch_x_mm, 2);
            $label['y'] = round((float) $t->margin_top_mm + $nudgeY + intdiv($pos, $across) * (float) $t->pitch_y_mm, 2);
            $pages[$page][] = $label;
            if ($label['font'] > 0) {
                $fonts[(string) $label['font']] = (float) $label['font'];
            }
        }

        return ['pages' => array_values($pages), 'skipped' => $skipped, 'count' => count($labels), 'fonts' => array_values($fonts)];
    }
}
