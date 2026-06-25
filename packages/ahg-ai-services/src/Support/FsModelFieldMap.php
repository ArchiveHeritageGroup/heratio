<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiServices\Support;

/**
 * Adapter from the HTR model's output to the FamilySearch Data Safe schema
 * (FS-Scotland indexer, heratio#1336).
 *
 * The trained HTR service returns a flat `fields:[{name,value,confidence}]`
 * list whose names depend on the doc_type the model was asked for:
 *   type_a (BIRTH)    : surname, first_names, date_of_birth, place_of_birth,
 *                       father_name, mother_name, district
 *   type_b (DEATH)    : surname, first_names, date_of_death, place_of_death,
 *                       cause_of_death, age
 *   type_c (MARRIAGE) : groom_surname/first_names, bride_surname/first_names,
 *                       date_of_marriage, place_of_marriage, witness_1, witness_2
 * (verified live against the gateway 2026-06-24).
 *
 * This maps those model names onto the Data Safe `*_ORIG` system names. Raw
 * values only - FsKeyingRules normalises dates/names afterwards. Combined
 * person names (father_name, groom_first_names+surname...) are split into
 * given/surname; the review grid lets a human fix the rare split error.
 */
final class FsModelFieldMap
{
    /** FS event type -> the HTR service doc_type that yields its field schema. */
    public const DOCTYPE_FOR_EVENT = [
        'Birth'    => 'type_a',
        'Baptism'  => 'type_a',
        'Death'    => 'type_b',
        'Burial'   => 'type_b',
        'Marriage' => 'type_c',
    ];

    public static function docTypeForEvent(string $eventType): string
    {
        return self::DOCTYPE_FOR_EVENT[$eventType] ?? 'auto';
    }

    /**
     * Map a model `fields` array to ONE Data Safe record (system-name => raw
     * value) for the given FS event type.
     *
     * @param array<int,array{name?:string,value?:string}> $fields
     * @return array<string,string>
     */
    public static function toFsRecord(array $fields, string $eventType): array
    {
        $v = [];
        foreach ($fields as $f) {
            $name = strtolower(trim((string) ($f['name'] ?? '')));
            if ($name !== '') {
                $v[$name] = trim((string) ($f['value'] ?? ''));
            }
        }

        $rec = [];
        $put = static function (string $sys, string $val) use (&$rec): void {
            if ($val !== '') {
                $rec[$sys] = $val;
            }
        };

        // Principal name (births/deaths) or groom (marriage).
        $put('PR_NAME_SURN_ORIG', $v['surname'] ?? $v['groom_surname'] ?? '');
        $put('PR_NAME_GN_ORIG', $v['first_names'] ?? $v['groom_first_names'] ?? '');

        // The event date -> the right day/month/year fields for this event type.
        $eventDate = $v['date_of_birth'] ?? $v['date_of_death'] ?? $v['date_of_marriage'] ?? '';
        [$d, $m, $y] = self::splitDate($eventDate);
        [$dayF, $monF, $yearF] = match ($eventType) {
            'Baptism' => ['PR_BIR_DAY_ORIG', 'PR_BIR_MONTH_ORIG', 'PR_BIR_YEAR_ORIG'],
            'Burial'  => ['PR_DEA_DAY_ORIG', 'PR_DEA_MONTH_ORIG', 'PR_DEA_YEAR_ORIG'],
            default   => ['EVENT_DAY_ORIG', 'EVENT_MONTH_ORIG', 'EVENT_YEAR_ORIG'],
        };
        $put($dayF, $d);
        $put($monF, $m);
        $put($yearF, $y);

        // Parents (births).
        if (! empty($v['father_name'])) {
            [$gn, $sn] = self::splitName($v['father_name']);
            $put('PR_FTHR_NAME_GN_ORIG', $gn);
            $put('PR_FTHR_NAME_SURN_ORIG', $sn);
        }
        if (! empty($v['mother_name'])) {
            [$gn, $sn] = self::splitName($v['mother_name']);
            $put('PR_MTHR_NAME_GN_ORIG', $gn);
            $put('PR_MTHR_NAME_SURN_ORIG', $sn);
        }

        // Death extras.
        $put('PR_AGE_ORIG', $v['age'] ?? '');

        // Marriage: bride -> spouse.
        $put('SP_NAME_SURN_ORIG', $v['bride_surname'] ?? '');
        $put('SP_NAME_GN_ORIG', $v['bride_first_names'] ?? '');

        // No Data Safe home: registration_number, place_*, district,
        // cause_of_death, witness_* -> intentionally dropped (EVENT_PLACE is
        // left blank per the keying spec; the rest have no Data Safe column).

        return $rec;
    }

    /**
     * The representative Data Safe system field a given model field maps to,
     * for the review-grid overlay (box -> cell link). Null when the model field
     * has no Data Safe home (registration_number, place_*, witness_*, ...).
     */
    public static function fsFieldFor(string $modelName, string $eventType): ?string
    {
        $n = strtolower(trim($modelName));
        $dateYear = match ($eventType) {
            'Baptism' => 'PR_BIR_YEAR_ORIG',
            'Burial'  => 'PR_DEA_YEAR_ORIG',
            default   => 'EVENT_YEAR_ORIG',
        };

        return match ($n) {
            'surname', 'groom_surname'         => 'PR_NAME_SURN_ORIG',
            'first_names', 'groom_first_names' => 'PR_NAME_GN_ORIG',
            'bride_surname'                    => 'SP_NAME_SURN_ORIG',
            'bride_first_names'                => 'SP_NAME_GN_ORIG',
            'date_of_birth', 'date_of_death', 'date_of_marriage' => $dateYear,
            'father_name'                      => 'PR_FTHR_NAME_GN_ORIG',
            'mother_name'                      => 'PR_MTHR_NAME_GN_ORIG',
            'age'                              => 'PR_AGE_ORIG',
            default                            => null,
        };
    }

    /** Split a combined personal name into [given, surname] (surname = last token). */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) <= 1) {
            return ['', $parts[0] ?? ''];
        }
        $surname = array_pop($parts);

        return [implode(' ', $parts), $surname];
    }

    /** Pull [day, month, year] out of a free-text date. Raw; FsKeyingRules formats. */
    public static function splitDate(string $date): array
    {
        $date = trim($date);
        if ($date === '') {
            return ['', '', ''];
        }
        $year = '';
        if (preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', $date, $m)) {
            $year = $m[1];
        }
        $month = '';
        if (preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/i', $date, $m)) {
            $month = $m[1];
        } elseif (preg_match('#\b(\d{1,2})[/\-.](\d{1,2})[/\-.]#', $date, $m)) {
            $month = $m[2]; // numeric dd/mm
        }
        $day = '';
        if (preg_match('/\b([0-3]?\d)\b/', $date, $m) && $m[1] !== $year) {
            $day = $m[1];
        }

        return [$day, $month, $year];
    }
}
