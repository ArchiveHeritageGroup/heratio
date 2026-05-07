<?php

/**
 * ResearchService - Service for Heratio
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



namespace AhgResearch\Services;

use AhgCore\Services\AhgSettingsService;
use AhgResearch\Mail\BookingCancelledMail;
use AhgResearch\Mail\BookingConfirmedMail;
use AhgResearch\Mail\BookingCreatedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * ResearchService - Core Research Portal Service
 *
 * Handles researcher management, bookings, collections, annotations,
 * citations, saved searches, researcher types, and verification.
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/ResearchService.php
 */
class ResearchService
{
    // =========================================================================
    // RESEARCHER MANAGEMENT
    // =========================================================================

    public function getResearcherByUserId(int $userId): ?object
    {
        return DB::table('research_researcher')->where('user_id', $userId)->first();
    }

    public function getResearcher(int $id): ?object
    {
        return DB::table('research_researcher')->where('id', $id)->first();
    }

    public function getResearcherByEmail(string $email): ?object
    {
        return DB::table('research_researcher')->where('email', $email)->first();
    }

    public function registerResearcher(array $data): int
    {
        $researcherId = DB::table('research_researcher')->insertGetId([
            'user_id' => $data['user_id'],
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'affiliation_type' => $data['affiliation_type'] ?? 'independent',
            'institution' => $data['institution'] ?? null,
            'department' => $data['department'] ?? null,
            'position' => $data['position'] ?? null,
            'student_id' => $data['student_id'] ?? null,
            'research_interests' => $data['research_interests'] ?? null,
            'current_project' => $data['current_project'] ?? null,
            'orcid_id' => $data['orcid_id'] ?? null,
            'id_type' => ($data['id_type'] ?? null) ?: null,
            'id_number' => $data['id_number'] ?? null,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create access request for researcher approval
        DB::table('access_request')->insert([
            'request_type' => 'researcher',
            'scope_type' => 'single',
            'user_id' => $data['user_id'],
            'requested_classification_id' => 2,
            'current_classification_id' => 1,
            'reason' => 'New researcher registration: ' . $data['first_name'] . ' ' . $data['last_name'],
            'justification' => $data['research_interests'] ?? $data['current_project'] ?? null,
            'urgency' => 'normal',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logAudit('create', 'Researcher', $researcherId, [], $data, $data['first_name'] . ' ' . $data['last_name']);
        return $researcherId;
    }

    public function updateResearcher(int $id, array $data): bool
    {
        $oldValues = (array)(DB::table('research_researcher')->where('id', $id)->first() ?? new \stdClass);
        $data['updated_at'] = date('Y-m-d H:i:s');
        $result = DB::table('research_researcher')->where('id', $id)->update($data) > 0;
        if ($result) {
            $newValues = (array)(DB::table('research_researcher')->where('id', $id)->first() ?? new \stdClass);
            $this->logAudit('update', 'Researcher', $id, $oldValues, $newValues, ($newValues['first_name'] ?? '') . ' ' . ($newValues['last_name'] ?? ''));
        }
        return $result;
    }

    public function approveResearcher(int $id, int $approvedBy, ?string $expiresAt = null): bool
    {
        $oldValues = (array)(DB::table('research_researcher')->where('id', $id)->first() ?? new \stdClass);
        $result = DB::table('research_researcher')->where('id', $id)->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt ?? date('Y-m-d', strtotime('+1 year')),
        ]) > 0;
        if ($result) {
            $newValues = (array)(DB::table('research_researcher')->where('id', $id)->first() ?? new \stdClass);
            $this->logAudit('approve', 'Researcher', $id, $oldValues, $newValues, ($newValues['first_name'] ?? '') . ' ' . ($newValues['last_name'] ?? ''));
        }
        return $result;
    }

    public function getResearchers(array $filters = []): array
    {
        $query = DB::table('research_researcher');
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('first_name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'LIKE', '%' . $filters['search'] . '%');
            });
        }
        return $query->orderBy('last_name')->get()->toArray();
    }

    // =========================================================================
    // READING ROOMS & BOOKINGS
    // =========================================================================

    public function getReadingRooms(bool $activeOnly = true): array
    {
        $query = DB::table('research_reading_room');
        if ($activeOnly) $query->where('is_active', 1);
        return $query->orderBy('name')->get()->toArray();
    }

    public function getReadingRoom(int $id): ?object
    {
        return DB::table('research_reading_room')->where('id', $id)->first();
    }

    public function createBooking(array $data): int
    {
        $bookingId = DB::table('research_booking')->insertGetId([
            'researcher_id' => $data['researcher_id'],
            'reading_room_id' => $data['reading_room_id'],
            'booking_date' => $data['booking_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'purpose' => $data['purpose'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logAudit('create', 'ResearchBooking', $bookingId, [], $data, 'Booking ' . $data['booking_date']);

        $this->sendBookingMail($bookingId, fn ($b) => new BookingCreatedMail($b));

        return $bookingId;
    }

    public function addMaterialRequest(int $bookingId, int $objectId, ?string $notes = null): int
    {
        return DB::table('research_material_request')->insertGetId([
            'booking_id' => $bookingId,
            'object_id' => $objectId,
            'notes' => $notes,
            'status' => 'requested',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getBooking(int $id): ?object
    {
        $booking = DB::table('research_booking as b')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.id', $id)
            ->select('b.*', 'r.first_name', 'r.last_name', 'r.email', 'r.institution',
                'rm.name as room_name', 'rm.location as room_location')
            ->first();
        if ($booking) {
            $booking->materials = DB::table('research_material_request as m')
                ->leftJoin('information_object_i18n as i18n', function ($join) {
                    $join->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })
                ->where('m.booking_id', $id)
                ->select('m.*', 'i18n.title as object_title')
                ->get()->toArray();
        }
        return $booking;
    }

    public function getResearcherBookings(int $researcherId, ?string $status = null): array
    {
        $query = DB::table('research_booking as b')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.researcher_id', $researcherId);
        if ($status) $query->where('b.status', $status);
        return $query->select('b.*', 'rm.name as room_name')
            ->orderBy('b.booking_date', 'desc')->get()->toArray();
    }

    public function confirmBooking(int $id, int $confirmedBy): bool
    {
        $oldValues = (array)(DB::table('research_booking')->where('id', $id)->first() ?? new \stdClass);
        $result = DB::table('research_booking')->where('id', $id)->update([
            'status' => 'confirmed',
            'confirmed_by' => $confirmedBy,
            'confirmed_at' => date('Y-m-d H:i:s'),
        ]) > 0;
        if ($result) {
            $newValues = (array)(DB::table('research_booking')->where('id', $id)->first() ?? new \stdClass);
            $this->logAudit('confirm', 'ResearchBooking', $id, $oldValues, $newValues, null);

            $this->sendBookingMail($id, fn ($b) => new BookingConfirmedMail($b));
        }
        return $result;
    }

    public function cancelBooking(int $id, ?string $reason = null): bool
    {
        $oldValues = (array)(DB::table('research_booking')->where('id', $id)->first() ?? new \stdClass);
        $result = DB::table('research_booking')->where('id', $id)->update([
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancellation_reason' => $reason,
        ]) > 0;
        if ($result) {
            $newValues = (array)(DB::table('research_booking')->where('id', $id)->first() ?? new \stdClass);
            $this->logAudit('cancel', 'ResearchBooking', $id, $oldValues, $newValues, null);

            $this->sendBookingMail($id, fn ($b) => new BookingCancelledMail($b, $reason));
        }
        return $result;
    }

    /**
     * Dispatch a booking-related email when research_email_notifications is enabled.
     *
     * Gated, idempotent against missing recipient address, and never throws -
     * mail-delivery failure must not roll back the booking action that triggered it.
     */
    protected function sendBookingMail(int $bookingId, callable $mailableFactory): void
    {
        if (!AhgSettingsService::getBool('research_email_notifications', true)) {
            return;
        }

        try {
            $booking = $this->getBooking($bookingId);
            if (!$booking || empty($booking->email)) {
                return;
            }

            Mail::to($booking->email)->send($mailableFactory($booking));
        } catch (\Throwable $e) {
            Log::warning('[research] booking mail failed', [
                'booking_id' => $bookingId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function checkIn(int $bookingId): bool
    {
        return DB::table('research_booking')->where('id', $bookingId)
            ->update(['checked_in_at' => date('Y-m-d H:i:s')]) > 0;
    }

    public function checkOut(int $bookingId): bool
    {
        return DB::table('research_booking')->where('id', $bookingId)->update([
            'status' => 'completed',
            'checked_out_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    // =========================================================================
    // SAVED SEARCHES
    // =========================================================================

    public function saveSearch(int $researcherId, array $data): int
    {
        return DB::table('research_saved_search')->insertGetId([
            'researcher_id' => $researcherId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'search_query' => $data['search_query'],
            'search_filters' => isset($data['search_filters']) ? json_encode($data['search_filters']) : null,
            'alert_enabled' => $data['alert_enabled'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getSavedSearches(int $researcherId): array
    {
        return DB::table('research_saved_search')
            ->where('researcher_id', $researcherId)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();
    }

    public function deleteSavedSearch(int $id, int $researcherId): bool
    {
        return DB::table('research_saved_search')
            ->where('id', $id)
            ->where('researcher_id', $researcherId)
            ->delete() > 0;
    }

    // =========================================================================
    // COLLECTIONS (Evidence Sets)
    // =========================================================================

    public function createCollection(int $researcherId, array $data): int
    {
        return DB::table('research_collection')->insertGetId([
            'researcher_id' => $researcherId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_public' => $data['is_public'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getCollections(int $researcherId): array
    {
        return DB::table('research_collection')
            ->where('researcher_id', $researcherId)
            ->orderBy('name')
            ->get()->toArray();
    }

    public function getCollection(int $id): ?object
    {
        $collection = DB::table('research_collection')->where('id', $id)->first();
        if ($collection) {
            $collection->items = DB::table('research_collection_item as ci')
                ->leftJoin('information_object as io', 'ci.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n', function ($join) {
                    $join->on('ci.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
                })
                ->leftJoin('slug as s', 'ci.object_id', '=', 's.object_id')
                ->leftJoin('term_i18n as lod', function ($join) {
                    $join->on('io.level_of_description_id', '=', 'lod.id')->where('lod.culture', '=', 'en');
                })
                ->where('ci.collection_id', $id)
                ->select('ci.*', 'i18n.title as object_title', 's.slug as object_slug', 'io.identifier', 'lod.name as level_of_description')
                ->orderBy('ci.created_at')
                ->get()->toArray();
        }
        return $collection;
    }

    public function addToCollection(int $collectionId, int $objectId, ?string $notes = null): int
    {
        return DB::table('research_collection_item')->insertGetId([
            'collection_id' => $collectionId,
            'object_id' => $objectId,
            'notes' => $notes ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function removeFromCollection(int $collectionId, int $objectId): bool
    {
        return DB::table('research_collection_item')
            ->where('collection_id', $collectionId)
            ->where('object_id', $objectId)
            ->delete() > 0;
    }

    public function getCollectionFindingAidData(int $collectionId, int $researcherId): ?array
    {
        $collection = DB::table('research_collection')
            ->where('id', $collectionId)
            ->where('researcher_id', $researcherId)
            ->first();
        if (!$collection) return null;

        $items = DB::table('research_collection_item as ci')
            ->leftJoin('information_object as io', 'ci.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('ci.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'ci.object_id', '=', 's.object_id')
            ->where('ci.collection_id', $collectionId)
            ->select('ci.*', 'i18n.title as object_title', 'io.identifier', 's.slug as object_slug',
                'i18n.scope_and_content', 'i18n.extent_and_medium')
            ->orderBy('ci.created_at')
            ->get()->toArray();

        return [
            'collection' => $collection,
            'items' => $items,
        ];
    }

    // =========================================================================
    // ANNOTATIONS
    // =========================================================================

    public function getAnnotations(int $researcherId): array
    {
        return DB::table('research_annotation as a')
            ->leftJoin('slug as s', 'a.object_id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('a.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('research_collection as rc', 'a.collection_id', '=', 'rc.id')
            ->leftJoin('digital_object as master_do', function ($j) {
                $j->on('a.object_id', '=', 'master_do.object_id')
                  ->where('master_do.usage_id', '=', 140); // master
            })
            ->leftJoin('digital_object as thumb', function ($j) {
                $j->on('master_do.id', '=', 'thumb.parent_id')
                  ->where('thumb.usage_id', '=', 141); // reference image
            })
            ->where('a.researcher_id', $researcherId)
            ->select('a.*', 's.slug as object_slug', 'i18n.title as object_title',
                'rc.name as collection_name',
                DB::raw("CONCAT(TRIM(TRAILING '/' FROM thumb.path), '/', thumb.name) as thumbnail_path"))
            ->orderBy('a.created_at', 'desc')
            ->get()->toArray();
    }

    public function searchAnnotations(int $researcherId, string $query): array
    {
        return DB::table('research_annotation as a')
            ->leftJoin('slug as s', 'a.object_id', '=', 's.object_id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('a.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('research_collection as rc', 'a.collection_id', '=', 'rc.id')
            ->leftJoin('digital_object as master_do', function ($j) {
                $j->on('a.object_id', '=', 'master_do.object_id')
                  ->where('master_do.usage_id', '=', 140); // master
            })
            ->leftJoin('digital_object as thumb', function ($j) {
                $j->on('master_do.id', '=', 'thumb.parent_id')
                  ->where('thumb.usage_id', '=', 141); // reference image
            })
            ->where('a.researcher_id', $researcherId)
            ->where(function ($q) use ($query) {
                $q->where('a.title', 'LIKE', '%' . $query . '%')
                  ->orWhere('a.content', 'LIKE', '%' . $query . '%')
                  ->orWhere('a.tags', 'LIKE', '%' . $query . '%');
            })
            ->select('a.*', 's.slug as object_slug', 'i18n.title as object_title',
                'rc.name as collection_name',
                DB::raw("CONCAT(TRIM(TRAILING '/' FROM thumb.path), '/', thumb.name) as thumbnail_path"))
            ->orderBy('a.created_at', 'desc')
            ->get()->toArray();
    }

    public function deleteAnnotation(int $id, int $researcherId): bool
    {
        return DB::table('research_annotation')
            ->where('id', $id)
            ->where('researcher_id', $researcherId)
            ->delete() > 0;
    }

    // =========================================================================
    // CITATIONS
    // =========================================================================

    public function generateCitation(int $objectId, string $style): array
    {
        $obj = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('repository as rep', 'io.repository_id', '=', 'rep.id')
            ->leftJoin('actor_i18n as repo_name', function ($join) {
                $join->on('rep.id', '=', 'repo_name.id')->where('repo_name.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('io.id', $objectId)
            ->select('io.*', 'i18n.title', 'i18n.scope_and_content',
                'repo_name.authorized_form_of_name as repository_name', 's.slug')
            ->first();

        if (!$obj) return ['error' => 'Object not found'];

        $title = $obj->title ?? 'Untitled';
        $repo = $obj->repository_name ?? '';
        $identifier = $obj->identifier ?? '';
        $accessDate = date('j F Y');
        $url = config('app.url') . '/' . ($obj->slug ?? $objectId);

        return match ($style) {
            'chicago' => ['citation' => "$title. $identifier. $repo. Accessed $accessDate. $url.", 'style' => 'Chicago'],
            'mla' => ['citation' => "\"$title.\" $repo, $identifier. Web. $accessDate. <$url>.", 'style' => 'MLA'],
            'turabian' => ['citation' => "$title. $identifier. $repo. $url.", 'style' => 'Turabian'],
            'apa' => ['citation' => "$repo. ($accessDate). $title [$identifier]. Retrieved from $url", 'style' => 'APA'],
            'harvard' => ['citation' => "$repo ($accessDate) $title [$identifier]. Available at: $url (Accessed: $accessDate).", 'style' => 'Harvard'],
            'unisa' => ['citation' => "$repo. $title. $identifier. [Online]. Available: $url [$accessDate].", 'style' => 'UNISA'],
            default => ['citation' => "$title. $identifier. $repo. $url.", 'style' => $style],
        };
    }

    public function logCitation(?int $researcherId, int $objectId, string $style, string $citation): void
    {
        try {
            DB::table('research_citation_log')->insert([
                'researcher_id' => $researcherId,
                'object_id' => $objectId,
                'citation_style' => $style,
                'citation_text' => $citation,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silent - citation logging is non-critical
        }
    }

    // =========================================================================
    // RESEARCHER TYPES
    // =========================================================================

    public function getResearcherTypes(): array
    {
        return DB::table('research_researcher_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()->toArray();
    }

    public function getResearcherType(int $id): ?object
    {
        return DB::table('research_researcher_type')->where('id', $id)->first();
    }

    public function createResearcherType(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        return DB::table('research_researcher_type')->insertGetId($data);
    }

    public function updateResearcherType(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return DB::table('research_researcher_type')->where('id', $id)->update($data) > 0;
    }

    // =========================================================================
    // API KEYS
    // =========================================================================

    public function getApiKeys(int $researcherId): array
    {
        $researcher = DB::table('research_researcher')->where('id', $researcherId)->first();
        if (!$researcher) return [];

        return DB::table('ahg_api_key')
            ->where('user_id', $researcher->user_id)
            ->orderBy('created_at', 'desc')
            ->get()->toArray();
    }

    public function generateApiKey(int $researcherId, string $name, array $permissions = [], ?string $expiresAt = null): array
    {
        // Get user_id from researcher
        $researcher = DB::table('research_researcher')->where('id', $researcherId)->first();
        if (!$researcher) {
            return ['key' => null, 'error' => 'Researcher not found'];
        }

        $rawKey = 'rk_' . bin2hex(random_bytes(32));
        $hashedKey = hash('sha256', $rawKey);
        $prefix = substr($rawKey, 0, 8);

        // Map research permissions to API scopes
        $scopes = [];
        if (in_array('read', $permissions)) $scopes = array_merge($scopes, ['read', 'search']);
        if (in_array('write', $permissions)) $scopes = array_merge($scopes, ['write', 'create', 'update']);
        if (in_array('search', $permissions)) $scopes[] = 'search';
        $scopes = array_unique($scopes);

        DB::table('ahg_api_key')->insert([
            'user_id' => $researcher->user_id,
            'name' => $name,
            'api_key' => $hashedKey,
            'api_key_prefix' => $prefix,
            'scopes' => json_encode($scopes),
            'rate_limit' => 1000,
            'expires_at' => $expiresAt,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['key' => $rawKey];
    }

    public function revokeApiKey(int $keyId, int $researcherId): bool
    {
        $researcher = DB::table('research_researcher')->where('id', $researcherId)->first();
        if (!$researcher) return false;

        return DB::table('ahg_api_key')
            ->where('id', $keyId)
            ->where('user_id', $researcher->user_id)
            ->update(['is_active' => 0, 'updated_at' => now()]) > 0;
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public function getDashboardStats(): array
    {
        return [
            'total_researchers' => DB::table('research_researcher')->where('status', 'approved')->count(),
            'today_bookings' => DB::table('research_booking')
                ->where('booking_date', date('Y-m-d'))
                ->whereIn('status', ['pending', 'confirmed'])->count(),
            'week_bookings' => DB::table('research_booking')
                ->whereBetween('booking_date', [date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))])
                ->whereIn('status', ['pending', 'confirmed'])->count(),
            'pending_requests' => DB::table('research_researcher')->where('status', 'pending')->count(),
        ];
    }

    public function getEnhancedDashboardData(int $researcherId): array
    {
        $data = [];

        $data['unread_notifications'] = DB::table('research_notification')
            ->where('researcher_id', $researcherId)
            ->where('is_read', 0)
            ->count();

        $data['recent_activity'] = DB::table('research_activity_log')
            ->where('researcher_id', $researcherId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()->toArray();

        $data['upcoming_bookings'] = DB::table('research_booking as b')
            ->join('research_reading_room as rm', 'b.reading_room_id', '=', 'rm.id')
            ->where('b.researcher_id', $researcherId)
            ->where('b.booking_date', '>=', date('Y-m-d'))
            ->whereIn('b.status', ['pending', 'confirmed'])
            ->select('b.*', 'rm.name as room_name')
            ->orderBy('b.booking_date')
            ->limit(5)
            ->get()->toArray();

        $data['recent_collections'] = DB::table('research_collection')
            ->where('researcher_id', $researcherId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()->toArray();

        $data['recent_journal_entries'] = DB::table('research_journal_entry')
            ->where('researcher_id', $researcherId)
            ->orderBy('entry_date', 'desc')
            ->limit(5)
            ->get()->toArray();

        $data['recent_notes'] = DB::table('research_annotation')
            ->where('researcher_id', $researcherId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()->toArray();

        $data['search_alerts'] = DB::table('research_saved_search')
            ->where('researcher_id', $researcherId)
            ->where('alert_enabled', 1)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()->toArray();

        $data['pending_invitations'] = DB::table('research_project_collaborator as pc')
            ->join('research_project as p', 'p.id', '=', 'pc.project_id')
            ->where('pc.researcher_id', $researcherId)
            ->where('pc.status', 'invited')
            ->select('pc.*', 'p.title as project_title')
            ->orderBy('pc.invited_at', 'desc')
            ->limit(5)
            ->get()->toArray();

        return $data;
    }

    // =========================================================================
    // HTML SANITIZATION
    // =========================================================================

    public function sanitizeHtml(string $html): string
    {
        // Strip dangerous tags but allow basic formatting
        $allowed = '<p><br><strong><b><em><i><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><a><blockquote><code><pre><hr><table><thead><tbody><tr><th><td><img><span><div><sub><sup>';
        $html = strip_tags($html, $allowed);

        // Remove javascript: URLs and on* event handlers
        $html = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);

        return $html;
    }

    // =========================================================================
    // AUDIT LOGGING
    // =========================================================================

    protected function logAudit(string $action, string $objectType, int $objectId, array $oldValues, array $newValues, ?string $description): void
    {
        try {
            DB::table('research_activity_log')->insert([
                'action' => $action,
                'object_type' => $objectType,
                'object_id' => $objectId,
                'researcher_id' => null,
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($newValues),
                'description' => $description,
                'ip_address' => request()->ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Silent - audit logging should not break the application
        }
    }
}
