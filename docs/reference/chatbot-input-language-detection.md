# Chatbot: reply in the input message language (#1275)

The AI Library Assistant (ahg-ai-chatbot) can optionally reply in the language a
message was **typed in**, rather than only the UI locale set for the session. This
is a follow-up to #1273 (which localizes replies to `app()->getLocale()`).

## How it works

- A local classifier, `AhgCore\Services\InputLanguageDetector`, inspects the incoming
  message and returns a language code for the official South African languages
  (af, zu, xh, nso, st, tn, ts, ve, ss, nr) or `null`.
- It runs entirely in-process: **no network call, and no LLM / qwen "what language is
  this" prompt**. It is a function-word / strong-marker heuristic.
- When detection is confident, `ChatbotService::dispatch()` uses the detected language
  as the reply locale; that locale is only ever passed to the sanctioned MT route
  (`AhgCore\Services\AnswerLocalizer` -> gateway `/ai/v1/translate`). Generation stays
  English; only the presented reply is translated.

## Compliance (hard rules)

- SA-language output only ever comes from the MT route, never a qwen LLM
  (`feedback_no_qwen_for_af`). A wrong detection cannot produce qwen output:
  `AnswerLocalizer` fails soft to English on any MT miss.
- Detection is conservative and English-biased. It returns `null` (keeping the
  UI-locale / English default) for English input, very short input, or an ambiguous
  lone word. Shared greetings (e.g. "dumela", "sawubona") are weak signals on their
  own, so they fall back to the default rather than guess between sister languages.

## Configuration

Opt-in, default **off**, so the #1273 UI-locale behaviour is unchanged unless enabled:

```
# .env
AHG_CHATBOT_REPLY_IN_INPUT_LANGUAGE=true
```

Config key: `ahg-ai-chatbot.reply_in_input_language` (bool).

## Operator self-check

Verify detection offline, with no gateway / LLM calls:

```
php artisan ahg:chatbot-test-multilang --detect
```

This prints a pass/fail table mapping canonical phrases to their detected locale.
The existing `ahg:chatbot-test-multilang` (without `--detect`) still runs the live
multi-language reply regression via the gateway.
