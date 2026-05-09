# Registry admin: moderation handler port spec

**SUPERSEDED 2026-05-08.** This file targets heratio's `packages/ahg-registry/`. Per the user's directive that "registry will in future be 100% divorced from heratio/archive. fully stand alone", all new registry moderation work targets the standalone repo at `/usr/share/nginx/registry/` instead. The active port spec is `/usr/share/nginx/registry/docs/reference/registry-admin-moderation-port-spec.md`. The content below is retained as historical reference; do not act on it.

**Status (2026-05-08):** spec only. No code written. Authored after the live audit and PSIS source diff in `registry-admin-institutions-audit-handoff.md`. Authoritative reference is PSIS at `/usr/share/nginx/archive/atom-ahg-plugins/ahgRegistryPlugin/modules/registry/actions/actions.class.php` (per `feedback_psis_authoritative_until_100`).

## Why this spec exists

Phase C-3 ported the registry admin VIEWS but not their POST handlers. Live audit on 2026-05-08 confirmed:

- All 9 admin browse pages (`/registry/admin/{institutions,vendors,software,standards,dropdowns,blog,discussions,reviews,newsletters}`) render at HTTP 200.
- All 9 admin pages have ZERO POST routes registered.
- `RegistryController` has no `*Verify` / `*Moderate` / `*Delete` methods.
- `RegistryService` has only browse/get methods. No `verify`, `update`, `delete`, `toggleFeatured`, `toggleVisibility`, `lock`, `pin`, `publish`, `archive`, `togglePinned`.
- The locked admin blade views in `packages/ahg-registry/resources/views/admin/*.blade.php` only render 1-2 row controls each. PSIS templates render up to 6.
- No GitHub issue tracks this gap. Needs a fresh issue when the user is ready.

DB columns required for all moderation actions already exist (`is_verified`, `is_featured`, `is_active`, `is_visible`, `verified_at`, `verified_by`, `status` enum, `is_pinned`, `is_locked`, `published_at`, `archived_at` per per-table audit).

## Scope

Port from PSIS to Heratio:

| Area | What to port | Lines in PSIS |
|---|---|---|
| Routes | 10 new POST routes in admin group | n/a (Heratio routes/web.php) |
| Controller | 9 new handler methods | actions.class.php:3675-4535 |
| Service | ~25 helper methods on RegistryService (or new dedicated services) | psis InstitutionService / VendorService / SoftwareService / UserGroupService / DiscussionService / BlogService / ReviewService |
| Views | Add row-action button blocks to 7 of 9 admin blades | locked, needs `./bin/unlock` per `feedback_lock_all_pages` |

Out of scope for this spec: `adminGroupVerify`, the `adminInstitutionUsers` POST sub-actions (link/delink/set-primary/update-role), and the `adminDropdownDelete` standalone route. Those are listed at the end as future work; the primary spec covers the 7 list-page moderation flows that surfaced in the audit.

## 1. Route additions

Add to `packages/ahg-registry/routes/web.php` inside the existing `Route::prefix('registry/admin')->name('registry.admin.')->middleware('admin')->group(...)` block (file ends at line 192):

```php
// Moderation (POST handlers, mirror PSIS form_action discriminator pattern)
Route::post('/institution/verify',  [RegistryController::class, 'adminInstitutionVerify'])->name('institutionVerify');
Route::post('/vendor/verify',       [RegistryController::class, 'adminVendorVerify'])->name('vendorVerify');
Route::post('/software/verify',     [RegistryController::class, 'adminSoftwareVerify'])->name('softwareVerify');
Route::post('/standard/delete',     [RegistryController::class, 'adminStandardDelete'])->name('standardDelete');
Route::post('/dropdown/delete',     [RegistryController::class, 'adminDropdownDelete'])->name('dropdownDelete');
Route::post('/blog/moderate',       [RegistryController::class, 'adminBlogModerate'])->name('blogModerate');
Route::post('/discussion/moderate', [RegistryController::class, 'adminDiscussionModerate'])->name('discussionModerate');
Route::post('/review/moderate',     [RegistryController::class, 'adminReviewModerate'])->name('reviewModerate');
// Newsletters has no PSIS form_action — basic CRUD already lives at adminNewsletterForm
```

Naming choice: separate POST route per entity, single endpoint per entity that switches on `form_action`. Mirrors PSIS exactly. CSRF token is required (Laravel default for POST). The `admin` middleware already guards the prefix, so no extra check needed at the route level.

## 2. Controller method specs

All methods on `AhgRegistry\Controllers\RegistryController` (live file in docker overlay; canonical NAS path is `/mnt/nas/heratio/heratio/packages/ahg-registry/src/Controllers/RegistryController.php`). All require `$this->service` (the existing `RegistryService` injection) plus admin-user lookup.

### 2.1 `adminInstitutionVerify(Request $request): RedirectResponse`

PSIS: `executeAdminInstitutionVerify` actions.class.php:3675-3704

```php
public function adminInstitutionVerify(Request $request): RedirectResponse
{
    $id = (int) $request->input('id');
    $action = (string) $request->input('form_action', 'verify');
    $notes = trim((string) $request->input('notes', ''));
    $userId = auth()->id();

    match ($action) {
        'verify'   => $this->service->verifyInstitution($id, $userId, $notes),
        'unverify' => $this->service->updateInstitution($id, ['is_verified' => 0, 'verified_at' => null, 'verified_by' => null]),
        'feature'  => $this->service->toggleInstitutionFeatured($id),
        'suspend'  => $this->service->updateInstitution($id, ['is_active' => 0]),
        'activate' => $this->service->updateInstitution($id, ['is_active' => 1]),
        'delete'   => $this->service->deleteInstitution($id),
        default    => null,
    };

    return redirect()->route('registry.admin.institutions');
}
```

### 2.2 `adminVendorVerify(Request $request): RedirectResponse`

PSIS: actions.class.php:3818-3846. Same shape as 2.1, swap `Institution` for `Vendor` everywhere; redirect target `registry.admin.vendors`.

### 2.3 `adminSoftwareVerify(Request $request): RedirectResponse`

PSIS: actions.class.php:3865-3893. Same shape as 2.1, swap to `Software`; redirect to `registry.admin.software`. Template only emits 4 of the 6 actions (verify/unverify/feature/delete) but the handler should accept all 6 for forward compatibility.

### 2.4 `adminStandardDelete(Request $request): RedirectResponse`

PSIS template adminStandardsSuccess.php has only `delete` form_action.

```php
public function adminStandardDelete(Request $request): RedirectResponse
{
    $id = (int) $request->input('id');
    $this->service->deleteStandard($id);
    return redirect()->route('registry.admin.standards');
}
```

### 2.5 `adminDropdownDelete(Request $request): RedirectResponse`

PSIS: actions.class.php:4518-4535. Captures the `dropdown_group` for redirect-with-anchor.

```php
public function adminDropdownDelete(Request $request): RedirectResponse
{
    $id = (int) $request->input('id');
    $group = $this->service->deleteDropdown($id);  // returns dropdown_group string for redirect
    return redirect()->route('registry.admin.dropdowns', $group ? ['group' => $group] : []);
}
```

### 2.6 `adminBlogModerate(Request $request): RedirectResponse`

PSIS: actions.class.php:4341-4358. Actions: `publish`, `archive`, `feature`, `pin`.

```php
public function adminBlogModerate(Request $request): RedirectResponse
{
    $postId = (int) $request->input('post_id');
    $action = (string) $request->input('form_action', '');

    match ($action) {
        'publish' => $this->service->publishBlogPost($postId),
        'archive' => $this->service->archiveBlogPost($postId),
        'feature' => $this->service->toggleBlogFeatured($postId),
        'pin'     => $this->service->toggleBlogPinned($postId),
        default   => null,
    };

    return redirect()->route('registry.admin.blog');
}
```

### 2.7 `adminDiscussionModerate(Request $request): RedirectResponse`

PSIS: actions.class.php:4302-4322. Actions: `hide`, `spam`, `activate`, `lock`, `pin`.

```php
public function adminDiscussionModerate(Request $request): RedirectResponse
{
    $discId = (int) $request->input('discussion_id');
    $action = (string) $request->input('form_action', '');

    match ($action) {
        'hide'     => $this->service->updateDiscussion($discId, ['status' => 'hidden']),
        'spam'     => $this->service->updateDiscussion($discId, ['status' => 'spam']),
        'activate' => $this->service->updateDiscussion($discId, ['status' => 'active']),
        'lock'     => $this->service->lockDiscussion($discId),
        'pin'      => $this->service->pinDiscussion($discId),
        default    => null,
    };

    return redirect()->route('registry.admin.discussions');
}
```

### 2.8 `adminReviewModerate(Request $request): RedirectResponse`

PSIS: actions.class.php:4388-4404. Actions: `toggle_visibility`, `approve`, `delete`. PSIS preserves the `filter` query param on redirect.

```php
public function adminReviewModerate(Request $request): RedirectResponse
{
    $reviewId = (int) $request->input('review_id');
    $action = (string) $request->input('form_action', '');
    $filter = $request->input('filter', 'pending');

    match ($action) {
        'toggle_visibility' => $this->service->toggleReviewVisibility($reviewId),
        'approve'           => $this->service->updateReview($reviewId, ['is_visible' => 1]),
        'delete'            => $this->service->deleteReview($reviewId),
        default             => null,
    };

    return redirect()->route('registry.admin.reviews', ['filter' => $filter]);
}
```

## 3. RegistryService method specs

Existing service is one fat class (`browseInstitutions`, `getInstitution`, etc.). PSIS uses 7 separate services (`InstitutionService`, `VendorService`, `SoftwareService`, `UserGroupService`, `DiscussionService`, `BlogService`, `ReviewService`). For Heratio, two design choices:

**Option A (recommended): keep one fat service.** Add the methods below to `RegistryService` directly. Matches the established Heratio pattern (`adminBrowseInstitutions` etc. all live there now) and minimises refactor blast radius.

**Option B: split into per-entity services.** Closer to PSIS source, supports DI per entity, but requires moving existing browse methods. Larger change, only worth it if the user has plans for those services elsewhere.

The spec assumes Option A. All methods read/write directly through the existing schema and use `Illuminate\Support\Facades\DB` (consistent with current service code).

### 3.1 Institution methods

```php
public function verifyInstitution(int $id, int $userId, string $notes = ''): void
{
    DB::table('registry_institution')->where('id', $id)->update([
        'is_verified'        => 1,
        'verified_at'        => now(),
        'verified_by'        => $userId,
        'verification_notes' => $notes ?: null,
        'updated_at'         => now(),
    ]);
}

public function updateInstitution(int $id, array $fields): void
{
    $fields['updated_at'] = now();
    DB::table('registry_institution')->where('id', $id)->update($fields);
}

public function toggleInstitutionFeatured(int $id): void
{
    $row = DB::table('registry_institution')->where('id', $id)->first(['is_featured']);
    if ($row) {
        DB::table('registry_institution')->where('id', $id)->update([
            'is_featured' => $row->is_featured ? 0 : 1,
            'updated_at'  => now(),
        ]);
    }
}

public function deleteInstitution(int $id): void
{
    DB::table('registry_institution')->where('id', $id)->delete();
}
```

### 3.2 Vendor methods

`verifyVendor`, `updateVendor`, `toggleVendorFeatured`, `deleteVendor` — same shape as 3.1, table `registry_vendor`.

### 3.3 Software methods

`verifySoftware`, `updateSoftware`, `toggleSoftwareFeatured`, `deleteSoftware` — same shape as 3.1, table `registry_software`.

### 3.4 Standard methods

```php
public function deleteStandard(int $id): void
{
    DB::table('registry_standard')->where('id', $id)->delete();
}
```

### 3.5 Dropdown methods

```php
public function deleteDropdown(int $id): ?string
{
    $row = DB::table('registry_dropdown')->where('id', $id)->first(['dropdown_group']);
    DB::table('registry_dropdown')->where('id', $id)->delete();
    return $row->dropdown_group ?? null;
}
```

Note: also remove i18n labels in `registry_dropdown_i18n` if cascade is not set up. Verify against migration v1.47.38 (commit `3505af53`) which created the i18n table with `FK CASCADE`. If FK cascade exists, no extra cleanup needed. If not, add `DB::table('registry_dropdown_i18n')->where('parent_id', $id)->delete();` first.

### 3.6 Blog methods

```php
public function publishBlogPost(int $postId): void
{
    DB::table('registry_blog_post')->where('id', $postId)->update([
        'status'       => 'published',
        'published_at' => now(),
        'updated_at'   => now(),
    ]);
}

public function archiveBlogPost(int $postId): void
{
    DB::table('registry_blog_post')->where('id', $postId)->update([
        'status'      => 'archived',
        'archived_at' => now(),
        'updated_at'  => now(),
    ]);
}

public function toggleBlogFeatured(int $postId): void
{
    $row = DB::table('registry_blog_post')->where('id', $postId)->first(['is_featured']);
    if ($row) {
        DB::table('registry_blog_post')->where('id', $postId)->update([
            'is_featured' => $row->is_featured ? 0 : 1,
            'updated_at'  => now(),
        ]);
    }
}

public function toggleBlogPinned(int $postId): void
{
    $row = DB::table('registry_blog_post')->where('id', $postId)->first(['is_pinned']);
    if ($row) {
        DB::table('registry_blog_post')->where('id', $postId)->update([
            'is_pinned'  => $row->is_pinned ? 0 : 1,
            'updated_at' => now(),
        ]);
    }
}
```

### 3.7 Discussion methods

```php
public function updateDiscussion(int $id, array $fields): void
{
    $fields['updated_at'] = now();
    DB::table('registry_discussion')->where('id', $id)->update($fields);
}

public function lockDiscussion(int $id): void
{
    $row = DB::table('registry_discussion')->where('id', $id)->first(['is_locked']);
    if ($row) {
        DB::table('registry_discussion')->where('id', $id)->update([
            'is_locked'  => $row->is_locked ? 0 : 1,
            'updated_at' => now(),
        ]);
    }
}

public function pinDiscussion(int $id): void
{
    $row = DB::table('registry_discussion')->where('id', $id)->first(['is_pinned']);
    if ($row) {
        DB::table('registry_discussion')->where('id', $id)->update([
            'is_pinned'  => $row->is_pinned ? 0 : 1,
            'updated_at' => now(),
        ]);
    }
}
```

### 3.8 Review methods

```php
public function toggleReviewVisibility(int $reviewId): void
{
    $row = DB::table('registry_review')->where('id', $reviewId)->first(['is_visible']);
    if ($row) {
        DB::table('registry_review')->where('id', $reviewId)->update([
            'is_visible' => $row->is_visible ? 0 : 1,
            'updated_at' => now(),
        ]);
    }
}

public function updateReview(int $reviewId, array $fields): void
{
    $fields['updated_at'] = now();
    DB::table('registry_review')->where('id', $reviewId)->update($fields);
}

public function deleteReview(int $reviewId): void
{
    DB::table('registry_review')->where('id', $reviewId)->delete();
}
```

## 4. Blade view additions

All 7 admin blades that need new buttons are LOCKED. Each requires `./bin/unlock <path>` (operator runs, never the agent — see `feedback_unlock_user_runs`). After unlock, the button blocks below mirror PSIS verbatim except for the route helper (`route(...)` instead of `url_for(...)`) and CSRF directive (`@csrf`).

Files to unlock and edit:

```
packages/ahg-registry/resources/views/admin/institutions.blade.php
packages/ahg-registry/resources/views/admin/vendors.blade.php
packages/ahg-registry/resources/views/admin/software.blade.php
packages/ahg-registry/resources/views/admin/standards.blade.php
packages/ahg-registry/resources/views/admin/dropdowns.blade.php
packages/ahg-registry/resources/views/admin/blog.blade.php
packages/ahg-registry/resources/views/admin/discussions.blade.php
packages/ahg-registry/resources/views/admin/reviews.blade.php
```

### 4.1 institutions.blade.php — append to the action `<div class="btn-group">` (currently has Users + Edit):

```blade
@if(empty($item->is_verified))
  <form method="post" action="{{ route('registry.admin.institutionVerify') }}" class="d-inline">
    @csrf
    <input type="hidden" name="form_action" value="verify">
    <input type="hidden" name="id" value="{{ (int) $item->id }}">
    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Verify') }}"><i class="fas fa-check"></i></button>
  </form>
@else
  <form method="post" action="{{ route('registry.admin.institutionVerify') }}" class="d-inline">
    @csrf
    <input type="hidden" name="form_action" value="unverify">
    <input type="hidden" name="id" value="{{ (int) $item->id }}">
    <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Unverify') }}"><i class="fas fa-times"></i></button>
  </form>
@endif

<form method="post" action="{{ route('registry.admin.institutionVerify') }}" class="d-inline">
  @csrf
  <input type="hidden" name="form_action" value="feature">
  <input type="hidden" name="id" value="{{ (int) $item->id }}">
  <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ !empty($item->is_featured) ? __('Unfeature') : __('Feature') }}"><i class="fas fa-star"></i></button>
</form>

@if(!isset($item->is_active) || $item->is_active)
  <form method="post" action="{{ route('registry.admin.institutionVerify') }}" class="d-inline">
    @csrf
    <input type="hidden" name="form_action" value="suspend">
    <input type="hidden" name="id" value="{{ (int) $item->id }}">
    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Suspend') }}"><i class="fas fa-ban"></i></button>
  </form>
@else
  <form method="post" action="{{ route('registry.admin.institutionVerify') }}" class="d-inline">
    @csrf
    <input type="hidden" name="form_action" value="activate">
    <input type="hidden" name="id" value="{{ (int) $item->id }}">
    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Activate') }}"><i class="fas fa-play"></i></button>
  </form>
@endif

<form method="post" action="{{ route('registry.admin.institutionVerify') }}" class="d-inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this institution? This cannot be undone.') }}');">
  @csrf
  <input type="hidden" name="form_action" value="delete">
  <input type="hidden" name="id" value="{{ (int) $item->id }}">
  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
</form>
```

### 4.2 vendors.blade.php / software.blade.php

Mirror 4.1 verbatim, replacing route name with `registry.admin.vendorVerify` / `registry.admin.softwareVerify` and the confirm copy. Software omits suspend/activate at PSIS template level (action handler still accepts them).

### 4.3 standards.blade.php — single Delete button

```blade
<form method="post" action="{{ route('registry.admin.standardDelete') }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this standard?') }}');">
  @csrf
  <input type="hidden" name="id" value="{{ (int) $item->id }}">
  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
</form>
```

### 4.4 dropdowns.blade.php — single Delete button (existing Edit preserved)

```blade
<form method="post" action="{{ route('registry.admin.dropdownDelete') }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this dropdown option?') }}');">
  @csrf
  <input type="hidden" name="id" value="{{ (int) $item->id }}">
  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
</form>
```

### 4.5 blog.blade.php — 4 actions

```blade
@if($post->status !== 'published')
  <form method="post" action="{{ route('registry.admin.blogModerate') }}" class="d-inline">
    @csrf
    <input type="hidden" name="form_action" value="publish">
    <input type="hidden" name="post_id" value="{{ (int) $post->id }}">
    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Publish') }}"><i class="fas fa-check"></i></button>
  </form>
@endif

@if($post->status !== 'archived')
  <form method="post" action="{{ route('registry.admin.blogModerate') }}" class="d-inline">
    @csrf
    <input type="hidden" name="form_action" value="archive">
    <input type="hidden" name="post_id" value="{{ (int) $post->id }}">
    <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Archive') }}"><i class="fas fa-archive"></i></button>
  </form>
@endif

<form method="post" action="{{ route('registry.admin.blogModerate') }}" class="d-inline">
  @csrf
  <input type="hidden" name="form_action" value="feature">
  <input type="hidden" name="post_id" value="{{ (int) $post->id }}">
  <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ !empty($post->is_featured) ? __('Unfeature') : __('Feature') }}"><i class="fas fa-star"></i></button>
</form>

<form method="post" action="{{ route('registry.admin.blogModerate') }}" class="d-inline">
  @csrf
  <input type="hidden" name="form_action" value="pin">
  <input type="hidden" name="post_id" value="{{ (int) $post->id }}">
  <button type="submit" class="btn btn-sm btn-outline-info" title="{{ !empty($post->is_pinned) ? __('Unpin') : __('Pin') }}"><i class="fas fa-thumbtack"></i></button>
</form>
```

(The variable name `$post` matches the foreach in the blade today; if the file uses `$item`, swap accordingly during edit.)

### 4.6 discussions.blade.php — 5 actions

Mirror PSIS template with `form_action` values `lock|pin|hide|spam|activate`, `discussion_id` hidden, route `registry.admin.discussionModerate`.

### 4.7 reviews.blade.php — 3 actions

Mirror PSIS template with `form_action` values `approve|toggle_visibility|delete`, `review_id` hidden, also a hidden `filter` to preserve the current filter, route `registry.admin.reviewModerate`.

## 5. CSRF, auth, validation

- Every blade form gets `@csrf`. Heratio runs full Laravel CSRF (per `feedback_settings_css_theme` security hardening from v1.24.0).
- No additional auth check needed in handlers — the route group already applies `middleware('admin')`.
- No request-level validation rules required for the Verify/Moderate handlers; they read fixed enum values from `form_action` and cast `id` to int. Standalone `Request` rule classes are unnecessary unless the user later wants the audit-trail to capture rejected attempts.
- Soft-delete: PSIS uses hard `delete()`. Heratio matches. If audit/restore is desired later, swap to `softDeletes` migration; not in this spec.

## 6. Testing checklist

After porting, smoke-test by:

1. Hit each admin page, confirm new buttons render.
2. For each `form_action`, fire it via curl with valid `_token` and `id`/`post_id`/etc., confirm 302 redirect to the parent admin URL.
3. Verify DB state changed correctly (e.g. `is_verified=1` after verify).
4. Verify CSRF rejection: same POST without `_token` returns 419.
5. Verify admin guard: same POST as anon returns 403.
6. Confirm 404 path: `id=99999` does not crash, just no-ops + redirect.

Recipe template (admin POST as johanpiet via tinker):

```bash
cat > /tmp/audit-modpost.php <<'PHP'
<?php
use Illuminate\Http\Request;
$user = \AhgCore\Models\User::find(900148);
\Auth::login($user);
$req = Request::create("/registry/admin/institution/verify", "POST", [
    'id' => 999,           // pick a known non-active institution
    'form_action' => 'verify',
    '_token' => csrf_token(),
]);
app()->instance("request", $req);
$ctrl = app(\AhgRegistry\Controllers\RegistryController::class);
$resp = $ctrl->adminInstitutionVerify($req);
echo "status=".$resp->getStatusCode()." location=".$resp->headers->get('Location')."\n";
PHP
php artisan tinker --execute="require '/tmp/audit-modpost.php';"
```

## 7. Out-of-scope follow-ups

These also exist in PSIS but were excluded from the primary port to keep this spec focused:

- `executeAdminGroupVerify` (actions.class.php:4242) — same shape as institution/vendor/software, on `registry_user_group`.
- `executeAdminInstitutionUsers` POST (actions.class.php:3729-3787) — link/delink/set-primary/update-role on `registry_user_institution`. Heratio's `adminInstitutionUsers` GET method already exists; the POST handler does not.
- Independent `adminBlogList` filter chips (status, search). The handler accepts `q` and `status` but the blade lacks UI for it.
- Newsletter send (`executeAdminNewsletterSend` actions.class.php:5591) — not in audit scope but missing.

Add these to the same tracking issue once raised.

## 8. Bin / git workflow

- Per `feedback_no_commit` and `feedback_no_push`, the agent never commits. After the port lands, supply the user with a `./bin/release` command (only push pathway, per `feedback_push_command`).
- Each blade edit needs a separate `./bin/unlock <path>` from the operator before the agent touches it. Per-change scope only.
- Per `feedback_no_claude_coauthor`, no Co-Authored-By line in release messages.
- No em-dashes in any new copy (per `feedback_no_em_dashes`). All confirm strings above use plain hyphens.
- Per `feedback_close_only_at_100`, do not close the tracking issue until all 7 page handlers + 8 blade edits are end-to-end verified.

## 9. Estimated size

| Component | Files touched | LoC delta |
|---|---|---|
| Routes | 1 (web.php) | +9 |
| Controller | 1 (RegistryController.php) | +130 |
| Service | 1 (RegistryService.php) | +180 |
| Blades | 8 (locked, per-file unlock) | +320 |
| **Total** | **11 files** | **~640 LoC** |

About a half-day of focused work once unlocks are in hand. Mostly mechanical; biggest risk is the Heratio blade variable names (`$item` vs `$post` vs `$discussion` vs `$review`) diverging from PSIS — verify against each blade before pasting button blocks.
