<?php

/**
 * AuthorityRecordService - record type, external identity and local overrides
 * for authority records (heratio#1475 item 3).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorityRecordService
{
    public const DEFAULT_TYPE = 'subject';

    /** Fields an external sync may write unless staff have overridden them. */
    public const SYNCED_FIELDS = ['heading', 'uri'];

    /** Heading types that imply a record type; anything else is a subject. */
    public const HEADING_TYPE_MAP = [
        'personal' => 'name', 'corporate' => 'name', 'meeting' => 'name', 'names' => 'name',
        'person' => 'name', 'family' => 'name', 'corporate_body' => 'name',
        'uniform' => 'title', 'uniform_title' => 'title', 'title' => 'title',
        'genre' => 'genre', 'form' => 'genre',
    ];

    public static function typeForHeading(?string $headingType): string
    {
        return self::HEADING_TYPE_MAP[(string) $headingType] ?? self::DEFAULT_TYPE;
    }

    /** The stored type, or the one implied by the heading type on an untyped row. */
    public function typeFor(object $authority, ?object $typing): string
    {
        return $typing->record_type ?? self::typeForHeading($authority->subject_type ?? $authority->heading_type ?? null);
    }

    /** A valid LCSH identifier from the core lcsh_id / lcsh_uri columns, if any. */
    public static function lcshIdFrom(object $authority): ?string
    {
        $id = trim((string) ($authority->lcsh_id ?? ''));
        if (LcshVocabularyClient::validId($id)) {
            return $id;
        }

        return preg_match('#^https?://id\.loc\.gov/authorities/subjects/(sh[0-9]{6,12})/?$#', trim((string) ($authority->lcsh_uri ?? '')), $m)
            ? $m[1] : null;
    }

    public function available(): bool
    {
        return Schema::hasTable('library_authority_record') && Schema::hasTable('library_authority_relation');
    }

    public function get(int $authorityId): ?object
    {
        return DB::table('library_authority_record')->where('authority_id', $authorityId)->first();
    }

    public function setType(int $authorityId, string $recordType): void
    {
        DB::table('library_authority_record')->updateOrInsert(
            ['authority_id' => $authorityId],
            ['record_type' => $recordType, 'updated_at' => now()]
        );
    }

    /** @return string[] */
    public function overriddenFields(?object $record): array
    {
        $fields = json_decode((string) ($record->overridden_fields ?? ''), true);

        return is_array($fields) ? array_values($fields) : [];
    }

    public function linkExternal(int $authorityId, string $scheme, string $externalId): void
    {
        if (! $this->get($authorityId)) {
            $authority = DB::table('library_subject_authority')->where('id', $authorityId)->first();
            DB::table('library_authority_record')->insertOrIgnore([
                'authority_id' => $authorityId,
                'record_type' => self::typeForHeading($authority->subject_type ?? $authority->heading_type ?? null),
                'created_at' => now(),
            ]);
        }
        DB::table('library_authority_record')->where('authority_id', $authorityId)
            ->update(['scheme' => $scheme, 'external_id' => $externalId, 'updated_at' => now()]);
    }

    public function externalIdTaken(string $scheme, string $externalId, int $exceptAuthorityId): bool
    {
        return DB::table('library_authority_record')
            ->where('scheme', $scheme)->where('external_id', $externalId)
            ->where('authority_id', '!=', $exceptAuthorityId)
            ->exists();
    }

    /** A staff edit to a synced field on a linked record becomes a local override. */
    public function recordLocalEdit(int $authorityId, object $before, array $after): void
    {
        $record = $this->get($authorityId);
        if (! $record || ! $record->external_id) {
            return;
        }

        $fields = $this->overriddenFields($record);
        foreach (self::SYNCED_FIELDS as $field) {
            if (array_key_exists($field, $after) && (string) $after[$field] !== (string) ($before->{$field} ?? '')) {
                $fields[] = $field;
            }
        }

        $this->storeOverrides($authorityId, $fields);
    }

    public function clearOverrides(int $authorityId): void
    {
        $this->storeOverrides($authorityId, []);
    }

    private function storeOverrides(int $authorityId, array $fields): void
    {
        $fields = array_values(array_unique($fields));
        DB::table('library_authority_record')->where('authority_id', $authorityId)->update([
            'overridden_fields' => $fields ? json_encode($fields) : null,
            'updated_at' => now(),
        ]);
    }
}
