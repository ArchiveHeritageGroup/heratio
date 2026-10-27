<?php

/**
 * RightsBlockService - a description's rights and Local Contexts labels for
 * GET /api/v2/descriptions/{slug}, in the shape PSIS sends (atom-ahg-plugins
 * v3.121.89, ahgAPIPlugin/lib/ApiRights.php), so the AHG Archives MCP reads
 * both catalogues the same way:
 *
 *   ['rules' => [['statement' => {iri, code, label}|null, 'licence' => ...|null,
 *                 'holder' => {name, iri}|null, 'starts_on', 'ends_on', 'grants' => {}]],
 *    'labels' => [['iri', 'code', 'label', 'source_code']]]
 *
 * Heratio's tables: object_rights_statement (statements set on the record),
 * rights_record (extended rights: statement, licence, holder, dates) and
 * icip_tk_label (TK/BC labels; #1464 moved label assignments there), plus
 * object_rights_holder (donors). Each is read only when present. Only rights
 * in force today. Holders go to staff only: a holder is often a donor.
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
                    $r->end_date
                );
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

    private function rule(?array $statement, ?array $licence, ?array $holder, $starts, $ends): array
    {
        // ponytail: grants stay {} as in PSIS; rights_grant (act, restriction)
        // could fill them once the MCP reads grants.
        return ['statement' => $statement, 'licence' => $licence, 'holder' => $holder,
            'starts_on' => $starts ?: null, 'ends_on' => $ends ?: null, 'grants' => (object) []];
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
