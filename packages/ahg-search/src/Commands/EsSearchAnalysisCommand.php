<?php

/**
 * EsSearchAnalysisCommand - give the description index word stemming and
 * country / nationality synonyms for the browse search box (heratio#1575),
 * in place: no rebuild from the database.
 *
 * Adds a `stem` sub-field to i18n.en.title and i18n.en.scopeAndContent,
 * indexed with English stemming (letter = letters), searched with the same
 * plus a synonym list pairing countries and their adjectives (Belgium =
 * Belgian), which stemming alone cannot do. Adding analysers to an existing
 * index needs it closed for a moment (a second or two of failed searches,
 * which fall back to the database path); the mapping and the update-by-query
 * that fills the new sub-field run with the index open.
 *
 * Re-run after any rebuild that drops and recreates the index.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSearch\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EsSearchAnalysisCommand extends Command
{
    protected $signature = 'ahg:es-search-analysis
        {--dry-run : Show what would change without touching the index}';

    protected $description = 'Add English stemming and country synonyms to the description index for the browse search box';

    /** Country and nationality forms searched as one another. */
    public const DEMONYMS = [
        'afghanistan, afghan', 'albania, albanian', 'algeria, algerian', 'angola, angolan', 'argentina, argentine, argentinian',
        'armenia, armenian', 'australia, australian', 'austria, austrian', 'bangladesh, bangladeshi', 'belgium, belgian',
        'bolivia, bolivian', 'botswana, botswanan, batswana', 'brazil, brazilian', 'britain, british', 'bulgaria, bulgarian',
        'burundi, burundian', 'cameroon, cameroonian', 'canada, canadian', 'chile, chilean', 'china, chinese', 'colombia, colombian',
        'congo, congolese', 'croatia, croatian', 'cuba, cuban', 'cyprus, cypriot', 'czechia, czech', 'denmark, danish',
        'egypt, egyptian', 'england, english', 'eritrea, eritrean', 'eswatini, swazi, swaziland', 'ethiopia, ethiopian',
        'finland, finnish', 'france, french', 'germany, german', 'ghana, ghanaian', 'greece, greek', 'guinea, guinean',
        'hungary, hungarian', 'iceland, icelandic', 'india, indian', 'indonesia, indonesian', 'iran, iranian', 'iraq, iraqi',
        'ireland, irish', 'israel, israeli', 'italy, italian', 'jamaica, jamaican', 'japan, japanese', 'jordan, jordanian',
        'kenya, kenyan', 'korea, korean', 'lebanon, lebanese', 'lesotho, basotho', 'liberia, liberian', 'libya, libyan',
        'madagascar, malagasy', 'malawi, malawian', 'malaysia, malaysian', 'mali, malian', 'mauritius, mauritian',
        'mexico, mexican', 'morocco, moroccan', 'mozambique, mozambican', 'namibia, namibian', 'netherlands, dutch, holland',
        'new zealand, new zealander', 'nicaragua, nicaraguan', 'nigeria, nigerian', 'norway, norwegian', 'pakistan, pakistani',
        'palestine, palestinian', 'peru, peruvian', 'philippines, filipino', 'poland, polish', 'portugal, portuguese',
        'romania, romanian', 'russia, russian', 'rwanda, rwandan', 'scotland, scottish', 'senegal, senegalese', 'serbia, serbian',
        'sierra leone, sierra leonean', 'somalia, somali', 'south africa, south african', 'spain, spanish', 'sudan, sudanese',
        'sweden, swedish', 'switzerland, swiss', 'syria, syrian', 'tanzania, tanzanian', 'thailand, thai', 'tunisia, tunisian',
        'turkey, turkish', 'uganda, ugandan', 'ukraine, ukrainian', 'united states, usa, america, american',
        'venezuela, venezuelan', 'vietnam, vietnamese', 'wales, welsh', 'yugoslavia, yugoslav', 'zambia, zambian',
        'zimbabwe, zimbabwean', 'soviet union, soviet',
    ];

    public function handle(): int
    {
        $host = rtrim(config('services.elasticsearch.host', 'http://localhost:9200'), '/');
        $index = config('services.elasticsearch.prefix', 'heratio_').'qubitinformationobject';
        $url = "{$host}/{$index}";
        $dry = (bool) $this->option('dry-run');

        $settings = Http::get("{$url}/_settings")->json("{$index}.settings.index") ?? null;
        if ($settings === null) {
            $this->error("Index {$index} not found at {$host}.");

            return self::FAILURE;
        }

        // 1. Analysers - only if missing; needs a brief close.
        if (! isset($settings['analysis']['analyzer']['heratio_stem'])) {
            $this->info("Adding the stemming and synonym analysers to {$index} (closes it for a moment)");
            if (! $dry) {
                $analysis = [
                    'filter' => [
                        'heratio_stemmer' => ['type' => 'stemmer', 'language' => 'english'],
                        'heratio_demonyms' => ['type' => 'synonym_graph', 'synonyms' => array_values(array_unique(self::DEMONYMS))],
                    ],
                    'analyzer' => [
                        'heratio_stem' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['lowercase', 'asciifolding', 'heratio_stemmer']],
                        'heratio_stem_search' => ['type' => 'custom', 'tokenizer' => 'standard', 'filter' => ['lowercase', 'asciifolding', 'heratio_demonyms', 'heratio_stemmer']],
                    ],
                ];
                Http::send('POST', "{$url}/_close")->throw();
                try {
                    Http::put("{$url}/_settings", ['analysis' => $analysis])->throw();
                } finally {
                    Http::send('POST', "{$url}/_open")->throw();
                }
                Http::timeout(60)->get("{$host}/_cluster/health/{$index}", ['wait_for_status' => 'yellow', 'timeout' => '50s']);
            }
        } else {
            $this->info('Analysers already present.');
        }

        // 2. The stem sub-field on the English title and description, keeping
        //    each field's own definition (a multi-field is added by restating it).
        $en = Http::get("{$url}/_mapping")->json("{$index}.mappings.properties.i18n.properties.en.properties") ?? [];
        $props = [];
        foreach (['title', 'scopeAndContent'] as $field) {
            $def = $en[$field] ?? null;
            if (! $def || ($def['type'] ?? '') !== 'text' || isset($def['fields']['stem'])) {
                continue;
            }
            $def['fields']['stem'] = ['type' => 'text', 'analyzer' => 'heratio_stem', 'search_analyzer' => 'heratio_stem_search'];
            $props[$field] = $def;
        }
        if ($props === []) {
            $this->info('Stem sub-fields already present.');

            return self::SUCCESS;
        }
        $this->info('Adding stem sub-fields: '.implode(', ', array_keys($props)));
        if ($dry) {
            return self::SUCCESS;
        }
        Http::put("{$url}/_mapping", ['properties' => ['i18n' => ['properties' => ['en' => ['properties' => $props]]]]])->throw();

        // 3. Fill the new sub-field from each document's own source, in the background.
        $task = Http::send('POST', "{$url}/_update_by_query?conflicts=proceed&wait_for_completion=false&slices=auto")->throw()->json('task');
        $this->info("Filling the sub-fields in the background, task {$task}. Progress: GET {$host}/_tasks/{$task}");

        return self::SUCCESS;
    }
}
