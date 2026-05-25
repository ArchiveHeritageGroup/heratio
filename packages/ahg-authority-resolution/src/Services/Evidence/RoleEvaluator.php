<?php

/**
 * RoleEvaluator - Service for Heratio
 *
 * Task-4 role evidence for PERSON / ORG candidates. Scans the candidate
 * actor's free-text history (and functions / mandates / legal_status fields
 * if present) for any token captured in the mention's role_language_tokens.
 *
 * Signals:
 *   match    - at least one role-language token (from the mention's context)
 *              appears literally in the candidate's actor_i18n history text
 *   silent   - candidate HAS history text AND mention HAS role tokens, but no
 *              token appears in the history
 *   absent   - candidate has no history text OR mention has no role tokens
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgAuthorityResolution\Services\Evidence;

use Illuminate\Support\Facades\DB;

class RoleEvaluator implements EvaluatorInterface
{
    private const PERSON_ORG_TYPES = ['PERSON', 'ORG'];

    public function dimension(): string
    {
        return 'role';
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, self::PERSON_ORG_TYPES, true);
    }

    public function evaluate(object $mention, object $context, object $candidate): array
    {
        $candSource = (string) ($candidate->candidate_source ?? '');
        if (! in_array($candSource, ['mysql_actor', 'fuseki_agent'], true)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'candidate_source_not_actor']);
        }

        $authId = $candidate->candidate_authority_id ?? null;
        if ($authId === null) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, ['reason' => 'no_authority_id']);
        }

        $roleTokens = $this->extractRoleTokens($context->role_language_tokens ?? null);
        $candText = $this->candidateHistoryText((int) $authId);

        if (empty($roleTokens) && ($candText === null || $candText === '')) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_role_tokens_and_no_candidate_history',
            ]);
        }
        if (empty($roleTokens)) {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_role_tokens_in_mention',
                'has_candidate_history' => true,
            ]);
        }
        if ($candText === null || $candText === '') {
            return EvidenceSignal::make(EvidenceSignal::ABSENT, [
                'reason' => 'no_candidate_history_text',
                'role_tokens' => $roleTokens,
            ]);
        }

        $candLower = mb_strtolower($candText);
        $hits = [];
        foreach ($roleTokens as $tok) {
            $tokLower = mb_strtolower($tok);
            if ($tokLower === '') {
                continue;
            }
            if (str_contains($candLower, $tokLower)) {
                $hits[] = $tok;
            }
        }

        if (! empty($hits)) {
            return EvidenceSignal::make(EvidenceSignal::MATCH, [
                'matched_tokens' => array_values(array_unique($hits)),
                'all_role_tokens' => $roleTokens,
            ]);
        }

        return EvidenceSignal::make(EvidenceSignal::SILENT, [
            'reason' => 'no_token_appears_in_history',
            'role_tokens' => $roleTokens,
        ]);
    }

    /**
     * @return list<string> Role-language tokens (kinship / witness / location / etc.)
     */
    private function extractRoleTokens($roleTokensJson): array
    {
        $rows = EvidenceDateUtil::decodeJsonish($roleTokensJson);
        if (! is_array($rows)) {
            return [];
        }
        $toks = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['token']) && $row['token'] !== '') {
                $toks[] = (string) $row['token'];
            } elseif (is_string($row) && $row !== '') {
                $toks[] = $row;
            }
        }

        return array_values(array_unique($toks));
    }

    private function candidateHistoryText(int $actorId): ?string
    {
        $row = DB::table('actor_i18n')
            ->where('id', $actorId)
            ->orderByRaw("CASE WHEN culture = 'en' THEN 0 ELSE 1 END")
            ->first(['history', 'functions', 'mandates', 'legal_status']);

        if (! $row) {
            return null;
        }
        $parts = [
            (string) ($row->history ?? ''),
            (string) ($row->functions ?? ''),
            (string) ($row->mandates ?? ''),
            (string) ($row->legal_status ?? ''),
        ];
        $combined = trim(implode(' ', array_filter($parts, fn ($p) => $p !== '')));

        return $combined === '' ? null : $combined;
    }
}
