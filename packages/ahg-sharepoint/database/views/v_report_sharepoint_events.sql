-- Reporting view for BI consumers (Power BI, Tableau, Metabase).
-- Mirror of atom-ahg-plugins/ahgSharePointPlugin/database/views/.
-- Apply via: mysql ... < this file.

CREATE OR REPLACE VIEW v_report_sharepoint_events AS
SELECT
    e.id                            AS event_id,
    e.received_at                   AS received_at,
    e.processed_at                  AS processed_at,
    e.status                        AS status,
    e.attempts                      AS attempts,
    e.change_type                   AS change_type,
    e.sp_item_id                    AS sp_item_id,
    e.information_object_id         AS information_object_id,
    d.id                            AS drive_id,
    d.site_url                      AS site_url,
    d.site_title                    AS site_title,
    d.drive_name                    AS drive_name,
    d.sector                        AS sector,
    t.id                            AS tenant_id,
    t.name                          AS tenant_name,
    s.subscription_id               AS subscription_id,
    s.resource                      AS subscription_resource
FROM sharepoint_event e
LEFT JOIN sharepoint_subscription s ON s.id = e.subscription_id
LEFT JOIN sharepoint_drive d        ON d.id = e.drive_id
LEFT JOIN sharepoint_tenant t       ON t.id = d.tenant_id;
