> Heratio Help Center article. Category: User Guide.

# Article Comments - Anonymous Commenting and Moderation

**Version:** 1.0
**Date:** 2026-05-30
**Area:** Articles / Blog

---

## 1. Overview

Every published article (Articles / blog, at `/articles/{slug}`) carries a
blog-style comment section. Comments are **anonymous** - visitors do not need to
log in or register. A name is optional; when left blank the comment is shown as
"Anonymous". Comments appear immediately, and administrators can moderate them
after the fact.

## 2. How visitors comment

At the bottom of any published article there is a "Comments" section that lists
existing comments and a "Leave a comment" form. A visitor:

1. Optionally types a name.
2. Types the comment (up to 4000 characters).
3. Clicks "Post comment".

The comment appears straight away under the article.

## 3. Spam and abuse protection

Because commenting is open to anyone, several guards run automatically:

- **Honeypot field** - a hidden field that real people never see. Automated bots
  that fill it have their submission silently discarded.
- **Per-IP flood window** - the same IP address must wait a short interval
  between comments.
- **Rate limit** - the comment endpoint is throttled per IP.
- **Length cap** - comments are limited to 4000 characters.

These keep the form open and frictionless for genuine readers while blocking the
most common automated abuse.

## 4. Moderating comments (administrators)

Administrators have a moderation screen at **Admin -> Manage Articles ->
Comments** (`/admin/articles/comments`). It lists every comment, newest first,
with the article it belongs to, the author (or "Anonymous"), the source IP, the
comment text, its status, and when it was posted.

For each comment you can:

- **Approve** - make the comment visible (the default state for new comments).
- **Spam** - hide the comment from the public article.
- **Delete** - remove the comment permanently.

A comment's status is one of `approved` (visible), `pending` (held, not shown),
or `spam` (hidden). New comments default to `approved` so the section behaves
like a normal blog; switch a comment to `spam` or delete it to remove unwanted
content.

## 5. Where the comments live

Comments are stored in the `blog_comment` table, linked to the article in
`blog_post`. Deleting an article removes its comments automatically.
