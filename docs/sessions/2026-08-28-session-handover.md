# Session handover - 25 to 28 August 2026 (v1.154.674 - v1.154.685)

Written for whoever picks this up next. The per-release notes in this directory
carry *what* changed; this carries *why*, what I got wrong, and what is still
open. Where the two disagree, trust the code.

## Where things stand

All three instances on **v1.154.685**, all healthy.

```
heratio-dev   1.154.685
heratio       1.154.685   heratio.org               200
sasa          1.154.685   sasaarchive.theahg.co.za  200
atom          served from the `atom` VM (192.168.0.122:8088), NOT /usr/share/nginx/atom
```

**v1.154.683 was another session's** (a CLI password-hash fix). My deploys
carried it to both instances - correct, but not mine and not verified by me.

## The through-line: things that were never wired

Most of this session was one defect class - **a view or page bound to something
its controller never produces, failing silently into an empty state rather than
an error**. It is the #1478 shape and it is still turning up:

- `MuseumController::provenance()` was `$provenanceChain = collect();`. The page
  returned 200 and said "No provenance data." forever, while `provenance_entry`
  held real chains keyed on those very objects. Fixed v1.154.684.
- `objectComparison()` and `qualityDashboard()` in the same controller, same
  shape. The dashboard reported a permanent **0%** regardless of the catalogue -
  worse than showing nothing, because it asserts a measurement nobody took.
  Fixed v1.154.685; real answer on dev is 66 records, 47%, grade D.
- `InboxService` stored `source_url` and nothing ever fetched it (#1492).

**If you are looking for work, grep for `= collect();` and `= 0;` in controller
methods that return a view.** That is where the remaining ones will be.

## What I got wrong, so you do not repeat it

1. **I told a peer session Heratio had a working provenance display.** Half
   right: the information-object one is real, the museum one I pointed at was a
   stub. archive-33 was closer to right than my correction allowed. Both of us
   were confidently wrong about the same feature from opposite directions.
2. **I claimed `[ test ] && action` would abort under `set -euo pipefail`** and
   wrote that into a script comment. It does not - a failing command is exempt
   when it is not the final command of an `&&` list. Verified with a five-line
   script only AFTER writing the wrong explanation into code.
3. **I called the mysqldump failures "transient"** for months on no evidence.
   They were deterministic - see below.
4. **I sized the VM backup rotation from `du`**, which reports allocated size.
   archivematica is 300 GiB allocated but 25.9 GiB real, so a run I budgeted at
   3.5 hours took 17 minutes.
5. **I introduced a bug and caught it in test**: my provenance due-diligence card
   rendered for objects with no concerns, because `'none'` - the vocabulary's
   no-concern default - is a non-empty string.

## The rc=3 mystery, solved (#1491)

Four monthly failures said only "rc=3". v1.154.677 preserved the stderr that had
been discarded since June; the fifth failure named it in one line:

```
Error 1412: Table definition has changed ... when dumping `display_facet_cache`
```

**It was never transient.** 24 Aug died at 5,192,913,641 bytes and 26 Aug at
5,192,911,527 - within 2 KB, the same point every time. The facet-cache cron
fired at `01:00:01`, the same second the dump started.

Fixed both ways (v1.154.682): the cache table's DATA is excluded with its
STRUCTURE appended as a second gzip member (`--ignore-table` drops the
`CREATE TABLE` too, and a restore would otherwise lack a table the app expects),
and the four facet crons moved to `0,4-23`.

**Still unexplained:** `RefreshFacetCacheCommand` does DELETE+INSERT - DML, not
DDL - so how it raises a *table definition* error is unknown. The collision is
proven by timing and the repeated failure point; the mechanism is not. If 1412
appears against a different table, start there.

**Do not deploy during the 01:00 window.** The second attempt that night failed
differently and it was self-inflicted: deploying v1.154.679 at 01:33 altered
`research_workspace_file` mid-dump.

## Infrastructure now in place

- **VM backups exist at all** (#1486 item 4, v1.154.676). Nothing backed up any
  VM image before - no snapshots, no ZFS, no copy of any qcow2. Weekly rotation
  at 22:00, live via libvirt push-mode; guests stay running. Two clean nights so
  far. Saturday is Mogalakwena (~1 TB) - the first genuinely large run.
- **Host memory**: swap 2 GB -> 33 GB, swappiness 60 -> 10, InnoDB pool 96 GB ->
  48 GB, `MemoryHigh=64G` on mysql, qemu `oom_score` ~680 -> ~145. Committed
  memory 168 GB -> 120 GB of 251 GB. The 23 Aug OOM killed mysqld and stalled a
  guest into a 33.5-hour outage; the host now absorbs that rather than losing
  processes to it.
- **`bin/release` signals `queue:restart`** (v1.154.681). Dev's worker had been
  serving jobs from two-day-old code, silently, because deploy targets get
  restarted and the release SOURCE never did.
- **Anything below `git push` in `bin/release` is dead code on dev** - the push
  always fails and `set -e` exits there. Two separate steps have now had to move
  above it.

## Open, with my read on each

- **#1491** - fixed but open until several clean nights pass. Title is stale.
- **#1471** - CI never runs migrations. **Cost me work three times this week**;
  most recently my `museum_metadata_i18n` join threw in the test DB. Highest
  value item on the board.
- **#1490 / #1405** - yours, not a session's. KM has burned Heratio's gateway key
  since 31 May while its own key sits unused.
- **#1488, #1489** - small, self-contained, specced.
- **#1479 / #1480** - NER pair. **#1480 was filed on a false premise** and needs
  re-reading before anyone acts on it.
- **i18n cluster (#1410, #1416, #1419, #1444, #1445)** - 16-22 days untouched
  and the largest block of actual product work. Two of the five need people
  rather than code.

## Cross-session

`archive-33` diagnoses and hands over rather than editing Heratio. A rule about
that was relayed on 27 Aug and **retracted on 28 Aug** - it is not in force. The
handover convention stands by agreement, not by rule. The boundary that does
hold: nothing that was blocked in another session gets routed here to be run.
