# ahgFunctionManagePlugin — Functions (ISDF)

The **ahgFunctionManagePlugin** manages **function records** — the activities, business processes, mandates, or programmes that produce archival material. They are described per **ISDF** (International Standard for Describing Functions).

Functions sit alongside actors and information objects in the archival description trinity:

```
Actor (who)  →  Function (what they did)  →  Information object (the record they left)
```

Documenting functions matters because the same body restructures over time, the same activity passes between bodies, and the records make sense only if the activity that produced them is described separately and persistently.

---

## What it does

| Capability | Detail |
| --- | --- |
| Browse | `/function/browse` — paged, faceted by classification (function / activity / process / transaction) |
| Show | `/function/<slug>` — full ISDF detail with related actors and records |
| Add | `/function/add` — create a new function record (Editor) |
| Edit | `/function/<slug>/edit` — update fields per ISDF |
| Move (reparent) | `/function/<slug>/move` — change parent in the function hierarchy |
| Merge | `/admin/function/merge` — collapse duplicates |
| Autocomplete | `/function/autocomplete` — JSON used by IO edit and actor edit pages |

---

## ISDF field set

The edit form mirrors the four ISDF areas:

- **5.1 Identity area** — type (function / activity / process / transaction), authorized form of name, parallel forms, other forms, classification
- **5.2 Context area** — dates of the function, description, history, legislation
- **5.3 Relationships area** — related functions (broader / narrower / temporal predecessor / successor), related actors (executes), related records (output of)
- **5.4 Control area** — function description identifier, institution responsible, rules, status (draft / published), level of detail, dates of description, language, sources, maintenance notes

Multilingual: every text field has per-culture rows in `function_i18n`.

---

## Function types

The classification picklist (taxonomy id `92`) ships with four ISDF-standard types:

| Type | Meaning | Example |
| --- | --- | --- |
| **Function** | A high-level area of responsibility | "Higher Education", "Public Health" |
| **Activity** | A coherent set of tasks within a function | "Curriculum development", "Disease surveillance" |
| **Process** | A workflow with input + output | "Accreditation review", "Vaccination campaign" |
| **Transaction** | A single discrete event | "Issued degree no 1965-001" |

Functions form a tree: a Function contains Activities; an Activity contains Processes; a Process leaves a trail of Transactions.

---

## Common workflows

### Document a new function

1. `/function/add`.
2. Pick **Type = Function**, fill **Authorized form of name**, **Dates** (when the function existed — e.g. "1923-1994" for a programme that's since ended).
3. **History** — narrative of what the function was, the legal basis, how it changed over time.
4. **Legislation** — link to the act / policy / mandate that authorised the function.
5. Save. The function gets a slug like `national-vaccination-programme`.

### Link an actor to a function (who *executes* the function)

1. Open the actor show page (`/actor/<slug>`).
2. **Functions** sidebar → **Add link**.
3. Pick the function, pick the relationship type (`executes`, `responsible-for`, `participated-in`).
4. Save. The link is stored in the `relation` table; the inverse appears on the function's show page automatically.

### Link records to a function (what the function *produced*)

1. Open the IO edit page.
2. **Context** area → **Function** field → autocomplete to the function record.
3. Save. The IO's metadata now carries `creating_function_id`. The function's show page lists the record under "Records produced".

### Merge duplicates

Same as actors and terms — `/admin/function/merge`, pick survivor + doomed, review every link, confirm.

---

## Settings

The plugin shares the authority-record settings page (`/admin/settings/ahg/authority`) — completeness scoring, NER pipeline, merge auto-pre-select threshold all apply to functions too.

Function-specific:

- **Auto-create from NER** — when on, the NER pipeline (in `ahgAIPlugin`) extracts function names from IO scope-and-content fields and offers to link them to existing function records or create new ones.
- **Show in GLAM browse facets** — surface "Function" as a sidebar facet on `/glam/browse`. Default off (functions are usually a researcher-tool view, not a casual-browse axis).

---

## Permissions

| Action | Required role |
| --- | --- |
| Browse, view (published) | Anonymous |
| Browse, view (drafts) | Editor / Admin |
| Add, edit, move | Editor (`acl:create`, `acl:update`) |
| Merge | Admin |
| Delete | Admin (refused if any IO references the function) |

---

## Why bother with functions?

Two motivating examples:

**1. Records survive, bodies change.**
The records of "South African Department of Education" predate the department itself. The records were produced by various legacy bodies (Cape Education Department, Transvaal Education Department, etc.) — each of which executed the *same function* (basic-education provision) under different actors. Indexing on function lets researchers find all "basic education provision" records regardless of which body owned them at the time.

**2. Bodies execute many functions.**
A single state archive holds records of finance, health, defence, justice, and education. The same actor (the state) executes many functions. Function records let researchers slice the catalogue by activity, not just by depositor.

Without function records, the only way to express "this is about education" is to drop the term into a subject keyword — which loses the ISDF-standard structure (dates, mandates, hierarchy).

---

## Common gotchas

- **Functions vs subjects.** A subject term ("Education") is what the record is *about*. A function ("Provision of basic education") is what produced the record. Same word, different model. Don't tag both unless you mean both.
- **MPTT integrity** — like terms and information objects, function tree uses `lft`/`rgt`. Don't `DELETE` directly; use the controller or `php artisan ahg:nested-set-rebuild --table=function`.
- **Dates of existence** — these are the dates the *function* existed, not the records. A 1990-1994 programme can have records dated 1995 (a final report) and that's fine.
- **Slug stability** — renaming the authorized form of name does not rename the URL slug. Use `/function/<slug>/rename` (admin only) explicitly.
- **Deleting a function with records attached** is refused at the controller. Reassign or merge first.

---

## Related

- **`ahgActorManagePlugin`** — actors *execute* functions
- **`ahgInformationObjectManagePlugin`** — records are the *output* of functions
- **`ahgRicExplorerPlugin`** — RiC graph: actor ↔ function ↔ record edges
- **`ahgTermTaxonomyPlugin`** — the function-classification picklist is a taxonomy
- **Help articles**: *Records in Contexts (RiC) Overview*, *AHG Authority Records — User Guide*
