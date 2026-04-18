<?php

/**
 * ProvenanceService - Service for Heratio
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



namespace AhgProvenance\Services;

use Illuminate\Support\Facades\DB;

class ProvenanceService
{
    /**
     * Get provenance records for an information object by slug.
     * Loads record with i18n, events with agent names, and documents.
     */
    public function getBySlug(string $slug): array
    {
        $culture = app()->getLocale() ?: 'en';

        $io = DB::table('information_object')
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.slug', '!=', '');
            })
            ->where('slug.slug', $slug)
            ->select('information_object.*', 'slug.slug')
            ->first();

        if (!$io) {
            return ['resource' => null, 'provenance' => null, 'documents' => collect()];
        }

        $title = DB::table('information_object_i18n')
            ->where('id', $io->id)
            ->where('culture', $culture)
            ->value('title');

        $io->title = $title;

        // Load provenance_record with i18n and agent name
        $record = DB::table('provenance_record as pr')
            ->leftJoin('provenance_record_i18n as pri', function ($join) use ($culture) {
                $join->on('pr.id', '=', 'pri.id')
                     ->where('pri.culture', '=', $culture);
            })
            ->leftJoin('provenance_agent as pa', 'pr.provenance_agent_id', '=', 'pa.id')
            ->where('pr.information_object_id', $io->id)
            ->select([
                'pr.*',
                'pri.provenance_summary as summary_i18n',
                'pri.acquisition_notes',
                'pri.gap_description as gap_description_i18n',
                'pri.research_notes as research_notes_i18n',
                'pri.nazi_era_notes as nazi_era_notes_i18n',
                'pri.cultural_property_notes as cultural_property_notes_i18n',
                'pa.name as current_agent_name',
                'pa.agent_type as current_agent_type',
            ])
            ->first();

        $events = collect();
        $documents = collect();

        if ($record) {
            // Load events with agent names
            $events = DB::table('provenance_event as pe')
                ->leftJoin('provenance_event_i18n as pei', function ($join) use ($culture) {
                    $join->on('pe.id', '=', 'pei.id')
                         ->where('pei.culture', '=', $culture);
                })
                ->leftJoin('provenance_agent as from_agent', 'pe.from_agent_id', '=', 'from_agent.id')
                ->leftJoin('provenance_agent as to_agent', 'pe.to_agent_id', '=', 'to_agent.id')
                ->where('pe.provenance_record_id', $record->id)
                ->select([
                    'pe.*',
                    'pei.event_description',
                    'pei.notes as notes_i18n',
                    'from_agent.name as from_agent_name',
                    'from_agent.agent_type as from_agent_type',
                    'to_agent.name as to_agent_name',
                    'to_agent.agent_type as to_agent_type',
                ])
                ->orderBy('pe.event_date')
                ->orderBy('pe.sequence_number')
                ->get();

            // Load documents
            $documents = DB::table('provenance_document')
                ->where('provenance_record_id', $record->id)
                ->orderBy('document_date')
                ->get();
        }

        return [
            'resource' => $io,
            'provenance' => [
                'record' => $record,
                'events' => $events,
            ],
            'documents' => $documents,
        ];
    }

    /**
     * Get provenance records as timeline data.
     */
    public function getTimeline(string $slug): array
    {
        $data = $this->getBySlug($slug);
        if (!$data['resource']) {
            return $data;
        }

        $events = $data['provenance']['events'] ?? collect();
        $data['timeline'] = $events->map(function ($event) {
            return [
                'date' => $event->event_date ?? '',
                'title' => $event->event_type ?? '',
                'description' => $event->notes ?? '',
                'agent' => '',
            ];
        });

        return $data;
    }

    /**
     * List all information objects that have provenance data (browse).
     */
    public function browse(int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('provenance_record as pr')
            ->join('information_object', 'pr.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                     ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id')
                     ->where('slug.slug', '!=', '');
            })
            ->leftJoin('provenance_event as pe', 'pe.provenance_record_id', '=', 'pr.id')
            ->select(
                'information_object.id',
                'information_object_i18n.title',
                'slug.slug',
                DB::raw('COUNT(pe.id) as event_count'),
                DB::raw('MIN(pe.event_date) as earliest_event'),
                DB::raw('MAX(pe.event_date) as latest_event')
            )
            ->groupBy('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->orderBy('information_object_i18n.title')
            ->paginate($perPage);
    }

    /**
     * Get event types for dropdowns (grouped).
     */
    public function getEventTypes(): array
    {
        return [
            'Ownership Changes' => [
                'sale' => 'Sale',
                'purchase' => 'Purchase',
                'auction' => 'Auction Sale',
                'gift' => 'Gift',
                'donation' => 'Donation',
                'bequest' => 'Bequest',
                'inheritance' => 'Inheritance',
                'descent' => 'By Descent',
                'transfer' => 'Transfer',
                'exchange' => 'Exchange',
            ],
            'Loans & Deposits' => [
                'loan_out' => 'Loan Out',
                'loan_return' => 'Loan Return',
                'deposit' => 'Deposit',
                'withdrawal' => 'Withdrawal',
            ],
            'Creation & Discovery' => [
                'creation' => 'Creation',
                'commission' => 'Commission',
                'discovery' => 'Discovery',
                'excavation' => 'Excavation',
            ],
            'Loss & Recovery' => [
                'theft' => 'Theft',
                'recovery' => 'Recovery',
                'confiscation' => 'Confiscation',
                'restitution' => 'Restitution',
                'repatriation' => 'Repatriation',
            ],
            'Movement' => [
                'import' => 'Import',
                'export' => 'Export',
            ],
            'Documentation' => [
                'authentication' => 'Authentication',
                'appraisal' => 'Appraisal',
                'conservation' => 'Conservation',
                'restoration' => 'Restoration',
            ],
            'Institutional' => [
                'accessioning' => 'Accessioning',
                'deaccessioning' => 'Deaccessioning',
            ],
            'Other' => [
                'unknown' => 'Unknown',
                'other' => 'Other',
            ],
        ];
    }

    /**
     * Get acquisition types for dropdowns.
     */
    public function getAcquisitionTypes(): array
    {
        return [
            '' => '-- Select --',
            'donation' => 'Donation',
            'purchase' => 'Purchase',
            'bequest' => 'Bequest',
            'transfer' => 'Transfer',
            'loan' => 'Loan',
            'deposit' => 'Deposit',
            'exchange' => 'Exchange',
            'field_collection' => 'Field Collection',
            'unknown' => 'Unknown',
        ];
    }

    /**
     * Get certainty levels for dropdowns.
     */
    public function getCertaintyLevels(): array
    {
        return [
            'certain' => 'Certain - Documented evidence',
            'probable' => 'Probable - Strong circumstantial evidence',
            'possible' => 'Possible - Some supporting evidence',
            'uncertain' => 'Uncertain - Limited evidence',
            'unknown' => 'Unknown - No evidence',
        ];
    }

    /**
     * Update provenance record fields, events, and documents.
     */
    public function update(string $slug, array $data): bool
    {
        $culture = app()->getLocale() ?: 'en';
        $ioId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$ioId) {
            return false;
        }

        $record = DB::table('provenance_record')->where('information_object_id', $ioId)->first();

        // All provenance_record columns (non-i18n)
        $recordFields = [
            'current_status', 'custody_type', 'acquisition_type', 'acquisition_date',
            'acquisition_date_text', 'acquisition_price', 'acquisition_currency',
            'certainty_level', 'has_gaps', 'research_status',
            'nazi_era_provenance_checked', 'nazi_era_provenance_clear',
            'cultural_property_status',
            'is_complete', 'is_public',
        ];

        $values = [];
        foreach ($recordFields as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = $data[$field];
            }
        }

        // Handle checkboxes (default to 0 when unchecked / not present)
        $values['has_gaps'] = !empty($data['has_gaps']) ? 1 : 0;
        $values['nazi_era_provenance_checked'] = !empty($data['nazi_era_provenance_checked']) ? 1 : 0;
        $values['nazi_era_provenance_clear'] = ($data['nazi_era_provenance_clear'] ?? '') !== '' ? $data['nazi_era_provenance_clear'] : null;
        $values['is_complete'] = !empty($data['is_complete']) ? 1 : 0;
        $values['is_public'] = !empty($data['is_public']) ? 1 : 0;

        // Handle empty date/price
        $values['acquisition_date'] = !empty($data['acquisition_date']) ? $data['acquisition_date'] : null;
        $values['acquisition_price'] = !empty($data['acquisition_price']) ? $data['acquisition_price'] : null;

        // Handle current agent
        $agentName = $data['current_agent_name'] ?? null;
        if ($agentName) {
            $agentType = $data['current_agent_type'] ?? 'person';
            $values['provenance_agent_id'] = $this->findOrCreateAgent($agentName, $agentType);
        } else {
            $values['provenance_agent_id'] = null;
        }

        $values['updated_at'] = now();

        // i18n fields
        $i18nFields = [
            'provenance_summary',
            'acquisition_notes',
            'gap_description',
            'research_notes',
            'nazi_era_notes',
            'cultural_property_notes',
        ];
        $i18nValues = [];
        foreach ($i18nFields as $field) {
            $i18nValues[$field] = $data[$field] ?? null;
        }

        $recordId = null;

        if ($record) {
            $recordId = $record->id;
            DB::table('provenance_record')->where('id', $recordId)->update($values);

            // Upsert i18n
            $existingI18n = DB::table('provenance_record_i18n')
                ->where('id', $recordId)
                ->where('culture', $culture)
                ->first();

            if ($existingI18n) {
                DB::table('provenance_record_i18n')
                    ->where('id', $recordId)
                    ->where('culture', $culture)
                    ->update($i18nValues);
            } else {
                DB::table('provenance_record_i18n')->insert(array_merge(
                    ['id' => $recordId, 'culture' => $culture],
                    $i18nValues
                ));
            }
        } else {
            $values['information_object_id'] = $ioId;
            $values['created_by'] = auth()->id();
            $values['created_at'] = now();
            $recordId = DB::table('provenance_record')->insertGetId($values);

            DB::table('provenance_record_i18n')->insert(array_merge(
                ['id' => $recordId, 'culture' => $culture],
                $i18nValues
            ));
        }

        // Process events: delete all existing, re-insert from form arrays
        $this->processEvents($recordId, $data, $culture);

        // Process new documents from form
        $this->processDocuments($recordId, $data);

        return true;
    }

    /**
     * Process events from form array data.
     * Deletes all existing events and re-creates from the submitted arrays.
     */
    protected function processEvents(int $recordId, array $data, string $culture): void
    {
        // Delete existing events and their i18n
        $existingEventIds = DB::table('provenance_event')
            ->where('provenance_record_id', $recordId)
            ->pluck('id');

        if ($existingEventIds->isNotEmpty()) {
            DB::table('provenance_event_i18n')->whereIn('id', $existingEventIds)->delete();
            DB::table('provenance_event')->where('provenance_record_id', $recordId)->delete();
        }

        $eventTypes = $data['event_type'] ?? [];
        if (!is_array($eventTypes)) {
            return;
        }

        $eventDates = $data['event_date'] ?? [];
        $eventDateTexts = $data['event_date_text'] ?? [];
        $fromAgents = $data['from_agent'] ?? [];
        $toAgents = $data['to_agent'] ?? [];
        $eventLocations = $data['event_location'] ?? [];
        $eventCertainties = $data['event_certainty'] ?? [];
        $eventNotes = $data['event_notes'] ?? [];
        $userId = auth()->id();

        foreach ($eventTypes as $i => $type) {
            if (empty($type)) {
                continue;
            }

            $fromAgentId = null;
            $toAgentId = null;

            if (!empty($fromAgents[$i])) {
                $fromAgentId = $this->findOrCreateAgent($fromAgents[$i]);
            }
            if (!empty($toAgents[$i])) {
                $toAgentId = $this->findOrCreateAgent($toAgents[$i]);
            }

            $eventData = [
                'provenance_record_id' => $recordId,
                'event_type' => $type,
                'event_date' => !empty($eventDates[$i]) ? $eventDates[$i] : null,
                'event_date_text' => $eventDateTexts[$i] ?? null,
                'event_location' => $eventLocations[$i] ?? null,
                'certainty' => $eventCertainties[$i] ?? 'uncertain',
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'sequence_number' => $i + 1,
                'is_public' => 1,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $eventId = DB::table('provenance_event')->insertGetId($eventData);

            // Save event i18n (notes)
            $noteText = $eventNotes[$i] ?? null;
            if ($noteText) {
                DB::table('provenance_event_i18n')->insert([
                    'id' => $eventId,
                    'culture' => $culture,
                    'notes' => $noteText,
                ]);
            }
        }
    }

    /**
     * Process new document uploads from form arrays.
     */
    protected function processDocuments(int $recordId, array $data): void
    {
        $docTypes = $data['doc_type'] ?? [];
        if (!is_array($docTypes)) {
            return;
        }

        $docTitles = $data['doc_title'] ?? [];
        $docDates = $data['doc_date'] ?? [];
        $docUrls = $data['doc_url'] ?? [];
        $docDescriptions = $data['doc_description'] ?? [];
        $userId = auth()->id();

        $uploadDir = config('heratio.uploads_path') . '/provenance';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        foreach ($docTypes as $i => $type) {
            if (empty($type)) {
                continue;
            }

            $filePath = null;
            $originalFilename = null;
            $mimeType = null;
            $fileSize = null;
            $filename = null;

            // Handle file upload via Laravel request
            if (request()->hasFile("doc_file.$i")) {
                $file = request()->file("doc_file.$i");
                $originalFilename = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();

                $ext = $file->getClientOriginalExtension();
                $filename = uniqid('prov_') . '.' . $ext;
                $filePath = '/uploads/provenance/' . $filename;

                $file->move($uploadDir, $filename);
            }

            $docData = [
                'provenance_record_id' => $recordId,
                'document_type' => $type,
                'title' => $docTitles[$i] ?? null,
                'description' => $docDescriptions[$i] ?? null,
                'document_date' => !empty($docDates[$i]) ? $docDates[$i] : null,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'external_url' => !empty($docUrls[$i]) ? $docUrls[$i] : null,
                'is_public' => 0,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            DB::table('provenance_document')->insert($docData);
        }
    }

    /**
     * Find or create a provenance agent by name.
     */
    public function findOrCreateAgent(string $name, string $type = 'person', ?int $actorId = null): int
    {
        $existing = DB::table('provenance_agent')
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('provenance_agent')->insertGetId([
            'name' => $name,
            'agent_type' => $type,
            'actor_id' => $actorId,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Add a single provenance event.
     */
    public function addEvent(string $slug, array $data): void
    {
        $ioId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$ioId) {
            return;
        }

        $record = DB::table('provenance_record')->where('information_object_id', $ioId)->first();
        if (!$record) {
            return;
        }

        $maxSeq = DB::table('provenance_event')
            ->where('provenance_record_id', $record->id)
            ->max('sequence_number') ?? 0;

        DB::table('provenance_event')->insert([
            'provenance_record_id' => $record->id,
            'event_type' => $data['event_type'] ?? 'unknown',
            'event_date' => $data['date'] ?? null,
            'certainty' => 'uncertain',
            'sequence_number' => $maxSeq + 1,
            'is_public' => 1,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete a single provenance event.
     */
    public function deleteEvent(string $slug, int $eventId): void
    {
        DB::table('provenance_event_i18n')->where('id', $eventId)->delete();
        DB::table('provenance_event')->where('id', $eventId)->delete();
    }

    /**
     * Delete a provenance document.
     */
    public function deleteDocument(int $id): void
    {
        $doc = DB::table('provenance_document')->where('id', $id)->first();
        if ($doc && $doc->file_path) {
            $fullPath = config('heratio.uploads_path') . '/provenance/' . $doc->filename;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
        DB::table('provenance_document')->where('id', $id)->delete();
    }
}
