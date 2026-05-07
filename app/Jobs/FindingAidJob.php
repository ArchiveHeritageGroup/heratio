<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finding Aid Job — generates EAD XML (and optionally PDF) for an information object.
 *
 * Migrated from arFindingAidJob. Uses the same EAD generation logic as ExportController.
 * Saves output to public/downloads/finding-aid-{id}.xml and optionally .pdf.
 */
class FindingAidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Job status IDs (term_i18n)
    const STATUS_IN_PROGRESS = 183;
    const STATUS_COMPLETED = 184;
    const STATUS_ERROR = 185;

    protected int $informationObjectId;
    protected int $jobRecordId = 0;

    public function __construct(int $informationObjectId)
    {
        $this->informationObjectId = $informationObjectId;
    }

    public function handle(): void
    {
        $this->createJobRecord();
        $this->log("Generating finding aid for information object ID {$this->informationObjectId}.");

        try {
            $culture = app()->getLocale();
            $io = $this->getIO($culture);

            if (!$io) {
                $this->logError("Information object ID {$this->informationObjectId} not found.");
                $this->markFailed();
                return;
            }

            if ($io->parent_id == 1 || !$io->parent_id) {
                // This is a top-level description — proceed
            }

            // Gather all data needed for EAD generation
            $repository = $this->getRepository($io, $culture);
            $events = $this->getEvents($io, $culture);
            $creators = $this->getCreators($io, $culture);
            $subjects = $this->getAccessPoints($io, 35, $culture);
            $places = $this->getAccessPoints($io, 42, $culture);
            $genres = $this->getAccessPoints($io, 78, $culture);
            $notes = $this->getNotes($io, $culture);
            $levelName = $this->getLevelName($io, $culture);
            $children = $this->getDescendants($io, $culture);

            // Build EAD XML. #112: pick the layout from /admin/settings/global
            // findingAidModel ('inventory-summary' default | 'full-details').
            // inventory-summary keeps the parent archdesc full but emits each
            // child with only identifier + title + scope_and_content; full-details
            // expands every child field. Unknown values fall back to inventory-summary.
            $model = \AhgCore\Support\GlobalSettings::findingAidModel();
            $xml = $this->buildEadXml($io, $repository, $events, $creators, $subjects, $places, $genres, $notes, $levelName, $children, $culture, $model);
            $this->log('Finding aid model: ' . $model);

            // Ensure downloads directory exists
            $downloadsDir = public_path('downloads');
            if (!is_dir($downloadsDir)) {
                mkdir($downloadsDir, 0755, true);
            }

            // Save XML file
            $xmlPath = $downloadsDir . '/finding-aid-' . $io->id . '.xml';
            file_put_contents($xmlPath, $xml);
            $this->log("EAD XML saved to: {$xmlPath}");

            // Update job download_path
            if ($this->jobRecordId) {
                DB::table('job')->where('id', $this->jobRecordId)->update([
                    'download_path' => '/downloads/finding-aid-' . $io->id . '.xml',
                ]);
            }

            // Convert to PDF only when findingAidFormat=pdf (operator-set
            // on /admin/settings/global). Other formats keep the XML on disk
            // as the canonical artefact; Heratio's existing eadToHtml
            // transform serves the html path inline when needed.
            $format = \AhgCore\Support\GlobalSettings::findingAidFormat();
            if ($format === 'pdf') {
                $wkhtmltopdf = trim(shell_exec('which wkhtmltopdf 2>/dev/null') ?? '');
                if (!empty($wkhtmltopdf) && file_exists($wkhtmltopdf)) {
                    $this->generatePdf($io, $xml, $downloadsDir, $wkhtmltopdf);
                } else {
                    $this->log('findingAidFormat=pdf but wkhtmltopdf is not installed; skipping PDF generation. XML remains the canonical artefact.');
                }
            } else {
                $this->log('findingAidFormat=' . $format . '; skipping PDF generation.');
            }

            $this->log('Finding aid generation complete.');
            $this->markCompleted();

        } catch (\Throwable $e) {
            $this->logError("Fatal error: {$e->getMessage()}");
            $this->markFailed();
            Log::error('FindingAidJob failed', ['exception' => $e]);
        }
    }

    // ─── PDF Generation ──────────────────────────────────────────────

    protected function generatePdf(object $io, string $xml, string $downloadsDir, string $wkhtmltopdf): void
    {
        $this->log('Converting EAD XML to PDF via wkhtmltopdf...');

        // Create a simple HTML wrapper for the EAD XML content
        $html = $this->eadToHtml($io, $xml);

        $htmlPath = $downloadsDir . '/finding-aid-' . $io->id . '.html';
        $pdfPath = $downloadsDir . '/finding-aid-' . $io->id . '.pdf';

        file_put_contents($htmlPath, $html);

        $cmd = escapeshellcmd($wkhtmltopdf)
            . ' --quiet'
            . ' --page-size A4'
            . ' --margin-top 15mm'
            . ' --margin-bottom 15mm'
            . ' --margin-left 15mm'
            . ' --margin-right 15mm'
            . ' --encoding UTF-8'
            . ' ' . escapeshellarg($htmlPath)
            . ' ' . escapeshellarg($pdfPath)
            . ' 2>&1';

        $output = shell_exec($cmd);

        // Clean up temporary HTML
        @unlink($htmlPath);

        if (file_exists($pdfPath)) {
            $this->log("PDF generated: {$pdfPath}");
            // Update download_path to point to PDF
            if ($this->jobRecordId) {
                DB::table('job')->where('id', $this->jobRecordId)->update([
                    'download_path' => '/downloads/finding-aid-' . $io->id . '.pdf',
                ]);
            }
        } else {
            $this->log('PDF generation failed: ' . ($output ?: 'unknown error') . '. XML file is still available.');
        }
    }

    protected function eadToHtml(object $io, string $xml): string
    {
        $title = htmlspecialchars($io->title ?? 'Finding Aid', ENT_QUOTES, 'UTF-8');

        // Use XSLT-like transformation via simple regex-based conversion for readability
        // Strip XML declaration and DOCTYPE
        $body = preg_replace('/<\?xml[^>]*\?>/', '', $xml);
        $body = preg_replace('/<!DOCTYPE[^>]*>/', '', $body);

        // Convert EAD elements to HTML equivalents
        $body = preg_replace('/<ead>/', '', $body);
        $body = preg_replace('/<\/ead>/', '', $body);
        $body = preg_replace('/<eadheader[^>]*>/', '<div class="ead-header">', $body);
        $body = preg_replace('/<\/eadheader>/', '</div>', $body);
        $body = preg_replace('/<archdesc[^>]*>/', '<div class="archdesc">', $body);
        $body = preg_replace('/<\/archdesc>/', '</div>', $body);
        $body = preg_replace('/<did>/', '<div class="did">', $body);
        $body = preg_replace('/<\/did>/', '</div>', $body);
        $body = preg_replace('/<unittitle[^>]*>/', '<h2>', $body);
        $body = preg_replace('/<\/unittitle>/', '</h2>', $body);
        $body = preg_replace('/<unitid[^>]*>/', '<p><strong>Identifier:</strong> ', $body);
        $body = preg_replace('/<\/unitid>/', '</p>', $body);
        $body = preg_replace('/<unitdate[^>]*>/', '<p><strong>Date:</strong> ', $body);
        $body = preg_replace('/<\/unitdate>/', '</p>', $body);
        $body = preg_replace('/<origination[^>]*>/', '<p><strong>Creator:</strong> ', $body);
        $body = preg_replace('/<\/origination>/', '</p>', $body);
        $body = preg_replace('/<physdesc[^>]*>/', '<p><strong>Extent:</strong> ', $body);
        $body = preg_replace('/<\/physdesc>/', '</p>', $body);
        $body = preg_replace('/<repository>/', '<p><strong>Repository:</strong> ', $body);
        $body = preg_replace('/<\/repository>/', '</p>', $body);

        // Section elements
        $sectionMap = [
            'scopecontent' => 'Scope and content',
            'arrangement' => 'Arrangement',
            'bioghist' => 'Biographical / historical note',
            'custodhist' => 'Archival history',
            'acqinfo' => 'Immediate source of acquisition',
            'appraisal' => 'Appraisal, destruction and scheduling',
            'accruals' => 'Accruals',
            'accessrestrict' => 'Conditions governing access',
            'userestrict' => 'Conditions governing reproduction',
            'phystech' => 'Physical characteristics',
            'otherfindaid' => 'Finding aids',
            'originalsloc' => 'Existence and location of originals',
            'altformavail' => 'Existence and location of copies',
            'relatedmaterial' => 'Related units of description',
            'bibliography' => 'Publication notes',
            'processinfo' => 'Archivist\'s note',
            'controlaccess' => 'Access points',
        ];

        foreach ($sectionMap as $tag => $heading) {
            $body = preg_replace(
                '/<' . $tag . '[^>]*>/',
                '<div class="section"><h3>' . htmlspecialchars($heading) . '</h3>',
                $body
            );
            $body = preg_replace('/<\/' . $tag . '>/', '</div>', $body);
        }

        // Nested components
        $body = preg_replace('/<dsc[^>]*>/', '<div class="dsc"><h3>Description subordinate components</h3>', $body);
        $body = preg_replace('/<\/dsc>/', '</div>', $body);
        $body = preg_replace('/<c [^>]*>/', '<div class="component" style="margin-left:20px;border-left:2px solid #ccc;padding-left:10px;margin-bottom:10px;">', $body);
        $body = preg_replace('/<\/c>/', '</div>', $body);

        // Clean remaining tags that are decorative
        $body = preg_replace('/<(persname|famname|corpname|name|extent|subject|geogname|genreform)>/', '', $body);
        $body = preg_replace('/<\/(persname|famname|corpname|name|extent|subject|geogname|genreform)>/', '', $body);

        // Remove remaining EAD-specific tags
        $body = preg_replace('/<(filedesc|titlestmt|publicationstmt|profiledesc|creation|langusage|language|eadid|titleproper|publisher|date)[^>]*>/', '', $body);
        $body = preg_replace('/<\/(filedesc|titlestmt|publicationstmt|profiledesc|creation|langusage|language|eadid|titleproper|publisher|date)>/', '', $body);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$title} — Finding Aid</title>
    <style>
        body { font-family: 'Georgia', serif; max-width: 800px; margin: 0 auto; padding: 20px; color: #333; line-height: 1.6; }
        h1 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #555; }
        h3 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-top: 20px; }
        .section { margin-bottom: 15px; }
        .did { background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .component { margin-bottom: 10px; }
        .ead-header { font-size: 0.9em; color: #777; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    {$body}
</body>
</html>
HTML;
    }

    // ─── Data fetching (mirrors ExportController) ────────────────────

    protected function getIO(string $culture): ?object
    {
        return DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->where('information_object.id', $this->informationObjectId)
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

    protected function getRepository(object $io, string $culture): ?object
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

    protected function getEvents(object $io, string $culture)
    {
        return DB::table('event')
            ->join('event_i18n', 'event.id', '=', 'event_i18n.id')
            ->where('event.object_id', $io->id)
            ->where('event_i18n.culture', $culture)
            ->select('event.id', 'event.type_id', 'event.actor_id', 'event.start_date', 'event.end_date', 'event_i18n.date as date_display')
            ->get();
    }

    protected function getCreators(object $io, string $culture)
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

    protected function getAccessPoints(object $io, int $taxonomyId, string $culture)
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

    protected function getNotes(object $io, string $culture)
    {
        return DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $io->id)
            ->where('note_i18n.culture', $culture)
            ->select('note.type_id', 'note_i18n.content')
            ->get();
    }

    protected function getLevelName(object $io, string $culture): ?string
    {
        if (!$io->level_of_description_id) {
            return null;
        }
        return DB::table('term_i18n')
            ->where('id', $io->level_of_description_id)
            ->where('culture', $culture)
            ->value('name');
    }

    protected function getDescendants(object $io, string $culture)
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

    // ─── EAD XML Builder (mirrors ExportController::buildEadXml) ─────

    protected function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function mapLevelToEad(?string $level): string
    {
        $map = [
            'Fonds' => 'fonds', 'Sub-fonds' => 'subfonds', 'Collection' => 'collection',
            'Series' => 'series', 'Sub-series' => 'subseries', 'File' => 'file',
            'Item' => 'item', 'Part' => 'item',
        ];
        return $map[$level ?? ''] ?? 'otherlevel';
    }

    protected function buildEadXml($io, $repository, $events, $creators, $subjects, $places, $genres, $notes, $levelName, $children, string $culture, string $model = 'inventory-summary'): string
    {
        $isInventory = ($model !== 'full-details');
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
                // full-details expands physdesc/extent in the did; inventory-summary
                // keeps the did to the bare identifier + title only.
                if (!$isInventory && $child->extent_and_medium) {
                    $xml .= "        <physdesc><extent>" . $this->e($child->extent_and_medium) . "</extent></physdesc>\n";
                }
                $xml .= "      </did>\n";
                if ($child->scope_and_content) {
                    $xml .= "      <scopecontent><p>" . $this->e($child->scope_and_content) . "</p></scopecontent>\n";
                }
                // arrangement is a full-details extra; inventory-summary stops at scope.
                if (!$isInventory && $child->arrangement) {
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

    protected function buildDidElement($io, $events, $creators, $repository, $levelName, string $culture): string
    {
        $xml = "  <did>\n";
        if ($io->identifier) {
            $xml .= "    <unitid encodinganalog=\"3.1.1\">" . $this->e($io->identifier) . "</unitid>\n";
        }
        $xml .= "    <unittitle encodinganalog=\"3.1.2\">" . $this->e($io->title) . "</unittitle>\n";

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

    // ─── Job record management ───────────────────────────────────────

    protected function createJobRecord(): void
    {
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitJob',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('job')->insert([
            'id' => $objectId,
            'name' => 'App\\Jobs\\FindingAidJob',
            'status_id' => self::STATUS_IN_PROGRESS,
            'object_id' => $this->informationObjectId,
            'user_id' => auth()->id(),
            'output' => '',
            'completed_at' => null,
        ]);

        $this->jobRecordId = $objectId;
    }

    protected function log(string $message): void
    {
        if ($this->jobRecordId) {
            $existing = DB::table('job')->where('id', $this->jobRecordId)->value('output') ?? '';
            DB::table('job')->where('id', $this->jobRecordId)->update([
                'output' => $existing . $message . "\n",
            ]);
        }
        Log::info("FindingAidJob: {$message}");
    }

    protected function logError(string $message): void
    {
        $this->log("ERROR: {$message}");
    }

    protected function markCompleted(): void
    {
        if ($this->jobRecordId) {
            DB::table('job')->where('id', $this->jobRecordId)->update([
                'status_id' => self::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    protected function markFailed(): void
    {
        if ($this->jobRecordId) {
            DB::table('job')->where('id', $this->jobRecordId)->update([
                'status_id' => self::STATUS_ERROR,
                'completed_at' => now(),
            ]);
        }
    }
}
