<?php

/**
 * ScholarshipDiscoverCommand - print generative-scholarship discovery for one
 * record (heratio#1210). Verification + batch/CLI use.
 *
 *   php artisan ahg:scholarship-discover --id=905228
 *
 * Prints the record, its grouped catalogue connections (the ground truth), and
 * the AI-surfaced research leads from ScholarshipService::discover(). If the AI
 * gateway is unreachable the command still succeeds and reports the empty
 * insight set - it never throws on the AI path.
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

namespace AhgSemanticSearch\Console\Commands;

use AhgSemanticSearch\Services\ScholarshipService;
use Illuminate\Console\Command;

class ScholarshipDiscoverCommand extends Command
{
    protected $signature = 'ahg:scholarship-discover
        {--id= : The information_object id to discover connections for}';

    protected $description = 'Surface non-obvious, graph-grounded connections + research leads for one record (heratio#1210)';

    public function handle(ScholarshipService $service): int
    {
        $id = $this->option('id');
        if ($id === null || $id === '' || ! ctype_digit((string) $id)) {
            $this->error('Provide a numeric record id: --id=905228');

            return self::FAILURE;
        }

        $objectId = (int) $id;
        $discovery = $service->discover($objectId);

        $rec = $discovery['record'];
        $title = $rec['title'] ?? ('#'.$rec['id']);

        $this->line('<info>Record:</info> '.$title.' (#'.$rec['id'].')');
        if (! empty($rec['slug'])) {
            $this->line('<info>Slug:</info>   '.$rec['slug']);
        }
        $this->line(sprintf(
            '<info>Graph:</info>  %d direct, %d indirect (2nd hop), %d entities given to the AI.',
            (int) $discovery['total'],
            (int) $discovery['second_hop_count'],
            (int) $discovery['grounded_entities']
        ));
        $this->newLine();

        // The verified ground truth.
        $this->line('<comment>Catalogue connections (ground truth):</comment>');
        if (empty($discovery['connections'])) {
            $this->line('  (none - this record has no cross-collection graph links)');
        } else {
            foreach ($discovery['connections'] as $group) {
                $names = array_map(
                    fn ($i) => (string) ($i['name'] ?? ''),
                    array_slice($group['items'], 0, 10)
                );
                $names = array_filter($names);
                $more = $group['count'] - count($names);
                $tail = $more > 0 ? ' (+'.$more.' more)' : '';
                $this->line(sprintf('  - %s (%d): %s%s',
                    $group['domain'], $group['count'], implode('; ', $names), $tail));
            }
        }
        $this->newLine();

        // The AI-surfaced leads.
        $this->line('<comment>Research leads (AI, grounded in the above):</comment>');
        if (! ($discovery['ai_available'] ?? false)) {
            $this->warn('  AI gateway unreachable - no leads generated (graph connections above are unaffected).');
        } elseif (empty($discovery['insights'])) {
            $this->line('  (the AI found no non-obvious connections beyond the direct links)');
        } else {
            foreach ($discovery['insights'] as $n => $insight) {
                $this->line('  '.($n + 1).'. '.$insight);
            }
        }
        $this->newLine();
        $this->comment('AI-generated, grounded in catalogue links - verify before citing.');

        return self::SUCCESS;
    }
}
