# SA-language translation on the AHG AI gateway - change spec

For the gateway team (`/opt/ahg-ai/gateway`). Covers heratio#1419 (the translate route serves only af, en and a shared `bnt` model) and heratio#1444 (Tshivenda and isiNdebele). Written 21 November 2026 from live probes through `https://ai.theahg.co.za/ai/v1/translate` and a read-only look at the model files on disk. The gateway source itself was not readable from the Heratio session (`/opt/ahg-ai/gateway` is `ahgai`-only), so the route internals below are inferred from its OpenAPI document and its answers.

Heratio is ready for this change and does not need another release once it lands. Its clients send the contract codes in section 2, read `GET /ai/v1/translate/languages` to decide what to ask for, and refuse an SA language cleanly when the gateway cannot serve it.

## 1. What the gateway does today

Measured 21 November 2026, input "Download the report", `source=en`:

| Target | HTTP | Output | Verdict |
| --- | --- | --- | --- |
| af | 200 | aflaai die verslag | Works (lower-case, but usable) |
| zu | 200 | Layisha ungenise umbiko | Usable, from the shared `bnt` model |
| xh | 200 | Layishela phantsi egronjiweyo | Doubtful, `bnt` model |
| ts | 200 | Kopa xiviko lexi | Usable, `bnt` model |
| st | 200 | Kopa iyi ngxelo | Wrong - same sentence as tn and ss, half of it Xhosa |
| tn | 200 | Kopa iyi ngxelo | Wrong - same as st |
| ss | 200 | Kopa iyi ngxelo | Wrong - same as st |
| nso | 500 | `TRANSLATION_FAILED`, "No translator available for en -> nso" | No model |
| ve | 500 | `TRANSLATION_FAILED`, "No translator available for en -> ve" | No model |
| nr | 500 | `TRANSLATION_FAILED`, "No translator available for en -> nr" | No model |

: Live gateway translate probes, 21 November 2026

`GET /ai/v1/translate/languages` returned `af`, `bnt`, `en`, with `ct2_pairs` `bnt->en`, `af->en`, `en->af`, `en->bnt` and `model: opus-mt-ct2`. Every SA language other than af is routed to the one `en->bnt` Bantu-family model, which has no way of knowing which language was asked for. That is why st, tn and ss come back identical.

Two other facts matter for the choice of model.

The NLLB-200 tokenizer has no code for Tshivenda or isiNdebele. Searching the on-disk tokenizer (`/opt/ahg-ai/models/nllb-200`, the distilled 600M) finds `zul_Latn`, `xho_Latn`, `nso_Latn`, `tsn_Latn`, `sot_Latn`, `tso_Latn`, `ssw_Latn` and `afr_Latn`, and finds neither `ven_Latn` nor `nbl_Latn` (nor `nde_Latn`). The 1.3B and 3.3B checkpoints share that 202-language vocabulary. This closes the question in heratio#1444: no NLLB size can translate ve or nr. The garbage the 1.3B produced on .76 was the model being forced to an unknown token, not fp16 trouble.

NLLB-200 is licensed CC-BY-NC-4.0 at every size (Hugging Face model card tags, checked 21 November 2026). The header of `/opt/ahg-ai/translation/server.py` already records that NLLB was dropped for that reason. Any NLLB route the gateway exposes for paying clients needs an explicit licence decision from Dr Johan Pieterse first. The recommendation below avoids NLLB.

## 2. The contract Heratio uses

Route: `POST /ai/v1/translate`, Bearer key with the `gateway` scope, as today.

Request body, unchanged field names:

```json
{"text": "Download the report", "source": "en", "target": "zu"}
```

`source` and `target` are the short codes below. Heratio normalises everything it holds (`zu_ZA`, `zul`, `zul_Latn`, `nbl`) to these before it calls, and never sends a model-specific code. The gateway maps them to whatever its models need.

| Language | Contract code | FLORES-200 code | NLLB-200 | Recommended model |
| --- | --- | --- | --- | --- |
| English | en | eng_Latn | yes | - |
| Afrikaans | af | afr_Latn | yes | opus-mt en-af (live today) |
| isiZulu | zu | zul_Latn | yes | MADLAD-400-3B (no opus-mt en-zu model exists) |
| isiXhosa | xh | xho_Latn | yes | opus-mt en-xh |
| Sepedi | nso | nso_Latn | yes | opus-mt en-nso |
| Setswana | tn | tsn_Latn | yes | opus-mt en-tn |
| Sesotho | st | sot_Latn | yes | opus-mt en-st |
| Xitsonga | ts | tso_Latn | yes | opus-mt en-ts |
| siSwati | ss | ssw_Latn | yes | opus-mt en-ss |
| Tshivenda | ve | none | **no** | MADLAD-400-3B, after native review |
| isiNdebele | nr | none | **no** | MADLAD-400-3B, after native review |

: Contract codes and model per SA official language

isiNdebele (`nr`, South Ndebele) must never be mapped to Northern Ndebele (`nd`/`nde`). They are different languages, and the Zimbabwean one is what NLLB's neighbourhood would suggest.

Model availability was checked against the Hugging Face API on 21 November 2026. `Helsinki-NLP/opus-mt-en-{af,xh,nso,tn,st,ss,ts}` exist and are Apache-2.0. `opus-mt-en-zu`, `opus-mt-en-ve` and `opus-mt-en-nr` do not exist. Reverse models `opus-mt-{xh,nso,tn,st,ss,ts,ve}-en` exist, which covers SA-language to English for everything except zu and nr. `google/madlad400-3b-mt` (Apache-2.0, 2.94B parameters) lists af, zu, xh, nso, tn, st, ss, ts, ve and nr among its languages; its quality on ve and nr has not been measured by anyone at AHG yet, so treat it as a candidate until the review in section 5.

Successful response, unchanged:

```json
{"success": true, "translated": "...", "model": "opus-mt-en-tn", "source_lang": "en", "target_lang": "tn"}
```

Please put the real model name per language in `model`; Heratio records it in the provenance log.

Unsupported pair - the one change of shape we ask for:

```
HTTP 422
{"code": "UNSUPPORTED_LANGUAGE", "error": "No translator available for en -> nr"}
```

Today's `500 TRANSLATION_FAILED` with the same message is still recognised by Heratio, so this is a clean-up, not a blocker. A 500 should mean the model broke, not that the language was never offered.

Language list - `GET /ai/v1/translate/languages` must list each language it really serves, one code each, and stop listing `bnt`:

```json
{"languages": [
  {"code": "af", "name": "Afrikaans", "model": "opus-mt-en-af"},
  {"code": "zu", "name": "isiZulu", "model": "madlad400-3b-mt"},
  {"code": "tn", "name": "Setswana", "model": "opus-mt-en-tn"}
]}
```

Heratio reads this list (cached ten minutes) and only asks for what is on it. While `bnt` is still listed, Heratio counts it as covering zu, xh, ts and sn only, the four it was measured to handle, and refuses st, tn, ss, nso, ve and nr without calling. As soon as the list names languages individually, that stop-gap stops mattering.

## 3. Route changes, in order

1. **Remove the `bnt` fallback** for any target that is not literally `bnt`. A target with no dedicated model gets the 422 above. This alone stops the wrong st/tn/ss output and needs no new model.
2. **Add the six opus-mt models** en-xh, en-nso, en-tn, en-st, en-ss, en-ts (CTranslate2 int8, same pipeline as the en-af model already live), and the reverse models for SA-to-English. These run on CPU.
3. **Add MADLAD-400-3B** for zu, ve and nr (and as a second opinion for the others) on a GPU node, routed by the gateway's node selection. MADLAD takes the target as a prefix token (`<2zu> Download the report`), so the gateway adds that, not Heratio.
4. **List the codes** in `/translate/languages` as each model goes live.
5. `/ai/v1/translate/batch` gets the same mapping and the same 422 per item.

## 4. Nodes and memory

| Model | Size | Memory | Where |
| --- | --- | --- | --- |
| opus-mt (Marian, ~75M params), per pair, CT2 int8 | ~80-300 MB each | ~2-3 GB RAM for all pairs together | CPU on the gateway host, any hour |
| MADLAD-400-3B, bf16 | 2.94B params, ~5.9 GB weights | ~7-8 GB VRAM with working memory | .115 (RTX 3080, 10 GB) - fits |
| MADLAD-400-3B, CT2 int8 | ~3 GB | ~4 GB VRAM, or CPU | .78 (RTX 3070, 8 GB), or CPU at night |
| MADLAD-400-7B, bf16 | 8.3B params, ~16.6 GB weights | ~18-20 GB VRAM | .76 (RTX 3090, 24 GB) only; .76 is down today |
| NLLB-200 1.3B / 3.3B | - | - | Not recommended: no ve/nr, non-commercial licence |

: Memory per candidate model and the node it fits

Use bf16 or int8 for MADLAD, never fp16: it is a T5-family model, and T5 models are known to overflow in fp16. The RTX 3080 and 3070 both support bf16.

The standing GPU rule applies: GPU services run 06:00-18:00 only, and only Dr Johan Pieterse starts them. Outside those hours the gateway should either serve MADLAD on CPU (int8, slow but correct) or answer zu/ve/nr with a 503 so Heratio keeps the original text. Never fall back to `bnt` or to a general LLM; Heratio's own rule is that SA-language output never comes from qwen.

Per the GPU node policy, prefer .76 when it is back, .115 and .78 for this 3B model meanwhile.

## 5. Test plan

**Gateway, before release.** Run each line through the public route with a real key, not against the node.

1. `GET /ai/v1/translate/languages` lists af, xh, nso, tn, st, ts, ss individually and no `bnt`. zu, ve and nr appear only once MADLAD is live.
2. For each listed target, `POST /ai/v1/translate` with "Download the report" returns 200 and a non-empty `translated`.
3. st, tn and ss return three **different** strings. This is the regression test for the `bnt` bug.
4. No output equals the English input (the pass-through failure seen with the old LoRA adapter).
5. An unlisted target (try `nd` and `xx`) returns 422 `UNSUPPORTED_LANGUAGE`, not 200 and not 500.
6. `source=tn, target=en` returns English (reverse models).
7. Night check after 18:00: zu/ve/nr either answer from CPU or return 503, never `bnt` output.

Reference outputs from the base NLLB-600M on the same sentence (heratio#1419, 2 November) give the reviewer a sense of what correct looks like: af "Laai die verslag af", zu "Landa umbiko", xh "Khuphela ingxelo", ss "Titsatsele umbiko", st "Khoasolla tlaleho", tn "Itseele pego", ts "Kopa xiviko".

**Native review before ve and nr are listed.** Twenty archival phrases (button labels plus two sentences of scope-and-content), MADLAD output, one reviewer per language, scored usable / fixable / wrong. List ve or nr only when most rows are usable or fixable. If MADLAD fails the review, keep ve and nr unlisted; Heratio then keeps them as human-translation languages, which is what the picker already says.

**Heratio, after the gateway release.** Nothing to deploy. On dev:

```
DB_DATABASE=heratio_test DB_USERNAME=heratio_test DB_PASSWORD='H3ratio_Test!Local' php artisan test tests/Feature/SaTranslationGatewayTest.php
```

then translate one record field into tn from the record's Translate panel and confirm a draft appears with the new model name in its provenance.

## 6. Heratio side, for reference

- `packages/ahg-ai-services/src/Support/TranslationLanguages.php` holds the code table, the normaliser, the cached language-list read and the "unsupported" detector.
- `LlmService::translate()`, `AiController::translate()`, `TranslationController::translateText()` / `stringsMtSuggest()` and `AhgCore\Services\AnswerLocalizer` use it. An SA language the gateway cannot serve returns the original text or a clear "not available yet" message; it never goes to the general LLM.
- `LlmService::translate()` used to post to `.../ai/v1/translate/translate`, so every content translation 404'd at the gateway and went to the LLM. That is fixed in the same change.
