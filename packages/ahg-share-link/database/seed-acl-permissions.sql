-- ahgTimeLimitedShareLinkPlugin — Phase I seed
--
-- Idempotent ACL grants for the 5 share-link permissions.
-- Group ids (AtoM defaults):
--   100 = administrator  → bypassed in code (AclCheck), no INSERT needed
--   101 = editor
--   102 = contributor
--   103 = translator     → no grants
--
-- Permission matrix:
--   share_link.create                  101, 102
--   share_link.create_classified       (admin only, no group grant)
--   share_link.create_unlimited_expiry (admin only, no group grant)
--   share_link.list_all                101
--   share_link.revoke_others           101
--
-- Run via: mysql archive < seed-acl-permissions.sql

-- editor (101) — create + list_all + revoke_others
INSERT INTO acl_permission (user_id, group_id, object_id, action, grant_deny, created_at, updated_at)
SELECT NULL, 101, NULL, 'share_link.create', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM acl_permission WHERE group_id=101 AND action='share_link.create');

INSERT INTO acl_permission (user_id, group_id, object_id, action, grant_deny, created_at, updated_at)
SELECT NULL, 101, NULL, 'share_link.list_all', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM acl_permission WHERE group_id=101 AND action='share_link.list_all');

INSERT INTO acl_permission (user_id, group_id, object_id, action, grant_deny, created_at, updated_at)
SELECT NULL, 101, NULL, 'share_link.revoke_others', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM acl_permission WHERE group_id=101 AND action='share_link.revoke_others');

-- contributor (102) — create only
INSERT INTO acl_permission (user_id, group_id, object_id, action, grant_deny, created_at, updated_at)
SELECT NULL, 102, NULL, 'share_link.create', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM acl_permission WHERE group_id=102 AND action='share_link.create');

-- Final report
SELECT g.id AS group_id, gi.name AS group_name, p.action, p.grant_deny
FROM acl_permission p
JOIN acl_group g ON g.id = p.group_id
LEFT JOIN acl_group_i18n gi ON gi.id = g.id AND gi.culture = 'en'
WHERE p.action LIKE 'share_link.%'
ORDER BY g.id, p.action;
