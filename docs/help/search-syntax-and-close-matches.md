> Heratio Help Center article. Category: User Guide / Search.

# Search Syntax, Close Matches and "Did You Mean"

The search box at the top of the GLAM browse page (`/glam/browse`) does more than match the words you type. It understands a small search language, it notices when a search finds nothing, and it offers a correction or close matches instead of an empty page. What you get depends on one setting: whether your site sends the search box to the search index (Elasticsearch) or answers it from the database. Your administrator chooses that under **Admin -> AHG Settings** with the setting `ahg_display_search_es`. It is off by default.

## Search syntax

On a site that searches the index, all of these work in the browse search box:

| You type | What it finds |
|---|---|
| `mining labour` | Records with both words. All words must match. |
| `"mining labour"` | The exact phrase. |
| `"mining labour"~5` | Both words within five words of each other. |
| `mining OR labour` (or `mining \| labour`) | Records with either word. |
| `mining NOT gold` (or `mining -gold`) | Records with "mining" but without "gold". |
| `mining AND (gold OR coal)` | Brackets group the parts. |
| `photo*` | Words that begin with "photo": photograph, photographic, photos. |
| `wom?n` | One letter of any kind in place of the `?`: woman, women. |
| `Johanesburg~` | Close spellings, one or two letters different: Johannesburg. |
| `/19[0-9]{2}/` | A regular expression, matched against whole words. |

Write AND, OR and NOT in capitals. In lower case they are searched as ordinary words.

A wildcard cannot start a word (`*graph` is not allowed), because it would have to read every word in the index. Regular expressions and wildcards in the middle of a word run under a time limit of two seconds. A pattern that would take longer returns what was found in that time. If the search language in a query cannot be read, for example an unclosed bracket or quote, the search runs again on the plain words instead of failing.

On a site that answers from the database, the operators are not available. Words are matched as you type them, anywhere in the title, the description, the identifier and the other catalogue fields, and the symbols are ignored.

## When a search finds nothing

### With the search index

When nothing matches exactly, Heratio searches again with every word allowed one or two letters of difference (one for words of three to five letters, two for longer words; the first letter must match). If that finds records, the page says **Showing close matches for "..."** above them, so you always know the results are not exact matches. Choose **Exact matches only** to see the empty exact result instead.

The index can also suggest a corrected spelling, shown as **Did you mean: "..."?**. Select it to search for the suggestion.

### With the database

The database has no close-match search of its own, so Heratio keeps a word list instead: the words in the titles and the subject, place and genre access points of published records. When a search finds nothing, each word that is not in the list is compared with list words that start with the same letter and are of a similar length, and replaced by the closest one: one letter of difference for words of up to seven letters, two for longer words.

If the corrected search finds records, it is run for you and the page says **Showing results for "..."**, with a **Search instead for "..."** link back to what you typed. If it finds nothing either, the correction is offered as a **Did you mean** link.

## What suggestions are made from

Suggestions only ever come from published records that anyone may see. Draft records, embargoed records, records with a security classification, records withheld under an ICIP, cultural-protocol or rights restriction, and records of another tenant contribute no words, so a suggestion never reveals the existence of something you could not find for yourself. The corrected search then runs through exactly the same filters as any other search: the sector, collection, facets and access rules you have in place all still apply.

## Turning correction off

- For one search, add `noCorrect=1` to the address, or use the **Search instead for** or **Exact matches only** link. Either shows the exact result, empty or not.
- Administrators can switch the behaviour off for the whole site in the AHG settings table:

| Setting | Default | Effect |
|---|---|---|
| `ahg_display_search_correction` | on | Off: no did-you-mean, no close matches, no correction. |
| `ahg_display_search_autocorrect` | on | Off (database path): the correction is only offered as a link, never run. |
| `ahg_display_vocab_cache_ttl` | 3600 | Seconds the database word list is kept before it is rebuilt. 0 rebuilds it for every correction. |

## For administrators

The word list is cached for each interface language and holds at most the 50,000 most frequent words, built from up to 300,000 titles (newest first) plus the access-point terms. It is only built and read when a search returns no results, so ordinary searches pay nothing for it. A new record's words appear in suggestions once the cache expires. A change to embargoes, classifications or other restrictions gives a new list straight away, because the restricted set is part of the cache key.

Close matches and the "did you mean" on the index path are worked out by Elasticsearch: a `multi_match` query with `fuzziness: AUTO` over the same fields the normal search uses, and a phrase suggester on the `all` field whose suggestions are checked against published, non-embargoed records before they are shown. The code is in `packages/ahg-search/src/Services/BrowseQueryBuilder.php`, `packages/ahg-display/src/Services/SearchVocabularyService.php` and `DisplayController::searchCorrection()`.

## Related articles

- Search user guide
- Advanced search user guide
- Semantic search user guide
- Elasticsearch configuration
