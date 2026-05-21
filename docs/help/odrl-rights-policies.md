> Heratio Help Center article. Category: Rights / Digital Rights.

# ODRL Rights Policies

## Overview

Heratio implements the W3C Open Digital Rights Language (ODRL) standard to manage fine-grained access control over archival descriptions, collections, and digital objects. ODRL policies allow institutions to express permissions, prohibitions, and obligations in a machine-readable format that Heratio enforces automatically.

Unlike simple role-based access, ODRL policies can express nuanced rules such as "allow reproduction only for registered researchers until December 2030" or "prohibit distribution of this collection except for educational use."

---

## How ODRL Policies Work

Each policy consists of three core components:

- **Target** --- the resource the policy applies to (an archival description, collection, project, or digital object)
- **Rule** --- a permission or prohibition governing an action
- **Constraint** --- optional conditions that narrow when the rule applies (date ranges, user roles, usage limits)

When a user attempts to access or interact with a resource, Heratio's middleware evaluates all applicable ODRL policies. If any prohibition matches, access is denied. If a permission is required and none matches, access is also denied. All decisions are logged to the audit trail.

---

## Creating a Policy

### Step 1: Select a Target

Navigate to the resource (archival description, collection, or project) and open the **Rights** tab. Click **Add ODRL Policy**.

Target types include:

- **Archival Description** --- a single information object
- **Collection** --- an entire fonds or collection and its descendants
- **Project** --- a research project grouping
- **Digital Object** --- a specific file or representation
- **Repository** --- all holdings of a repository

### Step 2: Define Policy Type

Choose the policy type:

- **Permission** --- explicitly allows an action under specified conditions
- **Prohibition** --- explicitly denies an action

### Step 3: Select Action Types

One or more actions can be included in a single policy:

| Action | Description |
|--------|-------------|
| Use | General access to view or consult the resource |
| Reproduce | Create copies (photocopies, scans, photographs) |
| Distribute | Share or disseminate the resource to third parties |
| Modify | Alter, annotate, or create derivative works |
| Archive | Store or preserve copies of the resource |
| Display | Exhibit or publish the resource publicly |

### Step 4: Add Constraints (Optional)

Constraints narrow when the policy applies:

| Constraint | Description | Example |
|------------|-------------|---------|
| Researcher Restriction | Limit to users with a specific role | Only registered researchers |
| Date Window | Policy active only within a date range | Valid from 2024-01-01 to 2030-12-31 |
| Max Uses | Limit the total number of times an action can be performed | Maximum 5 reproductions |
| Purpose | Restrict to a specific purpose | Educational use only |
| Geography | Limit by geographic region | South Africa only |

Multiple constraints can be combined. All constraints must be satisfied for the rule to apply.

---

## Enforcement

Heratio enforces ODRL policies through middleware that intercepts requests at two levels:

### Viewing Enforcement

When a user navigates to an archival description, the middleware checks all ODRL policies targeting that resource (and its parent collection). If a prohibition on "Use" exists and no overriding permission matches the user's profile, the page displays an access-restricted notice instead of the full record.

### Reproduction Enforcement

When a user requests a download, print, or copy of a digital object, the middleware checks for "Reproduce" permissions. If the user does not meet the required constraints (role, date window, usage count), the reproduction request is denied with an explanation.

### Policy Evaluation Order

1. Check for prohibitions --- if any prohibition matches, deny access
2. Check for permissions --- if a permission with matching constraints exists, allow access
3. Default --- if no policy exists, fall back to the system default (configurable in Settings)

---

## Audit Trail

Every access decision is logged automatically:

- **Timestamp** of the access attempt
- **User** who made the request
- **Resource** that was accessed or denied
- **Action** attempted (use, reproduce, distribute, etc.)
- **Decision** (allowed or denied)
- **Policy ID** that governed the decision
- **Constraints evaluated** and their results

Audit logs are accessible from **Admin > Reports > ODRL Audit Log** and can be exported as CSV for compliance reporting.

---

## Administrator Bypass

Users with the Administrator role are exempt from all ODRL policy restrictions. This ensures that system administrators can always access and manage all resources regardless of policy configuration.

This bypass is logged in the audit trail with the notation "admin-bypass" so that administrative access remains transparent.

---

## Managing Policies

### Editing a Policy

Navigate to the resource, open the Rights tab, and click the policy to edit. All fields (target, type, actions, constraints) can be modified. Changes take effect immediately.

### Deleting a Policy

Click **Delete** on a policy to remove it. Deletion is permanent and logged in the audit trail. Once deleted, the resource reverts to the system default access level.

### Bulk Policy Assignment

To apply a policy to multiple resources, use the **Bulk ODRL Assignment** tool under Admin > Rights. Select a collection or use a saved search, then define the policy to apply to all matching resources.

---

## Best Practices

- Start with collection-level policies and override at the item level only when needed
- Use date windows for embargoed materials rather than manually toggling access
- Review the audit log periodically to identify unexpected denials
- Document the rationale for each policy in the notes field
- Test policies with a non-admin account before relying on them

---

*Part of the Heratio AHG Framework*
