<?php

/**
 * ResultCitationService - "Cite these results" (heratio#1585).
 *
 * Stores a short-coded snapshot of a GLAM browse view or a catalogue-assistant
 * answer and writes its citation in Chicago, APA, BibTeX and RIS. A snapshot
 * keeps the parameters, date, count, Heratio version and search-index
 * generation; for an AI answer also the model, the inference receipt and the
 * ids of the records it rested on. It never keeps a record title: a citation
 * link re-runs under the reader's own rights, and the records an AI answer
 * cites are listed only where the reader may see them.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSearch\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ResultCitationService
{
    /** Browse parameters that are page state, not part of the result set. */
    private const DROP = ['page', '_token', 'ajax', 'cite'];

    public const FORMATS = ['chicago', 'apa', 'bibtex', 'ris'];

    /** Snapshot a GLAM browse view; the count is the current viewer's. */
    public function citeBrowse(array $params): object
    {
        $params = $this->cleanParams($params);
        $query = trim((string) ($params['query'] ?? ''));

        return $this->store([
            'kind' => 'browse',
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
            'query_text' => $query !== '' ? mb_substr($query, 0, 500) : null,
            'result_count' => app(BrowseCounter::class)->total($params),
        ]);
    }

    /**
     * Snapshot a catalogue-assistant answer. Only the person who asked may cite
     * it: the message must belong to their own chatbot session. Null otherwise.
     */
    public function citeChatbot(int $messageId): ?object
    {
        $msg = DB::table('ahg_ai_chatbot_message')->where('id', $messageId)->where('role', 'assistant')->first();
        if (! $msg || ! Auth::check() || $msg->session_id !== hash('sha256', 'user:'.Auth::id())) {
            return null;
        }
        $existing = DB::table('ahg_cited_view')->where('kind', 'chatbot')->where('source_id', $messageId)->first();
        if ($existing) {
            return $existing;
        }
        $question = DB::table('ahg_ai_chatbot_message')->where('session_id', $msg->session_id)
            ->where('role', 'user')->where('id', '<', $messageId)->orderByDesc('id')->value('content');
        $slugs = array_values(array_filter(array_map(fn ($s) => $s['slug'] ?? null, (array) json_decode((string) $msg->sources, true))));
        $ids = $slugs ? DB::table('slug')->whereIn('slug', $slugs)->pluck('object_id')->map(fn ($i) => (int) $i)->unique()->values()->all() : [];

        return $this->store([
            'kind' => 'chatbot',
            'query_text' => $question !== null ? mb_substr((string) $question, 0, 500) : null,
            'result_count' => count($ids),
            'ai_model' => $msg->model,
            'ai_receipt_seq' => $msg->receipt_seq ?? null,
            'ai_receipt_hash' => $msg->receipt_hash ?? null,
            'record_ids' => json_encode($ids),
            'source_id' => $messageId,
        ]);
    }

    public function find(string $code): ?object
    {
        return DB::table('ahg_cited_view')->where('code', $code)->first();
    }

    /**
     * The cited records the current viewer may see, with their live titles:
     * staff see all, everyone else only published records outside restricted
     * protocols (the browse page's own rule).
     *
     * @return list<object{id:int, identifier:?string, title:?string, slug:?string}>
     */
    public function visibleRecords(object $cite): array
    {
        $ids = array_map('intval', (array) json_decode((string) $cite->record_ids, true));
        if (! $ids) {
            return [];
        }
        $q = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'io.id')->where('i.culture', '=', app()->getLocale()))
            ->leftJoin('information_object_i18n as f', fn ($j) => $j->on('f.id', '=', 'io.id')->where('f.culture', '=', 'en'))
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->whereIn('io.id', $ids)
            ->select('io.id', 'io.identifier', 'slug.slug', DB::raw("COALESCE(NULLIF(i.title, ''), f.title) AS title"));
        if (! Auth::check()) {
            $q->whereExists(fn ($s) => $s->select(DB::raw(1))->from('status as st')->whereColumn('st.object_id', 'io.id')
                ->where('st.type_id', 158)->where('st.status_id', 160));
            \AhgCore\Services\TermProtocolGate::excludeRestrictedRecords($q, 'io.id');
        }
        $rows = $q->get()->keyBy('id');

        return array_values(array_filter(array_map(fn ($id) => $rows[$id] ?? null, $ids)));
    }

    /** The citation in one format, for the current viewer. */
    public function format(object $cite, string $format): string
    {
        $f = $this->facts($cite);

        return match ($format) {
            'chicago' => "{$f['repo']}. \u{201C}{$f['title']}.\u{201D} {$f['what']}, {$f['version']}. Accessed {$f['accessedLong']}. {$f['url']}.",
            'apa' => "{$f['repo']}. ({$f['year']}). {$f['title']} [{$f['what']}]. {$f['version']}. Retrieved {$f['accessedLong']}, from {$f['url']}",
            'bibtex' => $this->bibtex($cite, $f),
            'ris' => $this->ris($cite, $f),
            default => '',
        };
    }

    /** @return array<string, string> */
    public function facts(object $cite): array
    {
        $created = \Carbon\Carbon::parse($cite->created_at);
        $count = (int) $cite->result_count;
        $q = (string) $cite->query_text;
        if ($cite->kind === 'chatbot') {
            $title = 'Answer by the catalogue assistant'.($q !== '' ? " to \u{2018}".Str::limit($q, 160)."\u{2019}" : '');
            $what = 'AI-generated answer, model '.($cite->ai_model ?: 'unrecorded').', resting on '.$count.' '.($count === 1 ? 'record' : 'records');
        } else {
            $title = ($q !== '' ? "Search for \u{2018}".Str::limit($q, 160)."\u{2019}" : 'Catalogue browse').$this->filterText($cite);
            $what = 'Search results, '.number_format($count).' '.($count === 1 ? 'record' : 'records').($cite->viewer_scope === 'staff' ? ' (staff view)' : '');
        }

        return [
            'repo' => $this->repositoryName(),
            'title' => $title,
            'what' => $what,
            'version' => 'Heratio '.$cite->heratio_version,
            'year' => $created->format('Y'),
            'date' => $created->format('Y-m-d'),
            'accessedLong' => $created->format('F j, Y'),
            'url' => route('results.cite.show', $cite->code),
            'receipt' => $cite->ai_receipt_seq ? 'inference receipt #'.$cite->ai_receipt_seq.' ('.substr((string) $cite->ai_receipt_hash, 0, 16).')' : '',
        ];
    }

    private function filterText(object $cite): string
    {
        $params = (array) json_decode((string) $cite->params, true);
        unset($params['query'], $params['view'], $params['sort'], $params['sortDir'], $params['dir'], $params['limit']);
        $parts = [];
        foreach ($params as $k => $v) {
            $parts[] = $k.': '.(is_array($v) ? implode('|', $v) : $v);
        }

        return $parts ? ' ('.implode('; ', $parts).')' : '';
    }

    private function note(object $cite, array $f): string
    {
        $bits = [$f['what'], $f['version']];
        if ($cite->index_generation) {
            $bits[] = 'search index generation '.$cite->index_generation;
        }
        if ($f['receipt'] !== '') {
            $bits[] = $f['receipt'];
        }
        if ($cite->kind === 'chatbot') {
            $ids = array_filter(array_map(fn ($r) => $r->identifier ?: $r->slug, $this->visibleRecords($cite)));
            if ($ids) {
                $bits[] = 'records: '.implode(', ', $ids);
            }
        }

        return implode('; ', $bits);
    }

    private function bibtex(object $cite, array $f): string
    {
        $e = fn (string $s) => str_replace(['\\', '{', '}', '%', '&', '#', '_'], ['\\textbackslash{}', '\\{', '\\}', '\\%', '\\&', '\\#', '\\_'], $s);

        return "@misc{heratio-{$cite->code},\n"
            ."  author = {{{$e($f['repo'])}}},\n"
            ."  title = {{$e($f['title'])}},\n"
            ."  year = {{$f['year']}},\n"
            ."  howpublished = {\\url{{$f['url']}}},\n"
            ."  urldate = {{$f['date']}},\n"
            ."  note = {{$e($this->note($cite, $f))}}\n}";
    }

    private function ris(object $cite, array $f): string
    {
        $v = fn (string $s) => preg_replace('/\s+/', ' ', $s);

        return implode("\r\n", [
            'TY  - '.($cite->kind === 'chatbot' ? 'GEN' : 'DBASE'),
            'AU  - '.$v($f['repo']),
            'TI  - '.$v($f['title']),
            'PY  - '.$f['year'],
            'DA  - '.str_replace('-', '/', $f['date']),
            'Y2  - '.str_replace('-', '/', $f['date']),
            'UR  - '.$f['url'],
            'N1  - '.$v($this->note($cite, $f)),
            'ER  - ',
        ])."\r\n";
    }

    private function store(array $row): object
    {
        do {
            $code = strtolower(Str::random(8));
        } while (DB::table('ahg_cited_view')->where('code', $code)->exists());

        $row += [
            'code' => $code,
            'viewer_scope' => Auth::check() ? 'staff' : 'public',
            'heratio_version' => self::version(),
            'index_generation' => self::indexGeneration(),
            'created_by' => Auth::id(),
            'created_at' => now(),
        ];
        $id = DB::table('ahg_cited_view')->insertGetId($row);

        return DB::table('ahg_cited_view')->where('id', $id)->first();
    }

    /** @return array<string, mixed> */
    private function cleanParams(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            if (! is_string($k) || in_array($k, self::DROP, true) || strlen($k) > 40 || $v === null || $v === '') {
                continue;
            }
            if (is_array($v)) {
                $v = array_values(array_filter($v, 'is_scalar'));
                if ($v === []) {
                    continue;
                }
            } elseif (! is_scalar($v)) {
                continue;
            }
            $out[$k] = is_array($v) ? array_map(fn ($x) => mb_substr((string) $x, 0, 200), $v) : mb_substr((string) $v, 0, 500);
        }
        ksort($out);

        return $out;
    }

    public static function version(): string
    {
        $v = json_decode((string) @file_get_contents(base_path('version.json')), true);

        return (string) ($v['version'] ?? 'unknown');
    }

    /**
     * The search index's generation: Elasticsearch's uuid for the records index,
     * which changes when the index is rebuilt. Null when it cannot be read.
     */
    public static function indexGeneration(): ?string
    {
        return Cache::remember('cite:index-generation', 600, function () {
            try {
                $index = config('services.elasticsearch.prefix', 'heratio_').'qubitinformationobject';
                $r = Http::timeout(2)->get(rtrim((string) config('services.elasticsearch.host'), '/').'/'.$index.'/_settings');
                $uuid = $r->ok() ? data_get(collect($r->json())->first(), 'settings.index.uuid') : null;

                return is_string($uuid) ? $uuid : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    private function repositoryName(): string
    {
        return Cache::remember('cite:repository-name', 600, function () {
            $id = DB::table('setting')->where('name', 'siteTitle')->value('id');
            $name = $id ? trim((string) DB::table('setting_i18n')->where('id', $id)->where('culture', 'en')->value('value')) : '';

            return $name !== '' ? $name : 'Heratio catalogue';
        });
    }
}
