# MzansiLM routing for South African-language translation

Heratio's general-purpose model (qwen3:8b) has near-zero African-language
pretraining and produces Dutch-flavoured or hallucinated text for the SA
languages. Issue #128 adds an operator-controlled routing layer so SA-language
translation can be sent to **MzansiLM-125M** - a decoder-only Llama
(Apache-2.0) purpose-trained on the MzansiText corpus covering the 11 official
SA languages.

This is the **Option B routing code** from issue #128. It is inert by default:
nothing changes until an operator deploys MzansiLM and enables it.

## What the routing does

`LlmService::translate($text, $targetLang)` checks, before its normal MT / LLM
dispatch, whether the target locale should go to MzansiLM:

- MzansiLM must be enabled (`mzansilm_enabled`).
- The target locale (or its base subtag, e.g. `zu` from `zu_ZA`) must be in
  `mzansilm_locales`.
- A `mzansilm_endpoint` must be configured.

When all three hold, the translation is POSTed to the MzansiLM endpoint in
OpenAI `/chat/completions` shape. If MzansiLM is not enabled, not configured for
the locale, or the call fails, `translate()` falls through to the existing
machine-translation / LLM path - so enabling MzansiLM never removes a
capability, it only upgrades the SA-language slice.

## Settings

Stored in `ahg_ner_settings`, read via `AhgAiServices\Support\AiServicesSettings`:

| key | default | meaning |
|---|---|---|
| `mzansilm_enabled` | `false` | master gate for MzansiLM routing |
| `mzansilm_endpoint` | (unset) | OpenAI-shape base URL, e.g. `http://gpu-host:8000/v1` |
| `mzansilm_model` | `mzansilm-125m` | model name requested from the endpoint |
| `mzansilm_locales` | `zu,xh,nso,st,ss,ts,tn,ve,nr,nd` | locales routed to MzansiLM |
| `mzansilm_timeout` | `60` | request timeout in seconds |

Afrikaans (`af`) is deliberately excluded from the default `mzansilm_locales` -
the Afrikaans catalogue is operator-maintained and needs no AI replay.

## Languages dropdown

`Northern Ndebele` (`nd`) was added to `TranslationController::TARGET_LANGUAGES`
so all ten MzansiLM-priority languages are selectable in the translation
language dropdown and on the language-management page. The other nine
(zu, xh, nso, st, ss, ts, tn, ve, nr) were already present.

## Deploying the model (operator)

Issue #128 lists two runtimes. Either exposes an OpenAI-shape endpoint that
`mzansilm_endpoint` points at:

- **vLLM** - `vllm serve anrilombard/mzansilm-125m --port 8000`; set
  `mzansilm_endpoint = http://<gpu-host>:8000/v1`.
- **Ollama** - convert the HF safetensors to GGUF, `ollama create mzansilm-125m`;
  set `mzansilm_endpoint` to the Ollama host's OpenAI-compatible `/v1` path.

MzansiLM-125M is tiny (125M params) and fits any of the GPU hosts. After
deploying, set `mzansilm_enabled = 1` and smoke-test translation quality on
isiZulu / isiXhosa / Sepedi / Sesotho before relying on it - it is a research
baseline with no published benchmarks.

## Translation routing and the AI gateway

Translation does not call a model host directly - it goes through the **AHG AI
gateway** (`https://ai.theahg.co.za/ai/v1/...`), the same consolidation HTR
received in #131. Both the live `mt.endpoint` setting and the hardcoded
fallback default in `TranslationController` are the gateway's `/ai/v1/translate`
endpoint, so translation traffic is authenticated and audited alongside the
other AI services whether or not the setting is present.

(Earlier the hardcoded fallback was a local `192.168.0.112:5004` MT adapter,
and a stale `ahg_ner_settings.mt.endpoint` row pointed at `192.168.0.115:5004`
- both removed under #128 so the gateway is the only translation path.)

MzansiLM, once deployed, is reached through the operator-set `mzansilm_endpoint`
- which can itself be a gateway route or a direct vLLM / Ollama endpoint.

## Not in this change

- **Model provisioning and the SA-language quality evaluation** (issue #128
  Tier 1) - operator/ops work; needs a GPU host and a native-speaker quality
  call, neither of which the routing code can perform.
- **AI Services settings-form fields** for the `mzansilm_*` keys - that form is
  a change-locked blade; the settings work from `ahg_ner_settings` until a
  field is added.
- **AtoM-AHG parity** - the AtoM `ahgAIPlugin` translate path is not yet
  routed; sensible to wire once MzansiLM is proven on the Heratio side.
- **Option C** (re-embedding the SA-language Qdrant slice with a MzansiLM
  tokenizer) - a separate, larger piece of work.
