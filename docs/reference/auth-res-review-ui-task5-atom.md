# AHG Authority Resolution - Review UI (Task 5, AtoM side)

Built 2026-05-19 under `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/`. This is the AtoM Heratio (Symfony 1.4 + AHG plugins) sibling of the Laravel `ahg-authority-resolution` Task 5 review UI. The two screens are functionally equivalent; this doc captures AtoM-specific implementation choices so the next agent can extend either side without rediscovering them.

## Summary

A three-region admin screen (`/admin/authorityResolution/:id/review`) lets archivists evaluate a promoted mention and commit one of five decisions: link / link-different / create-new / park / reject. Behind the screen, `DecisionRecorder::record()` is the single entry point that:

1. Freezes the visible candidate slate as a JSON snapshot.
2. Freezes the chosen candidate's evidence signals + data as a JSON snapshot.
3. Inserts an immutable `ahg_mention_decision` row.
4. Transitions `ahg_mention.state`.
5. For link / link-different: back-updates `ahg_ner_entity.linked_actor_id` (the existing consumer contract used by `ahgPrivacyPlugin` / `ahgLibraryPlugin` / `ahgDiscoveryPlugin`).
6. For park: writes the `ahg_mention_park` row with reason.
7. Synchronously calls `DecisionProvenanceWriter::write()` so the RDF-Star tuple lands in `urn:atom:auth-res:graph:decisions` on Fuseki within the same request. There is no queue on AtoM.

## Module layout

```
atom-ahg-plugins/ahgAuthorityResolutionPlugin/
  config/ahgAuthorityResolutionPluginConfiguration.class.php   <- registers routes + module
  lib/Services/DecisionRecorder.php                            <- new, shared decision pipeline
  modules/authorityResolution/
    actions/actions.class.php                                  <- 7 actions (index, review, link, linkDifferent, createNew, park, reject, lookup)
    templates/
      indexSuccess.php                                         <- queue list
      reviewSuccess.php                                        <- 3-region screen
      _candidateCard.php                                       <- one candidate (used by review)
      _evidenceRow.php                                         <- one dimension row inside a card
      _linkDifferentModal.php                                  <- typeahead modal for link-different
      _parkModal.php                                           <- reason modal for park
```

## Routes

Registered through `AtomFramework\Routing\RouteLoader` in the plugin configuration. Same pattern as `ahgAuthorityPlugin`. No `routing.yml` is needed — the plugin configuration class is autoloaded once the plugin is enabled via `atom_plugin` (`is_enabled=1`).

```
ar_auth_res_index            GET   /admin/authorityResolution
ar_auth_res_review           GET   /admin/authorityResolution/:id/review
ar_auth_res_link             POST  /admin/authorityResolution/:id/link
ar_auth_res_link_different   POST  /admin/authorityResolution/:id/link-different
ar_auth_res_create_new       POST  /admin/authorityResolution/:id/create-new   (Task 6 stub)
ar_auth_res_park             POST  /admin/authorityResolution/:id/park
ar_auth_res_reject           POST  /admin/authorityResolution/:id/reject
ar_auth_res_lookup           GET   /admin/authorityResolution/lookup
```

The action handlers detect `X-Requested-With: XMLHttpRequest` or `?format=json` and return `application/json`; otherwise they redirect to the next pending review or back to the queue (flash-noticed).

## AtoM-specific layout / theming patterns discovered

- The admin layout used is `layout_1col` from `ahgThemeB5Plugin/templates/`. Templates begin with `<?php decorate_with('layout_1col'); ?>` and contribute three slots: `title`, `before-content`, `content`. The theme bundle (`/dist/js/ahgThemeB5Plugin.bundle.*.js`) injects the navbar, footer, voice-commands modal, clipboard normalizer, and the BS5 CSS. No manual `<head>` boilerplate is required.
- Action class names must be `<module>Actions` (no `ar` prefix). E.g. `authorityResolutionActions extends sfActions`. The module is auto-discovered once enabled in the plugin configuration's `initialize()` via `sfConfig::set('sf_enabled_modules', ...)`.
- Service-class loading uses **explicit `require_once`** at the top of each method — Symfony 1.4 has no PSR-4 autoloader for the plugin's `AtomFramework\Services\AuthorityResolution\` tree. Same pattern as the Symfony 1.4 tasks under `lib/task/`.
- Action vars exposed to the template are picked up automatically (`$this->mention = ...`) — no `$sf_data->getRaw(...)` ceremony needed for objects, but it's a defensive pattern used in other AHG plugin templates and we follow it for primitive arrays.
- The candidate "view authority" link routes to `/<slug>` (AtoM's canonical actor URL via `QubitMetadataRoute` in `apps/qubit/config/routing.yml`), not `@authority_show` — that route doesn't exist. We do a `LEFT JOIN slug` in the candidate query and degrade to a plain "authority #N" label when the actor has no slug row.
- Place-coordinate resolution probes `term_i18n.description` for a `lat,lng` pattern. AtoM has no canonical term-level geocoord column; many places resolve to `null` and the map preview is skipped. UI still renders cleanly.

## ACL

Reused the existing AtoM credential check (`$this->context->user->isAuthenticated()` and `hasCredential('editor')` / `isAdministrator()`) — same pattern as `ahgAuthorityPlugin`'s `requireAuth() / requireEditor()`. No new ACL system. Per-action defaults:

- Index, review, lookup: `requireAuth()`
- All POST decision handlers: `requireEditor()`

## Demo routes hit

Logged in as the admin user (`/index.php/user/login/`) and:

```
GET  /admin/authorityResolution            -> 200, 134 KB, queue page shows 1,011 pending + 1 linked
GET  /admin/authorityResolution/138/review -> 200, 88 KB, three-region screen for "Frederick Douglass"
GET  /admin/authorityResolution/lookup?q=Douglass&type=PERSON
     -> 200 application/json
     -> {"results":[{"source":"mysql_actor","authority_id":902224,"fuseki_uri":null,"display_name":"Frederick Douglass"}]}
POST /admin/authorityResolution/159/link   candidate_id=8 format=json
     -> 200 application/json
     -> {"ok":true,"decision_id":4,"state":"linked","provenance":{"ok":true,"graph":"urn:atom:auth-res:graph:decisions","status":204},...}
```

Post-state on mention 159 (PERSON `Lloyd`):

- `ahg_mention.state` flipped `pending -> linked`.
- `ahg_ner_entity.linked_actor_id` set to `902263`.
- `ahg_mention_decision` row #4 created with `decision_type=link`, `chosen_candidate_id=8`, `chosen_authority_id=902263`, `original_system_top_score=1.0000`, `fuseki_graph_uri=urn:atom:auth-res:graph:decisions`.
- `ahg_mention_decision.candidates_visible_snapshot` froze both visible candidates with rank + composite_score.
- `ahg_mention_decision.evidence_snapshot` froze the chosen candidate's `evidence_signals` + `evidence_data`.
- Fuseki `urn:atom:auth-res:graph:decisions` graph: SELECT COUNT(*) now returns 45 triples (was 42 before the new decision).

`php symfony auth-res:write-provenance <decision_id> --show` re-emits the SPARQL UPDATE and confirms HTTP 204 status from Fuseki. Idempotent on the audit row.

## HTML snippet from `/admin/authorityResolution/138/review`

```html
<body class="d-flex flex-column min-vh-100 authorityResolution review show-edit-tooltips">
  ...
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><i class="fas fa-quote-left me-1"></i>Source mention</strong>
      <small class="text-muted">#138</small>
    </div>
    <div class="card-body">
      <h4 class="mb-2">Frederick Douglass</h4>
      <p class="small mb-2"><strong>Source:</strong>
        <a href="/ai-test-19" target="_blank" rel="noopener">AI Test 19 ...</a></p>
      <p class="small mb-2 text-muted">NER confidence: 1.000</p>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong><i class="fas fa-stream me-1"></i>Context window</strong></div>
    <div class="card-body">
      <div class="bg-light p-2 rounded small" style="font-family: monospace; line-height: 1.5;">
        <span class="text-muted">...ke Lloyd's of London and the Royal Society of Sciences ...</span>
        <mark class="bg-warning"><strong>Frederick Douglass</strong></mark>
        <span class="text-muted">, Mark Twain, and Thomas Edison...</span>
      </div>
      ...Offset: 730-748   Paragraph: 12-846...
    </div>
  </div>
```

## Known gaps

- **Task 6 (create-new sub-workflow) is stubbed.** The `createNew` action records a `create_new` decision row (so the audit trail is complete and the mention exits the queue) but does not yet build the EAC-CPF actor row. The form on the right column says "(Task 6 stub)" so reviewers don't mistake it for a finished feature.
- **Place coordinates resolve sparingly.** AtoM's place terms typically don't carry lat/lng; we probe `term_i18n.description` for `lat,lng`. The Leaflet preview only loads if at least one candidate resolves coordinates. The CDN-loaded Leaflet is conditional so non-PLACE reviews don't pay the bytes.
- **ACL is coarse.** `editor` credential + admin is sufficient for these actions. If granular `AclService::check('authorityResolution.decide')` lands later, it slots into `requireEditor()`.
- **Park follow-up signal.** `ahg_mention_park.new_candidate_available` is surfaced in the UI but the re-scan job that flips it (Task 7) lives elsewhere.

## How to test locally

```bash
# Pre-state
mysql archive -e "SELECT id, state FROM ahg_mention WHERE id IN (82, 138, 159);"

# Render the queue or a review page (admin login required)
curl -ks -L -c /tmp/c -b /tmp/c -H 'Host: psis.theahg.co.za' \
  https://localhost/admin/authorityResolution/138/review | grep -c "Ranked candidates"

# JSON typeahead
curl -ks -L -c /tmp/c -b /tmp/c -H 'Host: psis.theahg.co.za' \
  "https://localhost/admin/authorityResolution/lookup?q=Douglass&type=PERSON"

# Link decision via HTTP (server replies JSON when format=json or X-Requested-With set)
curl -ks -c /tmp/c -b /tmp/c -H 'Host: psis.theahg.co.za' -H 'X-Requested-With: XMLHttpRequest' \
  -d 'candidate_id=N&format=json' -X POST \
  https://localhost/admin/authorityResolution/<mention_id>/link

# Re-emit RDF-Star for any committed decision
php symfony auth-res:write-provenance <decision_id> --show
```

## Files

- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/config/ahgAuthorityResolutionPluginConfiguration.class.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/lib/Services/DecisionRecorder.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/actions/actions.class.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/templates/indexSuccess.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/templates/reviewSuccess.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/templates/_candidateCard.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/templates/_evidenceRow.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/templates/_linkDifferentModal.php`
- `/usr/share/nginx/archive/atom-ahg-plugins/ahgAuthorityResolutionPlugin/modules/authorityResolution/templates/_parkModal.php`
