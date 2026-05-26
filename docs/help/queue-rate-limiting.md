> Heratio Help Center article. Category: System Administration.

# Queue Rate Limiting and Job Uniqueness

## What this is

Heratio runs slow tasks (HTR transcription, AI extraction, full-text reindex, finding-aid export, email and SMS fan-out) on a background worker so the web request can return quickly. Those tasks are called "queued jobs". This article explains the two controls you can apply to queued jobs to keep the system healthy:

- **Rate limiting** - cap how often a particular job is allowed to run (e.g. "no more than 10 HTR jobs per minute").
- **Uniqueness** - stop the same job from running twice in parallel or being queued twice at the same time.

These controls protect upstream services (the AI gateway, the email relay, Elasticsearch) from being overwhelmed, and they prevent duplicate work when a user clicks Save twice in quick succession.

## Why it matters to you as an administrator

- The AI gateway is shared across Heratio, KM, and other AHG products. If Heratio fires 200 HTR requests in a burst, the gateway can throttle Heratio for the next hour. Setting a per-minute cap prevents that.
- A user who clicks "Reindex" three times in five seconds should not trigger three reindex jobs. Uniqueness debounces those clicks to one job.
- A second worker that picks up a duplicate job copy should not run it again while the first copy is still finishing. The uniqueness lock catches that case.

## Settings you can change

The per-minute caps live in `config/queue.php` under the `rate_limits` section. Every cap is also overridable via an environment variable in `.env`, so you can tune values on a single host without editing code or redeploying.

| Setting | Env var | Default | What it caps |
|---|---|---|---|
| `htr_extract` | `QUEUE_RATE_LIMIT_HTR_EXTRACT` | 10 | Handwritten text recognition jobs per minute. |
| `llm_complete` | `QUEUE_RATE_LIMIT_LLM_COMPLETE` | 60 | General LLM completions per minute. |
| `ner_extract` | `QUEUE_RATE_LIMIT_NER_EXTRACT` | 30 | Named-entity recognition jobs per minute. |
| `summarize` | `QUEUE_RATE_LIMIT_SUMMARIZE` | 30 | Summarization jobs per minute. |
| `translate` | `QUEUE_RATE_LIMIT_TRANSLATE` | 30 | Translation jobs per minute. |
| `email_send` | `QUEUE_RATE_LIMIT_EMAIL_SEND` | 100 | Outbound email per minute. |
| `sms_send` | `QUEUE_RATE_LIMIT_SMS_SEND` | 30 | Outbound SMS per minute. |
| `webhook_dispatch` | `QUEUE_RATE_LIMIT_WEBHOOK` | 60 | Outbound webhooks per minute. |
| `es_reindex` | `QUEUE_RATE_LIMIT_ES_REINDEX` | 120 | Elasticsearch reindex jobs per minute. |
| `thumbnail_gen` | `QUEUE_RATE_LIMIT_THUMBNAIL` | 60 | Image thumbnail generation jobs per minute. |

After changing an env var, restart the worker so it picks up the new value:

```
sudo supervisorctl restart heratio-queue-worker:*
# or, if you deployed with systemd:
sudo systemctl restart 'heratio-queue-worker@*'
```

## How to know it is working

Each control logs a line to the worker log (`/var/log/heratio-queue-worker-00.log` by default) when it fires:

- `queue.rate_limited.release` - a job hit its cap and was put back in the queue to try again later. Look for `retry_after_seconds` in the log line.
- `queue.unique.duplicate_swallowed` - a second worker picked up a duplicate copy of a job that was already running, and dropped it.
- `queue.dispatch.duplicate_dropped` - a user or controller called for the same job twice in a row, and the second call was debounced before it ever reached the queue.

A sudden spike in `queue.rate_limited.release` is your signal to raise the corresponding cap; a steady trickle is healthy and means the limit is doing its job.

## Prerequisites

Rate limiting and uniqueness both rely on a shared, atomic cache so that all your worker processes see the same counter and the same lock. Heratio uses the `database` cache driver by default, which works out of the box.

If you have switched to the `file` cache driver, switch back to `database` (in `config/cache.php` or via the `CACHE_STORE` env var) before turning on rate limits, otherwise each worker process counts in its own file and the caps will not be respected.

`redis` is also supported and is the recommended driver for installations running more than four worker processes.

## What still needs a developer

Today the controls are opt-in per Job class - a developer adds a one-line trait or middleware entry to each Job that should be capped or deduped. The full retrofit across every existing Job is tracked in GitHub Issue #672 (Phase 3). The defaults above will start to apply automatically as Jobs are migrated.

## Related

- `docs/queue-worker-deployment.md` - Phase 1 worker daemon setup (supervisord and systemd).
- `docs/reference/queue-phase-2-rate-uniqueness.md` - full developer reference for the four opt-in patterns.
- GitHub Issue #672 - queue and background-jobs roadmap.
