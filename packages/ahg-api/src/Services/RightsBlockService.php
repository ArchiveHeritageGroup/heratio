<?php

/**
 * RightsBlockService - a description's rights and Local Contexts labels for
 * GET /api/v2/descriptions/{slug}, in the shape PSIS sends (atom-ahg-plugins
 * v3.121.89, ahgAPIPlugin/lib/ApiRights.php), so the AHG Archives MCP reads
 * both catalogues the same way:
 *
 *   ['rules' => [['statement' => {iri, code, label}|null, 'licence' => ...|null,
 *                 'holder' => {name, iri}|null, 'starts_on', 'ends_on',
 *                 'grants' => {act: {restriction, note}}]],
 *    'labels' => [['iri', 'code', 'label', 'source_code']]]
 *
 * Heratio's tables: object_rights_statement (statements set on the record),
 * rights_record (extended rights: statement, licence, holder, dates) and
 * icip_tk_label (TK/BC labels; #1464 moved label assignments there), plus
 * object_rights_holder (donors), and the PREMIS rights / granted_right tables
 * (linked by a RIGHT relation). Each is read only when present. Only rights
 * in force today. Holders go to staff only: a holder is often a donor.
 *
 * grants (#1558) carry the PREMIS acts in OpenRiC Workspace's codes (render,
 * disseminate, use, ...; allow / disallow / conditional), from rights_grant
 * and granted_right. PSIS still sends {}. The MCP withholds a file when
 * render, disseminate or use is disallowed.
 *
 * Label codes are Local Contexts codes, worked out from the label's address,
 * then its English name, never from the stored code; the stored code is kept
 * as source_code and an unknown label becomes "local:<code>".
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgApi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RightsBlockService
{
    /** Local Contexts label address slug => code (localcontexts.org/label/<slug>/). Same table as PSIS. */
    public const LC_CODES = [
        'tk-attribution' => 'tk_a', 'tk-clan' => 'tk_cl', 'tk-family' => 'tk_f', 'tk-multiple-communities' => 'tk_mc',
        'tk-non-commercial' => 'tk_nc', 'tk-outreach' => 'tk_o', 'tk-secret-sacred' => 'tk_s', 'tk-verified' => 'tk_v',
        'tk-non-verified' => 'tk_nv', 'tk-culturally-sensitive' => 'tk_cs', 'tk-community-voice' => 'tk_cv',
        'tk-community-use-only' => 'tk_co', 'tk-women-restricted' => 'tk_wr', 'tk-women-general' => 'tk_wg',
        'tk-men-restricted' => 'tk_mr', 'tk-men-general' => 'tk_mg', 'tk-seasonal' => 'tk_ss', 'tk-open-to-collaboration' => 'tk_cb',
        'bc-provenance' => 'bc_p', 'bc-multiple-communities' => 'bc_mc', 'bc-clan' => 'bc_cl', 'bc-consent-verified' => 'bc_cv',
        'bc-consent-non-verified' => 'bc_cnv', 'bc-research-use' => 'bc_r', 'bc-outreach' => 'bc_o', 'bc-non-commercial' => 'bc_cnc',
        'bc-open-to-collaboration' => 'bc_cb',
    ];

    /** relation.type_id linking a description to a PREMIS right (the AtoM term RIGHT). */
    public const RIGHT_RELATION = 168;

    /** granted_right.restriction => the code OpenRiC and rights_grant use. */
    private const PREMIS_RESTRICTION = [0 => 'disallow', 1 => 'allow', 2 => 'conditional'];

    /** What to send when the rights cannot be read: never an empty block. */
    public const UNAVAILABLE = ['rules' => [], 'labels' => [], 'unavailable' => true];

    public function forObject(int $objectId, bool $staff, string $culture = 'en'): array
    {
        $rules = [];
        $labels = [];
        $today = now()->toDateString();
        $has = fn (string $t) => Schema::hasTable($t);

        // Rights statements set directly on the description.
        if ($has('object_rights_statement') && $has('rights_statement')) {
            foreach (DB::table('object_rights_statement')->where('object_id', $objectId)->orderBy('id')->pluck('rights_statement_id') as $sid) {
                if ($s = $this->statement((int) $sid, $culture)) {
                    $rules[] = $this->rule($s, null, null, null, null);
                }
            }
        }

        // Extended rights in force today: statement, licence, holder and dates.
        if ($has('rights_record')) {
            $rows = DB::table('rights_record')->where('object_id', $objectId)
                ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $today))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $today))
                ->orderBy('id')->get();
            foreach ($rows as $r) {
                $rules[] = $this->rule(
                    $r->rights_statement_id ? $this->statement((int) $r->rights_statement_id, $culture) : null,
                    $r->cc_license_id ? $this->licence((int) $r->cc_license_id) : null,
                    $staff ? $this->holder($r, $culture) : null,
                    $r->start_date,
                    $r->end_date,
                    $this->recordGrants((int) $r->id, $today, $culture)
                );
            }
        }

        // PREMIS rights (the rights / granted_right tables), linked to the
        // description by a relation of type RIGHT. Holder for staff only.
        if ($has('rights') && $has('granted_right') && $has('relation')) {
            $rows = DB::table('relation as rel')->join('rights as x', 'x.id', '=', 'rel.object_id')
                ->where('rel.subject_id', $objectId)->where('rel.type_id', self::RIGHT_RELATION)
                ->where(fn ($q) => $q->whereNull('x.start_date')->orWhere('x.start_date', '<=', $today))
                ->where(fn ($q) => $q->whereNull('x.end_date')->orWhere('x.end_date', '>=', $today))
                ->orderBy('x.id')->get(['x.id', 'x.start_date', 'x.end_date', 'x.rights_holder_id']);
            foreach ($rows as $r) {
                $holder = $staff && $r->rights_holder_id
                    ? DB::table('actor_i18n')->where('id', $r->rights_holder_id)->where('culture', $culture)->value('authorized_form_of_name')
                    : null;
                $rules[] = $this->rule(null, null, $holder ? ['name' => $holder, 'iri' => null] : null,
                    $r->start_date, $r->end_date, $this->premisGrants((int) $r->id, $today));
            }
        }

        // TK / BC labels.
        if ($has('icip_tk_label') && $has('icip_tk_label_type')) {
            $q = DB::table('icip_tk_label as x')->join('icip_tk_label_type as t', 't.id', '=', 'x.label_type_id')
                ->where('x.information_object_id', $objectId)->orderBy('x.id')
                ->select('t.code', 't.name', 't.local_contexts_url');
            foreach ($q->get() as $l) {
                $labels[] = $this->label($l->local_contexts_url, $l->name, (string) $l->code);
            }
        }

        // Holders recorded as donors (staff only).
        if ($staff && $has('object_rights_holder')) {
            $names = DB::table('object_rights_holder as h')
                ->leftJoin('actor_i18n as a', fn ($j) => $j->on('a.id', '=', 'h.donor_id')->where('a.culture', '=', $culture))
                ->where('h.object_id', $objectId)->orderBy('h.id')->pluck('a.authorized_form_of_name');
            foreach ($names as $name) {
                if ($name) {
                    $rules[] = $this->rule(null, null, ['name' => $name, 'iri' => null], null, null);
                }
            }
        }

        $seen = [];
        $labels = array_values(array_filter($labels, function ($l) use (&$seen) {
            return ! isset($seen[$l['code']]) && ($seen[$l['code']] = true);
        }));

        return ['rules' => $rules, 'labels' => $labels];
    }

    /** The Local Contexts code of a label, from its address, then its English name. */
    public static function localContextsCode(?string $uri, ?string $name, string $sourceCode): string
    {
        if ($uri && preg_match('#localcontexts\.org/label/([a-z-]+)/?#', $uri, $m) && isset(self::LC_CODES[$m[1]])) {
            return self::LC_CODES[$m[1]];
        }
        $slug = trim((string) preg_replace('/[^a-z]+/', '-', strtolower(str_replace('/', '-', (string) $name))), '-');
        if (isset(self::LC_CODES[$slug])) {
            return self::LC_CODES[$slug];
        }

        // Not a Local Contexts label we know: keep it visible, never mistaken for one.
        return 'local:'.$sourceCode;
    }

    /**
     * Licence code: "CC-" + the stored code upper-cased, the version added only
     * if the code does not already end in it; CC0 is "CC0-1.0". A stored code
     * for the Public Domain Mark (PDM-1.0), which is not a CC licence, is kept as is.
     */
    public static function licenceCode(string $stored, ?string $version): string
    {
        if (preg_match('/^cc[-_ ]?0/i', $stored)) {
            $code = 'CC0';
        } elseif (preg_match('/^pdm\b/i', $stored)) {
            $code = strtoupper($stored); // the Public Domain Mark is not a CC licence
        } else {
            $code = 'CC-'.strtoupper((string) preg_replace('/^cc[-_ ]?/i', '', $stored));
        }
        if ($version && ! preg_match('/-'.preg_quote($version, '/').'$/i', $code)) {
            $code .= '-'.$version;
        }

        return $code;
    }

    private function label(?string $uri, ?string $name, string $sourceCode): array
    {
        $code = self::localContextsCode($uri, $name, $sourceCode);
        $known = ! str_starts_with($code, 'local:');

        return [
            'iri' => $known ? 'https://localcontexts.org/label/'.array_search($code, self::LC_CODES, true).'/' : ($uri ?: null),
            'code' => $code,
            'label' => $name ?: $sourceCode,
            'source_code' => $sourceCode,
        ];
    }

    /** @param array<string, array{restriction: string, note: ?string}> $grants  PREMIS act => restriction */
    private function rule(?array $statement, ?array $licence, ?array $holder, $starts, $ends, array $grants = []): array
    {
        // grants is a JSON object keyed by act, as OpenRiC Workspace sends it; {} when none.
        return ['statement' => $statement, 'licence' => $licence, 'holder' => $holder,
            'starts_on' => $starts ?: null, 'ends_on' => $ends ?: null, 'grants' => $grants ?: (object) []];
    }

    /**
     * An extended-rights record's grants in force today. rights_grant already
     * stores the OpenRiC codes (act render/disseminate/use/..., restriction
     * allow/disallow/conditional), so they pass through.
     */
    private function recordGrants(int $recordId, string $today, string $culture): array
    {
        if (! Schema::hasTable('rights_grant')) {
            return [];
        }
        $out = [];
        $rows = DB::table('rights_grant as g')
            ->leftJoin('rights_grant_i18n as gi', fn ($j) => $j->on('gi.id', '=', 'g.id')->where('gi.culture', '=', $culture))
            ->where('g.rights_record_id', $recordId)
            ->where(fn ($q) => $q->whereNull('g.start_date')->orWhere('g.start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('g.end_date')->orWhere('g.end_date', '>=', $today))
            ->orderBy('g.id')->get(['g.act', 'g.restriction', 'gi.restriction_note']);
        foreach ($rows as $g) {
            if ($g->act) {
                $out[strtolower($g->act)] = ['restriction' => strtolower((string) $g->restriction ?: 'allow'), 'note' => $g->restriction_note ?: null];
            }
        }

        return $out;
    }

    /**
     * A PREMIS right's granted acts in force today. The act is a term (Display,
     * Disseminate, ...); Display is the act OpenRiC and rights_grant call render.
     */
    private function premisGrants(int $rightsId, string $today): array
    {
        $out = [];
        $rows = DB::table('granted_right as g')
            ->leftJoin('term_i18n as t', fn ($j) => $j->on('t.id', '=', 'g.act_id')->where('t.culture', '=', 'en'))
            ->where('g.rights_id', $rightsId)
            ->where(fn ($q) => $q->whereNull('g.start_date')->orWhere('g.start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('g.end_date')->orWhere('g.end_date', '>=', $today))
            ->orderBy('g.id')->get(['t.name', 'g.restriction', 'g.notes']);
        foreach ($rows as $g) {
            $act = strtolower(trim((string) $g->name));
            if ($act === '' || ! isset(self::PREMIS_RESTRICTION[(int) $g->restriction])) {
                continue;
            }
            $out[$act === 'display' ? 'render' : $act] = ['restriction' => self::PREMIS_RESTRICTION[(int) $g->restriction], 'note' => $g->notes ?: null];
        }

        return $out;
    }

    private function holder(object $r, string $culture): ?array
    {
        $name = $r->copyright_holder ?: ($r->copyright_holder_actor_id
            ? DB::table('actor_i18n')->where('id', $r->copyright_holder_actor_id)->where('culture', $culture)->value('authorized_form_of_name')
            : null);

        return $name ? ['name' => $name, 'iri' => $r->rights_holder_uri ?: null] : null;
    }

    private function statement(int $id, string $culture): ?array
    {
        $s = DB::table('rights_statement as s')
            ->leftJoin('rights_statement_i18n as i', fn ($j) => $j->on('i.rights_statement_id', '=', 's.id')->where('i.culture', '=', $culture))
            ->where('s.id', $id)->select('s.code', 's.uri', 'i.name')->first();

        return $s ? ['iri' => $s->uri, 'code' => $s->code, 'label' => $s->name ?: $s->code] : null;
    }

    private function licence(int $id): ?array
    {
        if (! Schema::hasTable('rights_cc_license')) {
            return null;
        }
        $l = DB::table('rights_cc_license')->where('id', $id)->select('code', 'version', 'uri')->first();
        if (! $l) {
            return null;
        }
        $code = self::licenceCode((string) $l->code, $l->version);

        return ['iri' => $l->uri, 'code' => $code, 'label' => str_replace('-', ' ', $code)];
    }
}
