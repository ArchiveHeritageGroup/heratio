# Researcher Download & Storage Quotas

Heratio can cap how much each researcher downloads and stores in the research
portal, so reading-room and storage limits are enforced automatically.

## What is limited

- **Downloads** - the number of download/reproduction events a researcher may
  make in a period (per calendar month, or all-time total).
- **Storage** - the total size of files a researcher uploads to their
  workspaces.

Each limit can be left unlimited.

## How limits are resolved

A researcher's effective limit is taken from the most specific policy that
applies, per metric: a **per-researcher** policy overrides a **per-project** one,
which overrides a **per researcher-type (role)** one, which overrides the
**global default**. A partial override (for example, a bigger storage cap for
one researcher) inherits the other limits from the broader policy.

## Soft warning and hard block

Each policy has a soft-warning threshold (default 80%). Below it, actions
proceed normally. At or above it, the researcher sees a warning but can still
continue. Once the limit is reached, the action (download or upload) is blocked
with a clear message.

## Managing quotas (administrators)

Open **Research › Administration › Quotas** (`/research/quotas`). The page shows:

- **Usage vs limit** - one row per researcher with download and storage usage
  bars, coloured as usage approaches and exceeds the limit.
- **Quota policies** - create, edit, or delete policies. Choose the scope
  (global / role / researcher / project) and period from the Dropdown Manager
  lists, and set the download count, storage size, and soft-warning percentage.

A global default of 100 downloads per month and 5 GiB of storage ships out of
the box; tune it or add overrides to suit your reading-room policy.
