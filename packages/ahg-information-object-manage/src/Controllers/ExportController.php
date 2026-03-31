<?php

/**
 * ExportController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    /**
     * Export an information object as EAD 2002 XML.
     */
    public function ead(string $slug)
    {
        $culture = app()->getLocale();
        $io = $this->getIO($slug, $culture);
        if (!$io) {
            abort(404);
        }

        $repository = $this->getRepository($io, $culture);
        $events = $this->getEvents($io, $culture);
        $creators = $this->getCreators($io, $culture);
        $subjects = $this->getAccessPoints($io, 35, $culture);
        $places = $this->getAccessPoints($io, 42, $culture);
        $genres = $this->getAccessPoints($io, 78, $culture);
        $notes = $this->getNotes($io, $culture);
        $levelName = $this->getLevelName($io, $culture);
        $children = $this->getDescendants($io, $culture);

        $xml = $this->buildEadXml($io, $repository, $events, $creators, $subjects, $places, $genres, $notes, $levelName, $children, $culture);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $io->slug . '_ead.xml"');
    }

    /**
     * Export an information object as Dublin Core 1.1 XML.
     */
    public function dc(string $slug)
    {
        $culture = app()->getLocale();
        $io = $this->getIO($slug, $culture);
        if (!$io) {
            abort(404);
        }

        $repository = $this->getRepository($io, $culture);
        $events = $this->getEvents($io, $culture);
        $creators = $this->getCreators($io, $culture);
        $subjects = $this->getAccessPoints($io, 35, $culture);
        $places = $this->getAccessPoints($io, 42, $culture);
        $genres = $this->getAccessPoints($io, 78, $culture);
        $levelName = $this->getLevelName($io, $culture);
        $languages = $this->getLanguages($io, $culture);

        $xml = $this->buildDcXml($io, $repository, $events, $creators, $subjects, $places, $genres, $levelName, $languages, $culture);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $io->slug . '_dc.xml"');
    }

    /**
     * Export an information object as MODS 3.5 XML.
     * Migrated from AtoM sfModsPlugin export action.
     */
    public function mods(string $slug)
    {
        $culture = app()->getLocale();
        $io = $this->getIO($slug, $culture);
        if (!$io) {
            abort(404);
        }

        $repository = $this->getRepository($io, $culture);
        $events = $this->getEvents($io, $culture);
        $creators = $this->getCreators($io, $culture);
        $subjects = $this->getAccessPoints($io, 35, $culture);
        $places = $this->getAccessPoints($io, 42, $culture);
        $genres = $this->getAccessPoints($io, 78, $culture);
        $levelName = $this->getLevelName($io, $culture);
        $languages = $this->getLanguages($io, $culture);

        $xml = $this->buildModsXml($io, $repository, $events, $creators, $subjects, $places, $genres, $levelName, $languages, $culture);

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $io->slug . '_mods.xml"');
    }

    /**
     * Export information objects as CSV.
     * Migrated from AtoM InformationObjectExportCsvAction.
     */
    public function csv(string $slug)
    {
        $culture = app()->getLocale();
        $io = $this->getIO($slug, $culture);
        if (!$io) {
            abort(404);
        }

        // Get this IO and all descendants
        $rows = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where(function ($q) use ($io) {
                $q->where('io.id', $io->id)
                  ->orWhere(function ($q2) use ($io) {
                      $q2->where('io.lft', '>', $io->lft)->where('io.rgt', '<', $io->rgt);
                  });
            })
            ->orderBy('io.lft')
            ->select([
                'io.id', 'io.identifier', 'io.level_of_description_id',
                'io.repository_id', 'io.parent_id',
                'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium',
                'i18n.archival_history', 'i18n.acquisition', 'i18n.appraisal',
                'i18n.accruals', 'i18n.arrangement', 'i18n.access_conditions',
                'i18n.reproduction_conditions', 'i18n.physical_characteristics',
                'i18n.finding_aids', 'i18n.location_of_originals', 'i18n.location_of_copies',
                'i18n.related_units_of_description', 'i18n.rules', 'i18n.sources',
                's.slug',
            ])
            ->get();

        $headers = [
            'identifier', 'title', 'slug', 'level_of_description_id',
            'scope_and_content', 'extent_and_medium', 'archival_history',
            'acquisition', 'appraisal', 'accruals', 'arrangement',
            'access_conditions', 'reproduction_conditions', 'physical_characteristics',
            'finding_aids', 'location_of_originals', 'location_of_copies',
            'related_units_of_description', 'rules', 'sources',
        ];

        $callback = function () use ($rows, $headers) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row->$h ?? '';
                }
                fputcsv($fp, $line);
            }
            fclose($fp);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $io->slug . '_export.csv"',
        ]);
    }

    private function buildModsXml($io, $repository, $events, $creators, $subjects, $places, $genres, $levelName, $languages, string $culture): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-5.xsd" version="3.5">' . "\n";

        // Title
        $xml .= "  <titleInfo>\n    <title>" . $this->e($io->title) . "</title>\n  </titleInfo>\n";

        // Creators
        foreach ($creators as $creator) {
            $type = match ((int) ($creator->entity_type_id ?? 0)) {
                132 => 'personal',
                130 => 'family',
                131 => 'corporate',
                default => 'personal',
            };
            $xml .= "  <name type=\"{$type}\">\n";
            $xml .= "    <namePart>" . $this->e($creator->name) . "</namePart>\n";
            $xml .= "    <role><roleTerm type=\"text\" authority=\"marcrelator\">creator</roleTerm></role>\n";
            $xml .= "  </name>\n";
        }

        // Type of resource
        if ($levelName) {
            $xml .= "  <typeOfResource>" . $this->e($levelName) . "</typeOfResource>\n";
        }

        // Origin info (dates)
        $hasOrigin = false;
        $originXml = "  <originInfo>\n";
        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if ($dateVal) {
                $hasOrigin = true;
                $originXml .= "    <dateCreated>" . $this->e($dateVal) . "</dateCreated>\n";
            }
        }
        $originXml .= "  </originInfo>\n";
        if ($hasOrigin) {
            $xml .= $originXml;
        }

        // Languages
        foreach ($languages as $lang) {
            $xml .= "  <language><languageTerm type=\"text\">" . $this->e($lang->name) . "</languageTerm></language>\n";
        }

        // Physical description
        if ($io->extent_and_medium) {
            $xml .= "  <physicalDescription>\n    <extent>" . $this->e($io->extent_and_medium) . "</extent>\n  </physicalDescription>\n";
        }

        // Abstract (scope and content)
        if ($io->scope_and_content) {
            $xml .= "  <abstract>" . $this->e($io->scope_and_content) . "</abstract>\n";
        }

        // Subjects
        foreach ($subjects as $s) {
            $xml .= "  <subject><topic>" . $this->e($s->name) . "</topic></subject>\n";
        }

        // Places
        foreach ($places as $p) {
            $xml .= "  <subject><geographic>" . $this->e($p->name) . "</geographic></subject>\n";
        }

        // Genres
        foreach ($genres as $g) {
            $xml .= "  <genre>" . $this->e($g->name) . "</genre>\n";
        }

        // Identifier
        if ($io->identifier) {
            $xml .= "  <identifier type=\"local\">" . $this->e($io->identifier) . "</identifier>\n";
        }

        // Location (repository)
        if ($repository) {
            $xml .= "  <location>\n    <physicalLocation>" . $this->e($repository->name) . "</physicalLocation>\n  </location>\n";
        }

        // Access conditions
        if ($io->access_conditions) {
            $xml .= "  <accessCondition type=\"restriction on access\">" . $this->e($io->access_conditions) . "</accessCondition>\n";
        }
        if ($io->reproduction_conditions) {
            $xml .= "  <accessCondition type=\"use and reproduction\">" . $this->e($io->reproduction_conditions) . "</accessCondition>\n";
        }

        // Record info
        $xml .= "  <recordInfo>\n";
        $xml .= "    <recordContentSource>" . $this->e(config('app.name', 'Heratio')) . "</recordContentSource>\n";
        $xml .= "    <recordCreationDate encoding=\"iso8601\">" . gmdate('Y-m-d') . "</recordCreationDate>\n";
        $xml .= "    <languageOfCataloging><languageTerm authority=\"iso639-2b\">" . $this->e($culture) . "</languageTerm></languageOfCataloging>\n";
        $xml .= "  </recordInfo>\n";

        $xml .= "</mods>\n";
        return $xml;
    }

    private function getIO(string $slug, string $culture)
    {
        return DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.description_status_id',
                'information_object.description_detail_id',
                'information_object.description_identifier',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.archival_history',
                'information_object_i18n.acquisition',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.appraisal',
                'information_object_i18n.accruals',
                'information_object_i18n.arrangement',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'information_object_i18n.finding_aids',
                'information_object_i18n.location_of_originals',
                'information_object_i18n.location_of_copies',
                'information_object_i18n.related_units_of_description',
                'information_object_i18n.rules',
                'information_object_i18n.sources',
                'information_object_i18n.revision_history',
                'information_object_i18n.institution_responsible_identifier',
                'information_object_i18n.edition',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    private function getRepository($io, string $culture)
    {
        if (!$io->repository_id) {
            return null;
        }
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('repository.id', $io->repository_id)
            ->where('actor_i18n.culture', $culture)
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->first();
    }

    private function getEvents($io, string $culture)
    {
        return DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display')
            ->get();
    }

    private function getCreators($io, string $culture)
    {
        return DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->join('actor', 'event.actor_id', '=', 'actor.id')
            ->where('event.object_id', $io->id)
            ->where('event.type_id', 111)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('event.actor_id')
            ->select('actor_i18n.authorized_form_of_name as name', 'actor.entity_type_id')
            ->distinct()
            ->get();
    }

    private function getAccessPoints($io, int $taxonomyId, string $culture)
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();
    }

    private function getNotes($io, string $culture)
    {
        return DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.type_id', 'note_i18n.content')
            ->get();
    }

    private function getLevelName($io, string $culture): ?string
    {
        if (!$io->level_of_description_id) {
            return null;
        }
        return DB::table('term_i18n')
            ->where('id', $io->level_of_description_id)
            ->where('culture', $culture)
            ->value('name');
    }

    private function getLanguages($io, string $culture)
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->join('term', 'object_term_relation.term_id', '=', 'term.id')
            ->where('object_term_relation.object_id', $io->id)
            ->where('term.taxonomy_id', 7)
            ->where('term_i18n.culture', $culture)
            ->select('term_i18n.name')
            ->get();
    }

    private function getDescendants($io, string $culture)
    {
        return DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->where('information_object.lft', '>', $io->lft)
            ->where('information_object.rgt', '<', $io->rgt)
            ->where('information_object_i18n.culture', $culture)
            ->orderBy('information_object.lft')
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object_i18n.title',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.arrangement',
            ])
            ->get();
    }

    private function e(string $value = null): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function mapLevelToEad(?string $level): string
    {
        $map = [
            'Fonds' => 'fonds', 'Sub-fonds' => 'subfonds', 'Collection' => 'collection',
            'Series' => 'series', 'Sub-series' => 'subseries', 'File' => 'file',
            'Item' => 'item', 'Part' => 'item',
        ];
        return $map[$level ?? ''] ?? 'otherlevel';
    }

    private function buildEadXml($io, $repository, $events, $creators, $subjects, $places, $genres, $notes, $levelName, $children, string $culture): string
    {
        $eadLevel = $this->mapLevelToEad($levelName);
        $date = gmdate('Y-m-d H:i e');
        $dateNormal = gmdate('Y-m-d');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<!DOCTYPE ead PUBLIC "+//ISBN 1-931666-00-8//DTD ead.dtd (Encoded Archival Description (EAD) Version 2002)//EN" "http://lcweb2.loc.gov/xmlcommon/dtds/ead2002/ead.dtd">' . "\n";
        $xml .= "<ead>\n";

        // Header
        $xml .= "<eadheader langencoding=\"iso639-2b\" countryencoding=\"iso3166-1\" dateencoding=\"iso8601\" repositoryencoding=\"iso15511\" scriptencoding=\"iso15924\" relatedencoding=\"DC\">\n";
        $xml .= "  <eadid>" . $this->e($io->identifier) . "</eadid>\n";
        $xml .= "  <filedesc>\n";
        $xml .= "    <titlestmt>\n";
        $xml .= "      <titleproper encodinganalog=\"6\">" . $this->e($io->title) . "</titleproper>\n";
        $xml .= "    </titlestmt>\n";
        if ($repository) {
            $xml .= "    <publicationstmt>\n";
            $xml .= "      <publisher encodinganalog=\"8\">" . $this->e($repository->name) . "</publisher>\n";
            $xml .= "      <date normal=\"{$dateNormal}\" encodinganalog=\"9\">{$dateNormal}</date>\n";
            $xml .= "    </publicationstmt>\n";
        }
        $xml .= "  </filedesc>\n";
        $xml .= "  <profiledesc>\n";
        $xml .= "    <creation>Generated by Heratio <date normal=\"{$dateNormal}\">{$date}</date></creation>\n";
        $xml .= "    <langusage><language langcode=\"{$culture}\">" . $this->e($culture) . "</language></langusage>\n";
        $xml .= "  </profiledesc>\n";
        $xml .= "</eadheader>\n";

        // Archdesc
        $xml .= "<archdesc level=\"{$eadLevel}\" relatedencoding=\"isad\">\n";
        $xml .= $this->buildDidElement($io, $events, $creators, $repository, $levelName, $culture);

        // Bioghist
        foreach ($creators as $creator) {
            $history = DB::table('actor_i18n')
                ->where('id', DB::table('actor_i18n')->where('authorized_form_of_name', $creator->name)->where('culture', $culture)->value('id'))
                ->where('culture', $culture)
                ->value('history');
            if ($history) {
                $xml .= "  <bioghist encodinganalog=\"3.2.2\"><p>" . $this->e($history) . "</p></bioghist>\n";
            }
        }

        // Scope and content
        if ($io->scope_and_content) {
            $xml .= "  <scopecontent encodinganalog=\"3.3.1\"><p>" . $this->e($io->scope_and_content) . "</p></scopecontent>\n";
        }
        if ($io->arrangement) {
            $xml .= "  <arrangement encodinganalog=\"3.3.4\"><p>" . $this->e($io->arrangement) . "</p></arrangement>\n";
        }
        if ($io->appraisal) {
            $xml .= "  <appraisal encodinganalog=\"3.3.2\"><p>" . $this->e($io->appraisal) . "</p></appraisal>\n";
        }
        if ($io->acquisition) {
            $xml .= "  <acqinfo encodinganalog=\"3.2.4\"><p>" . $this->e($io->acquisition) . "</p></acqinfo>\n";
        }
        if ($io->accruals) {
            $xml .= "  <accruals encodinganalog=\"3.3.3\"><p>" . $this->e($io->accruals) . "</p></accruals>\n";
        }
        if ($io->archival_history) {
            $xml .= "  <custodhist encodinganalog=\"3.2.3\"><p>" . $this->e($io->archival_history) . "</p></custodhist>\n";
        }
        if ($io->physical_characteristics) {
            $xml .= "  <phystech encodinganalog=\"3.4.4\"><p>" . $this->e($io->physical_characteristics) . "</p></phystech>\n";
        }
        if ($io->location_of_originals) {
            $xml .= "  <originalsloc encodinganalog=\"3.5.1\"><p>" . $this->e($io->location_of_originals) . "</p></originalsloc>\n";
        }
        if ($io->location_of_copies) {
            $xml .= "  <altformavail encodinganalog=\"3.5.2\"><p>" . $this->e($io->location_of_copies) . "</p></altformavail>\n";
        }
        if ($io->related_units_of_description) {
            $xml .= "  <relatedmaterial encodinganalog=\"3.5.3\"><p>" . $this->e($io->related_units_of_description) . "</p></relatedmaterial>\n";
        }
        if ($io->access_conditions) {
            $xml .= "  <accessrestrict encodinganalog=\"3.4.1\"><p>" . $this->e($io->access_conditions) . "</p></accessrestrict>\n";
        }
        if ($io->reproduction_conditions) {
            $xml .= "  <userestrict encodinganalog=\"3.4.2\"><p>" . $this->e($io->reproduction_conditions) . "</p></userestrict>\n";
        }
        if ($io->finding_aids) {
            $xml .= "  <otherfindaid encodinganalog=\"3.4.5\"><p>" . $this->e($io->finding_aids) . "</p></otherfindaid>\n";
        }

        // Publication notes
        foreach ($notes->where('type_id', 141) as $note) {
            $xml .= "  <bibliography><p>" . $this->e($note->content) . "</p></bibliography>\n";
        }

        // Revision history / archivist notes
        $archivistNotes = $notes->where('type_id', 142);
        if ($io->revision_history || $archivistNotes->isNotEmpty()) {
            $xml .= "  <processinfo>\n";
            if ($io->revision_history) {
                $xml .= "    <p><date>" . $this->e($io->revision_history) . "</date></p>\n";
            }
            foreach ($archivistNotes as $note) {
                $xml .= "    <p>" . $this->e($note->content) . "</p>\n";
            }
            $xml .= "  </processinfo>\n";
        }

        // Control access
        if ($subjects->isNotEmpty() || $places->isNotEmpty() || $genres->isNotEmpty()) {
            $xml .= "  <controlaccess>\n";
            foreach ($subjects as $s) {
                $xml .= "    <subject>" . $this->e($s->name) . "</subject>\n";
            }
            foreach ($places as $p) {
                $xml .= "    <geogname>" . $this->e($p->name) . "</geogname>\n";
            }
            foreach ($genres as $g) {
                $xml .= "    <genreform>" . $this->e($g->name) . "</genreform>\n";
            }
            $xml .= "  </controlaccess>\n";
        }

        // Children
        if ($children->isNotEmpty()) {
            $xml .= "  <dsc type=\"combined\">\n";
            $nestedRgt = [];
            foreach ($children as $child) {
                while (count($nestedRgt) > 0 && $child->rgt > $nestedRgt[count($nestedRgt) - 1]) {
                    array_pop($nestedRgt);
                    $xml .= "    </c>\n";
                }
                $childLevel = $child->level_of_description_id
                    ? $this->mapLevelToEad(DB::table('term_i18n')->where('id', $child->level_of_description_id)->where('culture', $culture)->value('name'))
                    : 'otherlevel';
                $xml .= "    <c level=\"{$childLevel}\">\n";
                $xml .= "      <did>\n";
                if ($child->identifier) {
                    $xml .= "        <unitid>" . $this->e($child->identifier) . "</unitid>\n";
                }
                $xml .= "        <unittitle>" . $this->e($child->title) . "</unittitle>\n";
                if ($child->extent_and_medium) {
                    $xml .= "        <physdesc><extent>" . $this->e($child->extent_and_medium) . "</extent></physdesc>\n";
                }
                $xml .= "      </did>\n";
                if ($child->scope_and_content) {
                    $xml .= "      <scopecontent><p>" . $this->e($child->scope_and_content) . "</p></scopecontent>\n";
                }
                if ($child->arrangement) {
                    $xml .= "      <arrangement><p>" . $this->e($child->arrangement) . "</p></arrangement>\n";
                }
                if ($child->rgt == $child->lft + 1) {
                    $xml .= "    </c>\n";
                } else {
                    $nestedRgt[] = $child->rgt;
                }
            }
            while (count($nestedRgt) > 0) {
                array_pop($nestedRgt);
                $xml .= "    </c>\n";
            }
            $xml .= "  </dsc>\n";
        }

        $xml .= "</archdesc>\n</ead>\n";
        return $xml;
    }

    private function buildDidElement($io, $events, $creators, $repository, $levelName, string $culture): string
    {
        $xml = "  <did>\n";
        if ($io->identifier) {
            $xml .= "    <unitid encodinganalog=\"3.1.1\">" . $this->e($io->identifier) . "</unitid>\n";
        }
        $xml .= "    <unittitle encodinganalog=\"3.1.2\">" . $this->e($io->title) . "</unittitle>\n";

        // Dates
        foreach ($events as $event) {
            $dateStr = $event->date_display ?? '';
            $normal = '';
            if ($event->start_date && $event->end_date) {
                $normal = $event->start_date . '/' . $event->end_date;
            } elseif ($event->start_date) {
                $normal = $event->start_date;
            }
            $xml .= "    <unitdate";
            if ($normal) {
                $xml .= " normal=\"" . $this->e($normal) . "\"";
            }
            $xml .= " encodinganalog=\"3.1.3\">" . $this->e($dateStr ?: $normal) . "</unitdate>\n";
        }

        // Creators
        foreach ($creators as $creator) {
            $tag = match ((int) ($creator->entity_type_id ?? 0)) {
                132 => 'persname',
                130 => 'famname',
                131 => 'corpname',
                default => 'name',
            };
            $xml .= "    <origination encodinganalog=\"3.2.1\"><{$tag}>" . $this->e($creator->name) . "</{$tag}></origination>\n";
        }

        if ($io->extent_and_medium) {
            $xml .= "    <physdesc encodinganalog=\"3.1.5\"><extent>" . $this->e($io->extent_and_medium) . "</extent></physdesc>\n";
        }

        if ($repository) {
            $xml .= "    <repository><corpname>" . $this->e($repository->name) . "</corpname></repository>\n";
        }

        $xml .= "  </did>\n";
        return $xml;
    }

    private function buildDcXml($io, $repository, $events, $creators, $subjects, $places, $genres, $levelName, $languages, string $culture): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">' . "\n";
        $xml .= "  <dc:title>" . $this->e($io->title) . "</dc:title>\n";

        foreach ($creators as $creator) {
            $xml .= "  <dc:creator>" . $this->e($creator->name) . "</dc:creator>\n";
        }

        foreach ($subjects as $s) {
            $xml .= "  <dc:subject>" . $this->e($s->name) . "</dc:subject>\n";
        }

        if ($io->scope_and_content) {
            $xml .= "  <dc:description>" . $this->e($io->scope_and_content) . "</dc:description>\n";
        }

        if ($repository) {
            $xml .= "  <dc:publisher>" . $this->e($repository->name) . "</dc:publisher>\n";
        }

        foreach ($events as $event) {
            $dateVal = $event->date_display ?: ($event->start_date ?? '');
            if ($dateVal) {
                $xml .= "  <dc:date>" . $this->e($dateVal) . "</dc:date>\n";
            }
        }

        if ($levelName) {
            $xml .= "  <dc:type>" . $this->e($levelName) . "</dc:type>\n";
        }

        if ($io->extent_and_medium) {
            $xml .= "  <dc:format>" . $this->e($io->extent_and_medium) . "</dc:format>\n";
        }

        if ($io->identifier) {
            $xml .= "  <dc:identifier>" . $this->e($io->identifier) . "</dc:identifier>\n";
        }

        $xml .= "  <dc:source>" . $this->e(url('/' . $io->slug)) . "</dc:source>\n";

        foreach ($languages as $lang) {
            $xml .= "  <dc:language>" . $this->e($lang->name) . "</dc:language>\n";
        }

        foreach ($places as $p) {
            $xml .= "  <dc:coverage>" . $this->e($p->name) . "</dc:coverage>\n";
        }

        if ($io->access_conditions) {
            $xml .= "  <dc:rights>" . $this->e($io->access_conditions) . "</dc:rights>\n";
        }

        $xml .= "</metadata>\n";
        return $xml;
    }
}
