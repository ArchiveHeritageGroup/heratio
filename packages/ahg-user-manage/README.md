# ahg-user-manage

User browse and management for Heratio - admin-side user CRUD, self-service profile, password reset, per-entity ACL editor, and plugin-grant capability layer (heratio#52).

## Purpose

- Admin user CRUD (`/admin/users`, `/user/add`, `/user/{slug}/edit`)
- Self-service profile + password change + clipboard
- Per-user plugin grants (admin sets capability) AND per-user plugin preferences (user toggles their own nav clutter)
- Per-entity ACL matrices: IO, Actor, Repository, Term (`/user/{slug}/indexInformationObjectAcl` and friends)
- Registration approve / reject workflow for new sign-ups
- Pending-registration queue

## Install

Auto-discovered. The ServiceProvider registers routes (`web` middleware) and the `ahg-user-manage` view namespace. The `user` / `user_i18n` tables are part of the AtoM-compatible core schema; this package only owns its views and ACL pivots.

## Routes (highlights)

Admin (`admin` middleware):

- `GET /admin/users` (also `/user/list`, `/user/browse`)
- `GET|POST /user/add`
- `GET|POST /user/{slug}/edit`
- `GET|DELETE /user/{slug}/delete`
- `GET /user/{slug}/indexInformationObjectAcl` (and Actor / Repository / Term variants)
- `GET|POST /user/{slug}/editInformationObjectAcl` (and variants)
- `GET /user/{slug}/plugins` - per-user capability grants
- `GET /user/registration/pending` plus `approve` / `reject`

Self-service (`auth` middleware):

- `GET /user/profile` / `/user/profile/edit`
- `GET /user/passwordEdit`
- `GET|POST /user/passwordReset`
- `GET /user/clipboard`
- `GET|POST /user/profile/plugins` - per-user nav preferences

Public:

- `GET|POST /user/register` / `GET /user/verify/{token}`

## Key classes

| Class | Role |
|---|---|
| `Controllers\UserController` | Browse, CRUD, profile, registration |
| `Controllers\UserAclController` | Per-user ACL matrix (`indexInformationObjectAcl` etc., heratio#52) |
| `Services\UserService` | User CRUD + email verification |
| `Services\UserBrowseService` | BrowseService subclass for the admin list |

## Notes

- All slug-based routes carry regex exclusions (`profile`, `password`, `register`, `clipboard`, etc.) so literal paths are not interpreted as usernames.
- The researcher-ACL editor (`editResearcherAcl`) is still a stub - separate concept, out of #52 scope, see route comment.
- `UserAclController` mirrors the group-ACL editor pattern from `/admin/acl/group/{id}/{tab}`.
