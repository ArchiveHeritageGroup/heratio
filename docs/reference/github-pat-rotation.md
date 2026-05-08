# GitHub PAT rotation on the Heratio box

**Canonical layout** (post-2026-05-08 cleanup): the GitHub Personal Access Token used by `git push` lives in **one** place, `~/.git-credentials`, and is consumed via the `store` credential helper. Origin URLs in every clone are now plain `https://github.com/<org>/<repo>.git` - no embedded user:pass. Rotating the PAT is therefore a single-line edit. This doc captures the layout and the procedure so the next rotation does not turn into a multi-file scavenger hunt.

## Layout

| Where | What | Format |
|---|---|---|
| `~/.git-credentials` | The PAT | `https://johanpiet2:<token>@github.com` (one line, mode 0600) |
| `~/.gitconfig` global | Helper wiring | `credential.helper = store` |
| Each repo's `.git/config` | `origin` remote | `https://github.com/ArchiveHeritageGroup/<repo>.git` (no creds) |
| `~/.config/gh/hosts.yml` | `gh` CLI auth | Separate `gho_…` OAuth token, **not** the PAT |

`gh` CLI uses its own OAuth token (issued by `gh auth login`, not by the GitHub Settings → Tokens page). Rotating the PAT does not touch `gh`, and rotating the `gh` OAuth token does not touch git push. Don't conflate them.

## Active git repos that consume the PAT

As of 2026-05-08, three local repos push to GitHub via HTTPS and therefore depend on `~/.git-credentials`:

- `/usr/share/nginx/heratio` → `ArchiveHeritageGroup/heratio`
- `/usr/share/nginx/ahg-ai-workbench` → `ArchiveHeritageGroup/ahg-ai-workbench`
- `/usr/share/nginx/archive/atom-ahg-plugins` → `ArchiveHeritageGroup/atom-ahg-plugins`

If a fourth HTTPS-cloned repo is added later, it will pick up the same credentials automatically - no per-repo wiring needed.

## Rotation procedure (one-liner)

1. On github.com, go to Settings → Developer settings → Personal access tokens → Tokens (classic), regenerate the token. Copy the new value.
2. On this box:

```bash
printf 'https://johanpiet2:NEW_TOKEN@github.com\n' > ~/.git-credentials
chmod 600 ~/.git-credentials
```

3. Verify against any of the three repos:

```bash
git -C /usr/share/nginx/heratio ls-remote origin HEAD
```

A successful response (`<sha>\tHEAD`) means the new token is live. No remote-URL edits, no per-repo work.

## If URL-embedding has crept back in

Origin URLs of the form `https://johanpiet2:ghp_…@github.com/...` are the failure mode this layout exists to avoid: rotating the PAT then requires editing every such `.git/config` *plus* `~/.git-credentials`, and missing one means silent breakage on the next push from that clone.

To detect:

```bash
grep -rl --include='config' '@github.com' /root /usr/share/nginx 2>/dev/null \
  | xargs grep -l 'ghp_'
```

To fix per repo:

```bash
git -C <repo> remote set-url origin "https://github.com/<org>/<repo>.git"
```

## Hygiene: residual token records

When rotating, also consider that the *previous* token may persist in:

- Claude Code session logs at `/root/.claude/history.jsonl` and `/root/.claude/projects/*/*.jsonl` - local-only, never pushed, but plain-text on disk.
- Bash history (`/root/.bash_history`, `/root/.zsh_history`) if the token was ever pasted on the command line.

These are not active credentials once the token is regenerated, but they leave a paper trail. Strip lines containing the dead token with:

```bash
sed -i '/<old-token-prefix>/d' /root/.claude/history.jsonl
```

(Make a `.bak` copy first; the file is JSONL, so deleting whole lines is safe.)

## Background: why the layout exists

Pre-2026-05-08, the PAT was duplicated across `~/.git-credentials` *and* every clone's `origin` URL. That meant a rotation in May 2026 required four file edits - easy to miss one, and the missed clone would only fail on the next push (often days later, in a different session, with a misleading "Authentication failed" message). Converting the origins to plain URLs and centralising on the credential helper is what made rotation a one-step operation.
