<?php

/**
 * TimelinePeriodLabel - the one place a heritage timeline period is turned
 * into a human-readable date range.
 *
 * `heritage_timeline_period` records start_year, end_year and circa; it has
 * NO year_label column and never has. Two surfaces wanted one and disagreed:
 * timeline.blade read `$period->year_label`, which resolved to nothing on
 * every period, so the timeline listed period NAMES with no dates at all -
 * the one thing a timeline exists to show. landing.blade meanwhile built its
 * own inline formula, which rendered the BCE period "-5000-499".
 *
 * Both now come through here. A second copy of this formatting is how the two
 * drifted in the first place.
 */

namespace AhgHeritageManage\Support;

class TimelinePeriodLabel
{
    /**
     * Format one period's date range.
     *
     * Years are stored as signed integers, so a negative start_year is BCE.
     * The era suffix is written only where it is needed to read the range
     * correctly: a range that crosses zero needs both, a wholly-BCE range
     * needs one at the end, and an ordinary CE range needs none.
     */
    public static function format($period): string
    {
        if (! $period) {
            return '';
        }

        $start = $period->start_year ?? null;
        $end = $period->end_year ?? null;

        if ($start === null || $start === '') {
            return '';
        }

        $start = (int) $start;
        $end = ($end === null || $end === '') ? null : (int) $end;

        $circa = ! empty($period->circa) ? 'c. ' : '';

        // Open-ended: the period runs to today. This is a real state in the
        // data (the "Modern & Contemporary" period carries end_year NULL),
        // not a missing value to be papered over.
        if ($end === null) {
            return $circa.self::year($start).($start < 0 ? ' BCE' : '').' - present';
        }

        // A single year, not a range.
        if ($start === $end) {
            return $circa.self::year($start).($start < 0 ? ' BCE' : '');
        }

        // Wholly BCE - one suffix carries the whole range.
        if ($start < 0 && $end < 0) {
            return $circa.self::year($start).' - '.self::year($end).' BCE';
        }

        // Crosses the era boundary - both ends must say which era they are in,
        // or "5000 - 499" reads as a range running backwards.
        if ($start < 0) {
            return $circa.self::year($start).' BCE - '.self::year($end).' CE';
        }

        return $circa.self::year($start).' - '.self::year($end);
    }

    /**
     * Attach year_label to every row of a period collection, in place.
     */
    public static function decorate($periods)
    {
        if (! $periods) {
            return $periods;
        }

        foreach ($periods as $period) {
            if (is_object($period)) {
                $period->year_label = self::format($period);
            }
        }

        return $periods;
    }

    /**
     * Attach year_label to a single period row (returns it unchanged if null).
     */
    public static function decorateOne($period)
    {
        if (is_object($period)) {
            $period->year_label = self::format($period);
        }

        return $period;
    }

    private static function year(int $year): string
    {
        return (string) abs($year);
    }
}
