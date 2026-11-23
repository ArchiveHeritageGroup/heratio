<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

use AhgActorManage\Services\ActorService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1495: resolves a source name to one authority record. Only plain
 * actors qualify (users and repositories share the actor table). Two possible
 * matches is a blocker, because linking to the wrong person cannot be seen later.
 * Names match case-insensitively but accent-sensitively: Muller is not Müller.
 */
class AuthorityResolver
{
    /** @var array<string,int> */
    private array $resolved = [];

    /** @param array<string,int> $plannedTypes nameKey => the type a typed column gave that name */
    public function __construct(private readonly string $culture, private readonly array $plannedTypes = []) {}

    public static function nameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /** Same name apart from case. @return list<int> */
    public function candidates(string $name, ?int $entityTypeId): array
    {
        return $this->sameUnderCollation($name, $entityTypeId)
            ->whereRaw('LOWER(actor_i18n.authorized_form_of_name) COLLATE utf8mb4_bin = ?', [self::nameKey($name)])
            ->orderBy('actor.id')->pluck('actor.id')->map(fn ($id) => (int) $id)->all();
    }

    /** Differs only by accents or equivalent letters (Muller, Müller). @return list<int> */
    public function nearMatches(string $name, ?int $entityTypeId): array
    {
        return $this->sameUnderCollation($name, $entityTypeId)
            ->whereRaw('LOWER(actor_i18n.authorized_form_of_name) COLLATE utf8mb4_bin <> ?', [self::nameKey($name)])
            ->orderBy('actor.id')->pluck('actor.id')->map(fn ($id) => (int) $id)->all();
    }

    /** Groups spellings the catalogue's name collation treats as equal. */
    public function foldKey(string $name): string
    {
        return (string) DB::selectOne(
            'SELECT HEX(WEIGHT_STRING(CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci)) AS k',
            [trim($name)]
        )->k;
    }

    private function sameUnderCollation(string $name, ?int $entityTypeId): Builder
    {
        $query = DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'object.id', '=', 'actor.id')
            ->where('object.class_name', 'QubitActor')
            ->where('actor_i18n.culture', $this->culture)
            ->where('actor_i18n.authorized_form_of_name', trim($name));
        if ($entityTypeId !== null) {
            $query->where(fn ($q) => $q->where('actor.entity_type_id', $entityTypeId)->orWhereNull('actor.entity_type_id'));
        }

        return $query;
    }

    public function resolve(string $name, ?int $entityTypeId): int
    {
        $entityTypeId ??= $this->plannedTypes[self::nameKey($name)] ?? null;
        $key = self::nameKey($name).'|'.$entityTypeId;
        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }
        $found = $this->candidates($name, $entityTypeId);
        if (count($found) > 1) {
            throw new \RuntimeException("Ambiguous authority: {$name}");
        }

        return $this->resolved[$key] = $found[0] ?? (new ActorService($this->culture))->create([
            'authorized_form_of_name' => trim($name),
            'entity_type_id' => $entityTypeId,
        ]);
    }
}
