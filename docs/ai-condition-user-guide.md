# ahgAiConditionPlugin - AI Condition Assessment

The **ahgAiConditionPlugin** runs **automatic condition assessments** on digital images of archival material (photographs, manuscripts, books, art on paper) and writes the results back as a structured **Spectrum 5.1 condition report**.

It is the AI counterpart to the manual condition workflow in `ahgConditionPlugin`. Where the manual flow asks an archivist to fill a form, the AI flow looks at the image, generates a draft assessment (foxing, tears, fading, mould, damp, support deformation, ink loss, …), assigns severity grades, and pre-populates the form so the archivist only has to **review and accept**.

---

## What it does

| Capability | Detail |
| --- | --- |
| Visual triage | Scans every image attached to an information object and grades it on the Spectrum **Stable / Fair / Poor / Unstable** scale |
| Defect detection | Identifies common defects (foxing, tears, water damage, fading, support loss, biological attack, etc.) by region |
| Heatmap overlay | Renders a per-defect mask over the image so the conservator can visually verify |
| Severity score | Assigns a 0-100 severity based on defect coverage, defect type, and image area affected |
| Recommended action | Suggests a treatment from the conservation_action taxonomy (digitise → freeze → consult conservator) |
| Draft report | Writes a `condition_report` row in **draft** status; the archivist accepts/rejects before it goes live |

The plugin **does not auto-publish**. Every AI-generated report waits for human sign-off - by design. The audit log records who accepted/rejected each draft.

---

## Where it lives

| Surface | URL |
| --- | --- |
| Per-IO trigger | "Run AI condition scan" button in the IO show-page **AI Tools** sidebar (`/<slug>` → AI Tools panel) |
| Bulk run dashboard | `/admin/ai/condition` |
| Per-report show | `/<slug>/condition/<report-id>` |
| Settings | `/admin/settings/ahg/ai-condition` |

The plugin is gated behind `ahgAIPlugin` and `ahgConditionPlugin` - both must be enabled. AI Tools sidebar disappears when the parent AI plugin is off.

---

## Settings

| Key | Meaning | Default |
| --- | --- | --- |
| `ai_condition_model` | Vision-LM model used for triage. Local: `llava:13b` recommended for archival images | `llava:13b` |
| `ai_condition_endpoint` | Ollama URL - local AI server. See server 192.168.0.78 setup notes | `http://192.168.0.78:11434` |
| `ai_condition_min_resolution_px` | Skip images smaller than this (a thumbnail can't be assessed) | `1024` |
| `ai_condition_severity_threshold` | Below this score, no condition_report is written (image is healthy) | `15` |
| `ai_condition_auto_run_on_ingest` | If true, every newly-ingested IO with an attached image gets queued automatically | `false` |
| `ai_condition_concurrency` | Parallel jobs run by the queue worker | `2` |
| `ai_condition_taxonomies` | Which Spectrum taxonomies feed the picklists (defects, treatments, urgency) | `condition_defect, conservation_action, urgency` |

Auto-run is disabled by default - running vision-LM inference on every new image is expensive. Turn it on once you've sized your queue + GPU.

---

## Common workflows

### Manual scan of a single record

1. Open the IO show page.
2. Right sidebar → **AI Tools** → **Run condition scan**.
3. The job is queued; progress is shown inline (5-30 s for a typical photo on the local server). When done, the **Condition** sidebar gets a new draft report card.
4. Click into the draft → review the heatmap overlay, edit defect descriptions, accept or reject.
5. Accepting flips the report's status to **published** and writes the timeline event.

### Batch scan a fonds

1. `/admin/ai/condition` → **New batch**.
2. Pick the parent IO (fonds or series). The wizard counts descendants with attached images.
3. Confirm. A background job queues one per descendant; the dashboard shows ETA + per-report progress.
4. As each report drafts, it appears in the dashboard "Pending review" tab. Reviewer accepts/rejects with the keyboard.

### Auto-run on ingest

1. `/admin/settings/ahg/ai-condition` → enable **Auto-run on ingest**.
2. Save. Every new IO with an attached image will trigger the scan as part of the post-ingest derivative-generation chain.
3. Reviewers work through the **Pending review** queue at their own pace.

---

## Permissions

| Action | Required role |
| --- | --- |
| View an AI-generated report (published) | Anonymous |
| View drafts | Editor / Conservator |
| Run a manual scan | Editor (`acl:create`) |
| Configure batch / auto-run | Admin |
| Accept / publish a draft | Conservator (`acl:condition.publish`) |
| Reject a draft | Conservator |
| Edit settings | Admin |

The Conservator role is configured at `/aclGroup` - by default, Conservators can publish condition reports but can't edit IO descriptions outside the condition sidebar.

---

## How it works (operational notes)

1. **Image fetch** - The job fetches the highest-resolution `digital_object` for the IO (avoiding thumbnails).
2. **Tiling** - Large TIFFs are tiled to 1024×1024 windows to fit the model's input.
3. **Inference** - Each tile is sent to the vision-LM endpoint with a structured prompt that asks for defects, regions, and severity. The response is JSON.
4. **Aggregation** - Per-tile findings are merged; bounding boxes are mapped back to the original image coordinate space.
5. **Persistence** - A `condition_report` row is created in `draft` status. The heatmap is written as a derivative under `{storage_path}/uploads/r/<repo>/condition/<report-id>/heatmap.png`.
6. **Audit** - `ahg_audit_log` row recorded with the action `ai-condition.draft`, including model name and version.

Failures (network, model timeout, malformed JSON) leave the report in `failed` status with the error captured in `condition_report.last_error`. Re-run from the dashboard.

---

## Quality + bias notes

- The vision-LM is **not a substitute for a trained conservator**. Treat its output as a triage signal, not a final assessment.
- Severity grades are calibrated against a sample of South-African archival material - the model may under- or over-call defects on materials it hasn't seen (e.g. parchment, daguerreotypes). Spot-check + retrain if you switch material classes.
- The model can produce confident-sounding wrong answers ("foxing" labelled on a stain that's actually printer ink). The mandatory human review catches these - never auto-publish.

---

## Common gotchas

- **No GPU = it crawls.** CPU-only inference takes ~2-5 minutes per image. Wire up a GPU box (RTX 3070+ recommended) and point `ai_condition_endpoint` at it. See the server 192.168.0.78 setup notes.
- **Thumbnails are useless.** If the only digital object is a 500-px reference image, the model has nothing to work with. The plugin skips anything below `ai_condition_min_resolution_px`.
- **Heatmap not appearing?** Check `{storage_path}/uploads/r/<repo>/condition/<id>/` - if the file is there but not visible, your nginx alias for `/uploads/` might be missing for that repo. The plugin doesn't write its own static-file route.
- **Spectrum vs AI defect taxonomies.** The plugin ships its own `condition_defect_ai` taxonomy that is a *superset* of the manual `condition_defect` one. When publishing, it maps AI labels back to the manual taxonomy - if a label has no mapping, it lands as a free-text note.
- **Auto-run + bulk ingest = job storm.** Turning auto-run on while a 50,000-row CSV ingest runs will queue 50,000 vision-LM jobs. Set `ai_condition_concurrency` low or run the ingest with auto-run off, then a separate batch afterwards.

---

## Related

- **`ahgConditionPlugin`** - manual Spectrum condition reports; the AI plugin writes into the same data model.
- **`ahgAIPlugin`** - parent plugin; provides the model endpoint, OCR, NER, summary, translate.
- **`ahgPreservationPlugin`** - OAIS preservation - a Poor/Unstable AI verdict can auto-create a preservation event.
- **`ahgSpectrumPlugin`** - Spectrum 5.1 procedures; condition assessment is one of the 21 Spectrum procedures.
- **Help articles**: *AI Tools - Overview*, *Condition Reports - Manual Workflow*
