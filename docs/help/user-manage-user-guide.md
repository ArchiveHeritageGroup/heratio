> Heratio Help Center article. Category: Administration / Users.

# User Management

User Management is where administrators create and maintain the people who can sign in to Heratio: their account details, contact information, group memberships, translation languages, API keys and per-user plugin visibility. It also handles self-service tasks such as viewing a profile, changing a password and reviewing the clipboard, and it processes the queue of public registration requests. Administrators reach the user list at **Admin -> Users** (`/admin/users`); signed-in users reach their own profile at `/user/profile`. The feature is provided by the `ahg-user-manage` package.

## Overview

Each Heratio user is modelled as an actor of class `QubitUser`, so a user record carries both account fields (username, email, password, active flag) and actor fields (display name, contact information). Users are organised into ACL groups, which determine what they can do; the User Management pages let you assign those groups but the permissions themselves are defined in the ACL area.

The pages divide into three audiences:

| Audience | Pages | Examples |
|---|---|---|
| Administrators | User browse, add, edit, delete, plugin grants, registration queue | `/admin/users`, `/user/add`, `/user/{slug}/edit` |
| Signed-in users | Profile, password change, clipboard, plugin preferences | `/user/profile`, `/user/passwordReset`, `/user/clipboard` |
| Public | Registration and email verification | `/user/register`, `/user/verify/{token}` |

## Key features

- **User browse.** A paginated, searchable list of users with sorting by name, date modified or email, and an active / inactive / all status filter. Each row shows the user's display name, username, email, status and group memberships.
- **Create and edit users.** A full form covering username, email, password, display name, full contact details, group assignments, translation languages, preferred locale, and the active flag.
- **Account deletion.** A confirm-then-delete flow that removes the user along with their group memberships, ACL permissions, API keys and underlying records.
- **Group assignment.** Assign any application group (groups above the system range) on the edit form. Every user is always a member of the Authenticated group.
- **Translation languages.** Grant a user permission to translate content into specific locales, recorded as a translate permission.
- **Preferred locale.** Optionally pin a user's interface language; left blank, the interface falls back to the URL, cookie or browser preference.
- **API keys.** Generate or delete a user's REST API key and OAI-PMH key from the edit form.
- **Per-user plugin grants.** Administrators can allow or deny individual plugins for a specific user, overriding the global plugin configuration.
- **Per-user plugin preferences.** Administrators and editors can hide globally enabled plugins from their own navigation to reduce clutter.
- **Self-service profile and password.** Users can view their profile, edit it, and change their own password after confirming the current one.
- **Registration queue.** Public registration requests collect in a queue that administrators approve (which creates the account) or reject, with optional admin notes and an assigned group.

## How to use

### Browse users

1. Go to **Admin -> Users** (`/admin/users`). The list opens showing active users.
2. Use the status control to switch between Active, Inactive and All users.
3. Use the search box to filter by name, username or email, and the sort control to order by Name, Date modified or Email.
4. Click a user to open their detail page (`/user/{slug}`), which shows their groups, any API keys, and their security clearance when the clearance feature is installed.

### Add a user

1. From the user list, choose **Add** (`/user/add`).
2. Complete the form:
   - **Username** and **email** are required and must be unique.
   - **Password** is required (minimum six characters); a confirmation field must match if filled.
   - **Display name** (authorized form of name) is the human-readable name shown throughout the interface.
   - **Contact information** - telephone, fax, street address, city, region, postal code, country code, website and a free-text note.
   - **Groups** - tick the application groups this user should belong to.
   - **Translation languages** - choose the locales the user may translate into.
   - **Preferred locale** - optionally pin the interface language; leave blank for no preference.
   - **Active** - leave ticked for an enabled account.
3. Save. The new user is created and you are taken to their detail page.

### Edit a user

1. Open a user and choose **Edit** (`/user/{slug}/edit`).
2. Change any of the fields above. Leave the password blank to keep the existing password; enter a new one to change it.
3. Use the **API key** controls to generate or delete the user's REST API key and OAI-PMH key (see below).
4. Save. Group memberships are re-synchronised to your selection while the Authenticated group is always retained.

### Manage a user's API keys

1. On the edit form, find the REST API key and OAI-PMH key controls.
2. Choose **generate** to create a new key (this replaces any existing key of that type) or **delete** to remove the key entirely.
3. Save the form. Keys are stored against the user and shown on their detail page.

### Delete a user

1. Open the user and choose **Delete** (`/user/{slug}/delete`).
2. Confirm on the confirmation page. Deleting a user removes their group memberships, ACL permissions, API keys, display name, contact records and underlying account in one transaction.

### Manage per-user plugin grants (administrators)

1. Open a user's plugin grants page (`/user/{slug}/plugins`).
2. For each plugin, choose **Inherit**, **Allow** or **Deny**:
   - **Inherit** leaves the global plugin configuration in effect (no per-user override).
   - **Allow** turns the plugin on for this user regardless of the global setting.
   - **Deny** turns the plugin off for this user.
3. Save. The grants for this user are replaced with your selection.

### Manage your own plugin preferences

1. Go to **Profile -> Plugins** (`/user/profile/plugins`). This page is available to administrators and editors.
2. Tick the globally enabled plugins you want to hide from your own navigation.
3. Save. Your preferences are stored separately from the admin grants and only affect your own view.

### Self-service: profile, password and clipboard

1. **Profile** (`/user/profile`) shows your own account using the same detail view as the admin user page.
2. **Edit profile** (`/user/profile/edit`) takes you to your edit form.
3. **Change password** (`/user/passwordReset`) asks for your current password and a new password (with confirmation) before updating it.
4. **Clipboard** (`/user/clipboard`) lists the items you have saved to your clipboard.

### Process registration requests

1. Go to the registration queue (`/user/registration/pending`). The page lists incoming public registration requests and can be filtered by status (pending, verified, approved, rejected or expired).
2. To approve a request, optionally choose a group to assign and add admin notes, then approve. This creates a full user account from the request details (including the verified email and chosen username) and marks the request approved.
3. To reject a request, add admin notes and reject. The request is marked rejected and no account is created.

Public visitors begin the process at the registration form (`/user/register`) and confirm their email through the verification link (`/user/verify/{token}`).

## Configuration

The package has no dedicated config file; behaviour is driven by data and shared settings:

- **User accounts** are stored across the class-table-inheritance chain `object` -> `actor` -> `user`, with the display name in `actor_i18n` and contact details in `contact_information` / `contact_information_i18n`.
- **Group membership** is stored in `acl_user_group`. Every user is always assigned the Authenticated group (id 99); only groups above the system range (id greater than 99) are selectable on the form.
- **Translation languages** are stored as a translate permission in `acl_permission` for the user.
- **Preferred locale** is stored on the `user` record when the schema includes that column; on older installs the field is simply not shown and the interface falls back to the URL, cookie or browser preference.
- **API keys** are stored in the `property` / `property_i18n` tables under the names `RestApiKey` and `OaiApiKey`.
- **Plugin grants** are stored in `user_plugin_grant`, and **plugin preferences** in `user_plugin_preference`; both reference plugin names from `atom_plugin`.
- **Available languages** for the form come from the `i18n_languages` setting, falling back to the translation files in the application's `lang` directory.
- **Page size** for the browse list follows the shared hits-per-page setting.
- **Registration requests** are stored in a registration request table (`ahg_registration_request`), the only table this package installs.

Administrative pages are protected by the `admin` middleware, and write actions additionally require the matching ACL action (create, update or delete). Self-service pages require an authenticated session; the registration form and email verification are public.

## References

- Source: packages/ahg-user-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/TBD
