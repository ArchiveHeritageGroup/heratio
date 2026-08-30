<?php

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;

/**
 * Write `event` rows (ISAD(G) 3.1.3 Date(s)) correctly.
 *
 * `event` is a class-table-inheritance child of `object`: its id must be an
 * `object` row with class_name 'QubitEvent', and the FK cascades. Inserting
 * straight into `event` with an invented id produces a row that violates the
 * inheritance and is invisible to anything joining through `object`.
 *
 * A date has two halves and both are kept. start_date/end_date are normalised
 * and sortable; event_i18n.date is the expression a person wrote ("circa
 * 1890s", "n.d.", "1890-1899?"). Storing only the normalised pair loses the
 * uncertainty the archivist recorded; storing only the text loses the ability
 * to sort or range-query. Neither is sufficient on its own.
 */
class EventRow
{
    /** Taxonomy holding the event types (Creation, Custody, Publication, ...). */
    public const EVENT_TYPE_TAXONOMY = 40;

    /**
     * Resolve an event-type name to its term id within the Event Types taxonomy.
     *
     * Scoped to the taxonomy rather than matched on name alone: several
     * taxonomies contain a term called "Publication", and picking the wrong one
     * silently mis-types the date.
     */
    public static function resolveType(string $name): ?int
    {
        $id = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term_i18n.id', '=', 'term.id')->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', self::EVENT_TYPE_TAXONOMY)
            ->whereRaw('LOWER(term_i18n.name) = ?', [mb_strtolower(trim($name))])
            ->value('term.id');

        return $id ? (int) $id : null;
    }

    /** The type names this instance accepts, for validation messages and docs. */
    public static function types(): array
    {
        return DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term_i18n.id', '=', 'term.id')->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', self::EVENT_TYPE_TAXONOMY)
            ->orderBy('term_i18n.name')
            ->pluck('term_i18n.name')
            ->all();
    }

    /**
     * Replace the dates on a record.
     *
     * @param  array<int,array{type?:string,start_date?:?string,end_date?:?string,date_display?:?string}>  $dates
     * @return int number of events written
     */
    public static function replaceFor(int $objectId, array $dates, string $culture = 'en'): int
    {
        return DB::transaction(function () use ($objectId, $dates, $culture) {
            // Deleting the object row cascades to event and event_i18n.
            $existing = DB::table('event')->where('object_id', $objectId)->pluck('id');
            if ($existing->isNotEmpty()) {
                DB::table('object')->whereIn('id', $existing)->delete();
            }

            $written = 0;
            foreach ($dates as $d) {
                // Type is required by the API, so this fallback only fires for
                // internal callers. Creation is the conventional default for an
                // untyped date on an archival description.
                $typeId = isset($d['type']) && $d['type'] !== ''
                    ? self::resolveType((string) $d['type'])
                    : \AhgCore\Constants\TermId::EVENT_TYPE_CREATION;

                $now = now();
                $eventId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitEvent',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('event')->insert([
                    'id' => $eventId,
                    'type_id' => $typeId,
                    'object_id' => $objectId,
                    'start_date' => $d['start_date'] ?? null,
                    'end_date' => $d['end_date'] ?? null,
                    'source_culture' => $culture,
                ]);

                if (! empty($d['date_display'])) {
                    DB::table('event_i18n')->insert([
                        'id' => $eventId,
                        'culture' => $culture,
                        'date' => $d['date_display'],
                    ]);
                }

                $written++;
            }

            return $written;
        });
    }
}
