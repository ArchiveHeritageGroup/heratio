-- ahg-reports: seed templates for the records-management compliance dashboard
-- Ported from atom-ahg-plugins/ahgReportBuilderPlugin/database/migrations/2026_05_17_records_management_compliance_templates.sql
-- on 2026-05-17. Phrasing generic-ified for Heratio's international audience
-- (no jurisdiction-specific clause numbers).
--
-- 5 templates, category `records_management_compliance`:
--   1. Audit Summary
--   2. Access Logs & User Activity
--   3. Metadata Integrity
--   4. Retention Status & Lifecycle
--   5. Consolidated Quarterly Dashboard
--
-- Idempotent: INSERT IGNORE on name (UNIQUE-like in spirit; the report_template
-- table uses no unique index on name, but a duplicate-name check on the
-- service-provider boot path filters this re-applies. Operators can rerun
-- this file safely if they keep the names unchanged.)

INSERT IGNORE INTO `report_template` (`name`, `description`, `category`, `scope`, `structure`, `is_active`) VALUES
('Records Management Compliance: Audit Summary',
 'Audit-trail summary mapping every action to user + timestamp + entity. Source data: ahg_audit_log.',
 'records_management_compliance',
 'system',
 JSON_OBJECT(
   'sections', JSON_ARRAY(
     JSON_OBJECT(
       'title', 'Overview',
       'section_type', 'narrative',
       'content', '<p>This report summarises every audited action recorded by the system in the selected reporting period. Each row is sourced from ahg_audit_log and is uniquely identified by a UUID. The data supports the audit-reporting requirements of any applicable records-management framework.</p>',
       'position', 0
     ),
     JSON_OBJECT(
       'title', 'Audit volume by action',
       'section_type', 'chart',
       'config', JSON_OBJECT(
         'dataSource', 'ahg_audit_log',
         'groupBy', 'action',
         'aggregate', 'count',
         'chartType', 'bar'
       ),
       'position', 1
     ),
     JSON_OBJECT(
       'title', 'Top 20 audited users',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT username, COUNT(*) AS event_count, MAX(created_at) AS last_event FROM ahg_audit_log WHERE username IS NOT NULL GROUP BY username ORDER BY event_count DESC LIMIT 20',
         'columns', JSON_ARRAY('username', 'event_count', 'last_event')
       ),
       'position', 2
     ),
     JSON_OBJECT(
       'title', 'Disposal workflow audit chain',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT entity_id AS disposal_id, action, user_id, created_at, JSON_UNQUOTE(JSON_EXTRACT(new_values, ''$.action_type'')) AS action_type FROM ahg_audit_log WHERE entity_type = ''disposal_action'' ORDER BY created_at DESC LIMIT 100',
         'columns', JSON_ARRAY('disposal_id', 'action', 'user_id', 'created_at', 'action_type')
       ),
       'position', 3
     )
   )
 ),
 1),

('Records Management Compliance: Access Logs & User Activity',
 'Detailed user-activity tracking: share-link access, login events, view events. Combines ahg_audit_log + information_object_share_access.',
 'records_management_compliance',
 'system',
 JSON_OBJECT(
   'sections', JSON_ARRAY(
     JSON_OBJECT(
       'title', 'Overview',
       'section_type', 'narrative',
       'content', '<p>User-activity report covering authenticated views, share-link recipient accesses, and authentication events. Use this report to satisfy access-log requirements under any applicable records-management framework.</p>',
       'position', 0
     ),
     JSON_OBJECT(
       'title', 'Active users (last 90 days)',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT user_id, username, COUNT(*) AS actions, MAX(created_at) AS last_seen FROM ahg_audit_log WHERE user_id IS NOT NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY user_id, username ORDER BY actions DESC LIMIT 50',
         'columns', JSON_ARRAY('user_id', 'username', 'actions', 'last_seen')
       ),
       'position', 1
     ),
     JSON_OBJECT(
       'title', 'Share-link access activity (all time)',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT t.id AS token_id, t.information_object_id, t.expires_at, t.access_count, COUNT(a.id) AS total_accesses, MIN(a.accessed_at) AS first_access, MAX(a.accessed_at) AS last_access FROM information_object_share_token t LEFT JOIN information_object_share_access a ON a.token_id = t.id GROUP BY t.id ORDER BY total_accesses DESC LIMIT 50',
         'columns', JSON_ARRAY('token_id', 'information_object_id', 'expires_at', 'access_count', 'total_accesses', 'first_access', 'last_access')
       ),
       'position', 2
     ),
     JSON_OBJECT(
       'title', 'Failed access attempts',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT t.id AS token_id, t.information_object_id, a.action, a.ip_address, a.accessed_at FROM information_object_share_access a JOIN information_object_share_token t ON t.id = a.token_id WHERE a.action LIKE ''denied_%'' ORDER BY a.accessed_at DESC LIMIT 50',
         'columns', JSON_ARRAY('token_id', 'information_object_id', 'action', 'ip_address', 'accessed_at')
       ),
       'position', 3
     )
   )
 ),
 1),

('Records Management Compliance: Metadata Integrity',
 'Metadata-integrity verification. Surfaces records with version history, recent edits, and version-restore activity.',
 'records_management_compliance',
 'system',
 JSON_OBJECT(
   'sections', JSON_ARRAY(
     JSON_OBJECT(
       'title', 'Overview',
       'section_type', 'narrative',
       'content', '<p>Metadata integrity is verified by full deterministic snapshots captured at every save. Every change is reversible. This report shows version-history coverage, recent edits, and restore events.</p>',
       'position', 0
     ),
     JSON_OBJECT(
       'title', 'Coverage statistics',
       'section_type', 'summary_card',
       'config', JSON_OBJECT(
         'cards', JSON_ARRAY(
           JSON_OBJECT('label', 'IOs with version history', 'source', 'sql:SELECT COUNT(DISTINCT information_object_id) FROM information_object_version'),
           JSON_OBJECT('label', 'Total IO versions', 'source', 'sql:SELECT COUNT(*) FROM information_object_version'),
           JSON_OBJECT('label', 'Actors with version history', 'source', 'sql:SELECT COUNT(DISTINCT actor_id) FROM actor_version'),
           JSON_OBJECT('label', 'Restore events', 'source', 'sql:SELECT COUNT(*) FROM information_object_version WHERE is_restore = 1')
         )
       ),
       'position', 1
     ),
     JSON_OBJECT(
       'title', 'Most-edited records (last 90 days)',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT v.information_object_id, MAX(v.version_number) AS latest_version, COUNT(*) AS edits_90d, MAX(v.created_at) AS last_edit FROM information_object_version v WHERE v.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY v.information_object_id ORDER BY edits_90d DESC LIMIT 25',
         'columns', JSON_ARRAY('information_object_id', 'latest_version', 'edits_90d', 'last_edit')
       ),
       'position', 2
     ),
     JSON_OBJECT(
       'title', 'Records restored from a previous version',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT information_object_id, version_number, restored_from_version, change_summary, created_at, created_by FROM information_object_version WHERE is_restore = 1 ORDER BY created_at DESC LIMIT 50',
         'columns', JSON_ARRAY('information_object_id', 'version_number', 'restored_from_version', 'change_summary', 'created_at', 'created_by')
       ),
       'position', 3
     )
   )
 ),
 1),

('Records Management Compliance: Retention Status & Lifecycle',
 'Retention status and lifecycle compliance. Records, schedules, time-until-disposal, and pending disposal-workflow stages.',
 'records_management_compliance',
 'system',
 JSON_OBJECT(
   'sections', JSON_ARRAY(
     JSON_OBJECT(
       'title', 'Overview',
       'section_type', 'narrative',
       'content', '<p>Lifecycle compliance report. Every archival record assigned a retention schedule has a calculated disposal-due date; this report shows where each record is in its lifecycle.</p>',
       'position', 0
     ),
     JSON_OBJECT(
       'title', 'Records assigned to a retention schedule',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT ra.information_object_id, rs.code AS schedule, rs.title, ra.trigger_event_date, ra.calculated_disposal_due, DATEDIFF(ra.calculated_disposal_due, CURDATE()) AS days_to_disposal, rs.disposal_action FROM retention_assignment ra JOIN retention_schedule rs ON ra.retention_schedule_id = rs.id ORDER BY ra.calculated_disposal_due LIMIT 100',
         'columns', JSON_ARRAY('information_object_id', 'schedule', 'title', 'trigger_event_date', 'calculated_disposal_due', 'days_to_disposal', 'disposal_action')
       ),
       'position', 1
     ),
     JSON_OBJECT(
       'title', 'Records due for disposal in next 12 months',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT ra.information_object_id, rs.code AS schedule, rs.disposal_action, ra.calculated_disposal_due, DATEDIFF(ra.calculated_disposal_due, CURDATE()) AS days_to_disposal FROM retention_assignment ra JOIN retention_schedule rs ON ra.retention_schedule_id = rs.id WHERE ra.calculated_disposal_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 12 MONTH) AND rs.disposal_action != ''permanent'' ORDER BY ra.calculated_disposal_due LIMIT 50',
         'columns', JSON_ARRAY('information_object_id', 'schedule', 'disposal_action', 'calculated_disposal_due', 'days_to_disposal')
       ),
       'position', 2
     ),
     JSON_OBJECT(
       'title', 'Disposal workflow pipeline',
       'section_type', 'chart',
       'config', JSON_OBJECT(
         'dataSource', 'disposal_action',
         'groupBy', 'status',
         'aggregate', 'count',
         'chartType', 'pie'
       ),
       'position', 3
     ),
     JSON_OBJECT(
       'title', 'Approved transfers awaiting transfer package',
       'section_type', 'sql_table',
       'config', JSON_OBJECT(
         'query', 'SELECT da.id, da.information_object_id, da.proposed_at, da.executive_signed_at, da.transfer_destination FROM disposal_action da WHERE da.action_type = ''transfer_narssa'' AND da.status = ''approved'' AND da.transfer_manifest_path IS NULL ORDER BY da.proposed_at LIMIT 50',
         'columns', JSON_ARRAY('id', 'information_object_id', 'proposed_at', 'executive_signed_at', 'transfer_destination')
       ),
       'position', 4
     )
   )
 ),
 1),

('Records Management Compliance: Consolidated Quarterly Dashboard',
 'Single-page executive snapshot: audit posture, user activity, metadata integrity, and lifecycle compliance. Run quarterly for the records-management committee.',
 'records_management_compliance',
 'system',
 JSON_OBJECT(
   'sections', JSON_ARRAY(
     JSON_OBJECT(
       'title', 'Executive Summary',
       'section_type', 'narrative',
       'content', '<p>This consolidated quarterly dashboard surfaces the four records-management compliance pillars in one view: audit posture, user activity, metadata integrity, and lifecycle compliance. Designed for the records-management committee, not the security operations team.</p>',
       'position', 0
     ),
     JSON_OBJECT(
       'title', 'Key indicators',
       'section_type', 'summary_card',
       'config', JSON_OBJECT(
         'cards', JSON_ARRAY(
           JSON_OBJECT('label', 'Total holdings',                'source', 'sql:SELECT COUNT(*) FROM information_object'),
           JSON_OBJECT('label', 'Audit events (90d)',            'source', 'sql:SELECT COUNT(*) FROM ahg_audit_log WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)'),
           JSON_OBJECT('label', 'Active retention assignments',  'source', 'sql:SELECT COUNT(*) FROM retention_assignment'),
           JSON_OBJECT('label', 'Records due for disposal (12m)','source', 'sql:SELECT COUNT(*) FROM retention_assignment ra JOIN retention_schedule rs ON ra.retention_schedule_id = rs.id WHERE ra.calculated_disposal_due <= DATE_ADD(CURDATE(), INTERVAL 12 MONTH) AND rs.disposal_action != ''permanent'''),
           JSON_OBJECT('label', 'Pending disposal workflows',    'source', 'sql:SELECT COUNT(*) FROM disposal_action WHERE status NOT IN (''executed'', ''rejected'', ''deferred'')'),
           JSON_OBJECT('label', 'Active share links',            'source', 'sql:SELECT COUNT(*) FROM information_object_share_token WHERE revoked_at IS NULL AND expires_at > NOW()'),
           JSON_OBJECT('label', 'NARSSA transfers packaged',     'source', 'sql:SELECT COUNT(*) FROM narssa_transfer WHERE status IN (''packaged'', ''transmitted'', ''accepted'')'),
           JSON_OBJECT('label', 'Versioned records',             'source', 'sql:SELECT COUNT(DISTINCT information_object_id) FROM information_object_version')
         )
       ),
       'position', 1
     ),
     JSON_OBJECT(
       'title', 'Audit signals (last 30d)',
       'section_type', 'chart',
       'config', JSON_OBJECT(
         'query', 'SELECT DATE(created_at) AS day, COUNT(*) AS events FROM ahg_audit_log WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY day',
         'chartType', 'line'
       ),
       'position', 2
     )
   )
 ),
 1);
