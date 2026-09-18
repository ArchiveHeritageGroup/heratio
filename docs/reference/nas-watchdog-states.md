# NAS watchdog states and why "slow" is no longer "down"

`ahg:nas-watchdog` probes the storage mount every five minutes and notifies on
state transitions. As of this change it reports three states, not two.

## The states

| State | Meaning | Rings the bell? |
| --- | --- | --- |
| `up` | Mounted, readable, canary sub-tree present, and it answered quickly. | No (under `--quiet-ok`) |
| `degraded` | It is answering, but slowly, or a check ran out of time. The NAS is there. | No. Logged at warning level, visible in the log and on /admin/health. |
| `down` | A check definitively failed, the path is not in `/proc/mounts`, or it has been `degraded` for N consecutive ticks. | Yes |

## What was wrong before

The old probe carried a `PROBE_TIMEOUT` of 5 seconds and a comment calling it a
"hard wall-clock cap". It capped nothing. The deadline was only tested *before*
each call, and `is_dir()`, `is_readable()` and the canary each block inside the
kernel for as long as the filesystem takes. Checking the clock around a call
that has already blocked cannot shorten it. That is how probes of 5,293 ms,
29,291 ms and 91,552 ms were recorded against a five-second cap.

The second half was worse. When the deadline had passed, the remaining checks
were skipped and their results left at their initialised `false` - and the state
rule was `mountpoint && readable && archive_subdir ? up : down`. So "ran out of
time" and "the NAS is gone" produced the same value, and a slow-but-alive mount
could not report anything except DOWN. Three calls were raised this way
(CH-000058, CH-000067, CH-000077) against a mount that answered normally a
minute later.

## How it is bounded now

`AhgCore\Support\BoundedFsCheck` runs each filesystem test in a child process
and abandons it at the deadline. The child is deliberately not waited on after
being killed, because a process stuck in an uninterruptible NFS syscall does not
die even on SIGKILL, and waiting for it would reintroduce the unbounded block.
The orphan is reparented to init and reaped when the mount frees.

The checks are tri-state: `true`, `false`, or `null` for "no answer in time".
Only `false` - the filesystem answered, and the answer was no - reports `down`.
`null` reports `degraded`.

The `/proc/mounts` check stays as it was. procfs answers from memory, so unlike
a stat of the mount itself it cannot block, and it remains authoritative: a path
that is not mounted is `down` immediately, with no waiting.

## Escalation, so a hung mount still alerts

A mount that is hung rather than merely slow would otherwise sit at `degraded`
for ever and never reach anyone. After `nas_watchdog_degraded_escalate`
consecutive degraded ticks (default 3, so a quarter of an hour at the scheduled
interval) the state escalates to `down` and notifies. The streak resets on the
first healthy tick. Set the value to 0 to disable escalation.

## Configuration

| Key | Env var | Default |
| --- | --- | --- |
| `heratio.nas_watchdog_enabled` | `NAS_WATCHDOG_ENABLED` | `true` |
| `heratio.nas_watchdog_timeout` | `NAS_WATCHDOG_TIMEOUT` | `5` seconds, shared across both blocking checks |
| `heratio.nas_watchdog_slow_ms` | `NAS_WATCHDOG_SLOW_MS` | `2000` ms before `up` becomes `degraded` |
| `heratio.nas_watchdog_degraded_escalate` | `NAS_WATCHDOG_DEGRADED_ESCALATE` | `3` ticks |

## Checking it

`tests/Unit/BoundedFsCheckTest.php` covers the part that matters: a process that
will not finish is abandoned at the deadline and reports `null`, never `false`.
It uses `sleep` rather than requiring a hung NFS mount to hand.
