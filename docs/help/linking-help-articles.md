> Heratio Help Center article. Category: Help Center.

# Linking help articles

Related articles can be cross-linked so each shows the other under **"Related articles."** Links are **bidirectional** — you add a link once and it appears on both articles.

## The easy way — the link manager (admin)

On any article page, admins see a **Links** button (next to "Copy link") at the top. It opens the link manager:

1. **Add a linked article** — start typing a title in the search box (or paste an article's `/help/article/…` URL), then click **Add & save**.
2. The article appears in the **Linked articles** list below.
3. **Repeat** to add more.
4. Remove a link with the **✕** button next to it.

That's it — no files, no commands. The link shows on both articles immediately.

Each article also has a **Copy link** field at the top to grab its shareable URL (`/help/article/{slug}`).

## The markdown way (authors)

When writing an article's markdown, a link to another article is also picked up automatically:

```markdown
See also [Advanced search](/help/article/advanced-search-user-guide)
```

On the next `php artisan ahg:help-ingest-all` it becomes a bidirectional related link. Manual links added in the admin UI are kept separate and are **not** overwritten by re-ingest.

## Related articles

- [Install Heratio & onboard an existing AtoM database](/help/article/install-and-atom-db-onboarding)
