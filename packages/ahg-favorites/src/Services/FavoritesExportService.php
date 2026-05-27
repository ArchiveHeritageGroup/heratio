<?php

/**
 * FavoritesExportService - multi-format export for a folder or the full
 * favourites list. Ported from PSIS
 * (atom-ahg-plugins/ahgFavoritesPlugin/lib/Services/FavoritesExportService.php).
 *
 * Supported formats: csv, json, bibtex, ris, print (HTML), ead (EAD XML).
 * PDF is intentionally not bundled - Heratio routes printable exports through
 * the print HTML view + browser print dialog (no Dompdf dependency).
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

namespace AhgFavorites\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FavoritesExportService
{
    /**
     * Build an enriched item list for export. Resolves title, level of
     * description, date range, repository, and folder name in the current
     * locale (falling back to en when no localised row exists).
     */
    public function getEnrichedFavorites(int $userId, ?int $folderId = null): array
    {
        $q = DB::table('favorites')->where('user_id', $userId);
        if ($folderId) {
            $q->where('folder_id', $folderId);
        }
        $favorites = $q->orderBy('created_at', 'desc')->get();

        $culture = app()->getLocale();
        $enriched = [];

        foreach ($favorites as $fav) {
            $objectId = (int) $fav->archival_description_id;

            $title = null;
            $lod = null;
            $dateRange = '';
            $repositoryName = '';

            if ($objectId && ($fav->object_type ?? 'information_object') === 'information_object') {
                $title = DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', $culture)
                    ->value('title');
                if (! $title && $culture !== 'en') {
                    $title = DB::table('information_object_i18n')
                        ->where('id', $objectId)
                        ->where('culture', 'en')
                        ->value('title');
                }

                $io = DB::table('information_object')->where('id', $objectId)->first();
                if ($io) {
                    if ($io->level_of_description_id ?? null) {
                        $lod = DB::table('term_i18n')
                            ->where('id', $io->level_of_description_id)
                            ->where('culture', $culture)
                            ->value('name');
                        if (! $lod && $culture !== 'en') {
                            $lod = DB::table('term_i18n')
                                ->where('id', $io->level_of_description_id)
                                ->where('culture', 'en')
                                ->value('name');
                        }
                    }

                    $parts = [];
                    if (! empty($io->start_date ?? null)) {
                        $parts[] = $io->start_date;
                    }
                    if (! empty($io->end_date ?? null)) {
                        $parts[] = $io->end_date;
                    }
                    $dateRange = implode(' - ', $parts);

                    if ($io->repository_id ?? null) {
                        $repositoryName = DB::table('actor_i18n')
                            ->where('id', $io->repository_id)
                            ->where('culture', $culture)
                            ->value('authorized_form_of_name') ?? '';
                        if (! $repositoryName && $culture !== 'en') {
                            $repositoryName = DB::table('actor_i18n')
                                ->where('id', $io->repository_id)
                                ->where('culture', 'en')
                                ->value('authorized_form_of_name') ?? '';
                        }
                    }
                }
            }

            $folderName = '';
            if ($fav->folder_id) {
                $folderName = DB::table('favorites_folder')
                    ->where('id', $fav->folder_id)
                    ->value('name') ?? '';
            }

            $enriched[] = [
                'id' => $fav->id,
                'title' => $title ?: ($fav->archival_description ?: 'Untitled'),
                'reference_code' => $fav->reference_code ?: '',
                'level_of_description' => $lod ?: '',
                'date_range' => $dateRange,
                'repository' => $repositoryName,
                'slug' => $fav->slug ?: '',
                'notes' => $fav->notes ?: '',
                'folder' => $folderName,
                'object_type' => $fav->object_type ?? 'information_object',
                'created_at' => $fav->created_at,
            ];
        }

        return $enriched;
    }

    public function streamCsv(int $userId, ?int $folderId = null, string $filename = 'favorites.csv'): StreamedResponse
    {
        $items = $this->getEnrichedFavorites($userId, $folderId);

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                __('Title'), __('Reference Code'), __('Level'), __('Dates'), __('Repository'),
                __('Slug'), __('Notes'), __('Folder'), __('Date Added'),
            ]);
            foreach ($items as $i) {
                fputcsv($out, [
                    $i['title'], $i['reference_code'], $i['level_of_description'], $i['date_range'],
                    $i['repository'], $i['slug'], $i['notes'], $i['folder'], $i['created_at'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function streamJson(int $userId, ?int $folderId = null, string $filename = 'favorites.json'): Response
    {
        $items = $this->getEnrichedFavorites($userId, $folderId);
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'count' => count($items),
            'favorites' => $items,
        ];

        return response(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function streamBibTeX(int $userId, ?int $folderId = null, string $filename = 'favorites.bib'): Response
    {
        $items = $this->getEnrichedFavorites($userId, $folderId);
        $out = '';

        foreach ($items as $item) {
            $key = 'fav'.$item['id'];
            $fields = [];

            if ($item['title']) {
                $fields[] = '  title = {'.$this->escapeBibTeX($item['title']).'}';
            }
            if ($item['reference_code']) {
                $fields[] = '  note = {'.$this->escapeBibTeX(__('Reference Code').': '.$item['reference_code']).'}';
            }
            if ($item['slug']) {
                $fields[] = '  howpublished = {\\url{'.$this->escapeBibTeX($item['slug']).'}}';
            }
            if ($item['repository']) {
                $fields[] = '  organization = {'.$this->escapeBibTeX($item['repository']).'}';
            }
            if ($item['date_range'] && preg_match('/\d{4}/', $item['date_range'], $m)) {
                $fields[] = '  year = {'.$m[0].'}';
            }
            if ($item['notes']) {
                $fields[] = '  annote = {'.$this->escapeBibTeX($item['notes']).'}';
            }

            $out .= "@misc{{$key},\n".implode(",\n", $fields)."\n}\n\n";
        }

        return response($out, 200, [
            'Content-Type' => 'application/x-bibtex',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function streamRis(int $userId, ?int $folderId = null, string $filename = 'favorites.ris'): Response
    {
        $items = $this->getEnrichedFavorites($userId, $folderId);
        $out = '';

        foreach ($items as $item) {
            $lines = ['TY  - GEN'];
            if ($item['title']) {
                $lines[] = 'TI  - '.$item['title'];
            }
            if ($item['reference_code']) {
                $lines[] = 'AN  - '.$item['reference_code'];
            }
            if ($item['slug']) {
                $lines[] = 'UR  - '.$item['slug'];
            }
            if ($item['repository']) {
                $lines[] = 'PB  - '.$item['repository'];
            }
            if ($item['date_range']) {
                $lines[] = 'PY  - '.$item['date_range'];
            }
            if ($item['notes']) {
                $lines[] = 'N1  - '.$item['notes'];
            }
            if ($item['level_of_description']) {
                $lines[] = 'M1  - '.$item['level_of_description'];
            }
            $lines[] = 'ER  - ';
            $out .= implode("\n", $lines)."\n\n";
        }

        return response($out, 200, [
            'Content-Type' => 'application/x-research-info-systems',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Minimal EAD3 (Encoded Archival Description) wrapper. Only used when
     * a folder of information_object favourites is exported - non-IO items
     * are dropped from the output since EAD only models archival components.
     */
    public function streamEad(int $userId, ?int $folderId = null, string $filename = 'favorites.xml'): Response
    {
        $items = $this->getEnrichedFavorites($userId, $folderId);
        $folderName = 'Favorites';
        if ($folderId) {
            $folderName = DB::table('favorites_folder')->where('id', $folderId)->value('name') ?? $folderName;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<ead xmlns="http://ead3.archivists.org/schema/" audience="external">'."\n";
        $xml .= "  <control>\n";
        $xml .= '    <recordid>heratio-favorites-'.($folderId ?: 'all').'</recordid>'."\n";
        $xml .= '    <filedesc><titlestmt><titleproper>'.htmlspecialchars($folderName, ENT_XML1).'</titleproper></titlestmt></filedesc>'."\n";
        $xml .= '    <maintenancestatus value="new"/>'."\n";
        $xml .= '    <maintenanceagency><agencyname>Heratio</agencyname></maintenanceagency>'."\n";
        $xml .= "  </control>\n";
        $xml .= "  <archdesc level=\"collection\">\n";
        $xml .= "    <did>\n";
        $xml .= '      <unittitle>'.htmlspecialchars($folderName, ENT_XML1).'</unittitle>'."\n";
        $xml .= '      <unitdate>'.now()->toDateString().'</unitdate>'."\n";
        $xml .= "    </did>\n";
        $xml .= "    <dsc>\n";

        foreach ($items as $item) {
            if ($item['object_type'] !== 'information_object') {
                continue;
            }
            $xml .= "      <c level=\"item\">\n";
            $xml .= "        <did>\n";
            $xml .= '          <unittitle>'.htmlspecialchars($item['title'], ENT_XML1).'</unittitle>'."\n";
            if ($item['reference_code']) {
                $xml .= '          <unitid>'.htmlspecialchars($item['reference_code'], ENT_XML1).'</unitid>'."\n";
            }
            if ($item['date_range']) {
                $xml .= '          <unitdate>'.htmlspecialchars($item['date_range'], ENT_XML1).'</unitdate>'."\n";
            }
            if ($item['level_of_description']) {
                $xml .= '          <physdesc>'.htmlspecialchars($item['level_of_description'], ENT_XML1).'</physdesc>'."\n";
            }
            $xml .= "        </did>\n";
            if ($item['notes']) {
                $xml .= '        <scopecontent><p>'.htmlspecialchars($item['notes'], ENT_XML1).'</p></scopecontent>'."\n";
            }
            $xml .= "      </c>\n";
        }

        $xml .= "    </dsc>\n  </archdesc>\n</ead>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function printHtml(int $userId, ?int $folderId = null): string
    {
        $items = $this->getEnrichedFavorites($userId, $folderId);

        $title = __('My Favorites');
        if ($folderId) {
            $folderName = DB::table('favorites_folder')->where('id', $folderId)->value('name');
            if ($folderName) {
                $title = __('Favorites: :name', ['name' => $folderName]);
            }
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'.htmlspecialchars($title).'</title>';
        $html .= '<style>body{font-family:Arial,sans-serif;margin:20px;}h1{font-size:20px;color:#1a1a2e;}';
        $html .= 'table{width:100%;border-collapse:collapse;font-size:12px;}th{background:#1a1a2e;color:#fff;padding:8px;text-align:left;}';
        $html .= 'td{padding:6px 8px;border-bottom:1px solid #dee2e6;}tr:nth-child(even){background:#f8f9fa;}';
        $html .= '@media print{body{margin:0;}}</style></head><body>';
        $html .= '<h1>'.htmlspecialchars($title).'</h1>';
        $html .= '<p style="font-size:12px;color:#666;">'.__('Printed on :date', ['date' => now()->format('Y-m-d H:i')]);
        $html .= ' - '.count($items).' '.__('items').'</p>';

        if (! empty($items)) {
            $html .= '<table><thead><tr><th>'.__('Title').'</th><th>'.__('Reference Code').'</th>';
            $html .= '<th>'.__('Level').'</th><th>'.__('Dates').'</th><th>'.__('Repository').'</th><th>'.__('Notes').'</th>';
            $html .= '</tr></thead><tbody>';
            foreach ($items as $i) {
                $html .= '<tr><td>'.htmlspecialchars($i['title']).'</td>';
                $html .= '<td>'.htmlspecialchars($i['reference_code']).'</td>';
                $html .= '<td>'.htmlspecialchars($i['level_of_description']).'</td>';
                $html .= '<td>'.htmlspecialchars($i['date_range']).'</td>';
                $html .= '<td>'.htmlspecialchars($i['repository']).'</td>';
                $html .= '<td>'.htmlspecialchars($i['notes']).'</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<p>'.__('No favorites to display.').'</p>';
        }

        $html .= '<script>window.print();</script></body></html>';

        return $html;
    }

    private function escapeBibTeX(string $text): string
    {
        return str_replace(
            ['&', '%', '$', '#', '_', '{', '}', '~', '^'],
            ['\\&', '\\%', '\\$', '\\#', '\\_', '\\{', '\\}', '\\textasciitilde{}', '\\textasciicircum{}'],
            $text
        );
    }
}
