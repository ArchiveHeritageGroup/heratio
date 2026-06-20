<?php

/**
 * VirtualReturnSpaceService - heratio#1207 "virtual-return 3D handoff".
 *
 * Hands a repatriation claim object's 3D model into an ORIGIN-CONTEXT exhibition
 * space, so the public (and the source community) can WALK the object in its own
 * context rather than only following a record link. Given a claim, it confirms the
 * underlying item is PUBLISHED and actually carries a 3D model (or Gaussian splat),
 * then idempotently provisions a small exhibition room named + annotated for the
 * claim's place / community of origin and places the object's 3D model at its
 * centre (via the exhibition twin's own ExhibitionSpaceService). The claim->space
 * link is cached on displaced_heritage_claim.virtual_return_space_id so repeat
 * visits reuse the same room.
 *
 * Dignified + safe: published items only (never a back door to a draft record),
 * no 3D model means no handoff, and everything is fail-soft - any failure returns
 * null and the virtual-return page simply omits the walk button. All exhibition
 * writes go through ExhibitionSpaceService (the twin's sanctioned door), never raw.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class VirtualReturnSpaceService
{
    protected RepatriationClaimService $claims;

    public function __construct()
    {
        $this->claims = new RepatriationClaimService;
    }

    /**
     * Provision (idempotently) the origin-context space for a claim and place the
     * object's 3D model in it. Returns ['space_id', 'slug'] or null when the item
     * is unpublished, has no 3D model, or anything fails (caller redirects back).
     *
     * @return array{space_id:int,slug:string}|null
     */
    public function provisionFor(int $claimId): ?array
    {
        $context = $this->context($claimId);
        if ($context === null || ! $this->canWalk($context)) {
            return null;
        }

        $claim = $context['claim'] ?? [];
        $item = $context['item'] ?? [];
        $itemRef = (int) ($claim['item_ref'] ?? 0);
        $spaces = app(\AhgExhibition\Services\ExhibitionSpaceService::class);

        // Reuse a previously provisioned space if it still exists.
        $existingId = $this->storedSpaceId($claimId);
        if ($existingId > 0 && DB::table('ahg_exhibition_space')->where('id', $existingId)->exists()) {
            return ['space_id' => $existingId, 'slug' => (string) DB::table('ahg_exhibition_space')->where('id', $existingId)->value('slug')];
        }

        $title = trim((string) ($item['title'] ?? ('#'.$itemRef)));
        $origin = trim((string) ($claim['origin_place'] ?? ''));
        $community = trim((string) ($claim['claimant_community'] ?? ''));
        $name = $origin !== '' ? "{$title} - virtual return to {$origin}" : "{$title} - virtual return";
        $notes = trim('Virtual return of "'.$title.'" shown in its origin context.'
            .($origin !== '' ? ' Place of origin: '.$origin.'.' : '')
            .($community !== '' ? ' Claimant community: '.$community.'.' : '')
            .' Repatriation engine #1207. '.RepatriationClaimService::DISCLAIMER);

        try {
            $spaceId = $spaces->create([
                'name' => $name,
                'space_type' => 'gallery',
                'notes' => $notes,
                'room_w' => 8, 'room_d' => 6, 'room_h' => 4,
            ]);
            $spaces->createPlacementAt($spaceId, $itemRef, 0.5, 0.5, 0);
        } catch (\Throwable $e) {
            Log::info('[virtual-return] space provisioning failed for claim '.$claimId.': '.$e->getMessage());

            return null;
        }

        $this->storeSpaceId($claimId, $spaceId);

        return ['space_id' => $spaceId, 'slug' => (string) DB::table('ahg_exhibition_space')->where('id', $spaceId)->value('slug')];
    }

    /**
     * Can this claim be walked? True only for a PUBLISHED item that carries a 3D
     * model (dedicated model / glTF-family digital object) or a Gaussian splat.
     * Drives the page button without provisioning anything.
     */
    public function canWalk(array $context): bool
    {
        $item = $context['item'] ?? null;                 // null = unpublished / absent
        $itemRef = (int) (($context['claim'] ?? [])['item_ref'] ?? 0);
        if ($item === null || $itemRef <= 0 || ! class_exists(\AhgExhibition\Services\ExhibitionSpaceService::class)) {
            return false;
        }
        try {
            $media = app(\AhgExhibition\Services\ExhibitionSpaceService::class)->getObjectMedia($itemRef);
        } catch (\Throwable $e) {
            return false;
        }

        return ($media['kind'] ?? '') === '3d' || ! empty($media['splat_url'] ?? null);
    }

    /** Assemble the claim virtual-return context, fail-soft. */
    public function context(int $claimId): ?array
    {
        try {
            $context = $this->claims->virtualReturn($claimId);
        } catch (\Throwable $e) {
            return null;
        }

        return is_array($context) ? $context : null;
    }

    private function storedSpaceId(int $claimId): int
    {
        if (! Schema::hasColumn('displaced_heritage_claim', 'virtual_return_space_id')) {
            return 0;
        }

        return (int) (DB::table('displaced_heritage_claim')->where('id', $claimId)->value('virtual_return_space_id') ?? 0);
    }

    private function storeSpaceId(int $claimId, int $spaceId): void
    {
        if (! Schema::hasColumn('displaced_heritage_claim', 'virtual_return_space_id')) {
            return;
        }
        DB::table('displaced_heritage_claim')->where('id', $claimId)->update([
            'virtual_return_space_id' => $spaceId,
            'updated_at' => now(),
        ]);
    }
}
