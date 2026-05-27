-- ============================================================================
-- ahg-request-publish - publish_request_status dropdown seed (Heratio #745)
-- ============================================================================
-- Seeds the four canonical status values used by ahg_publish_request.status.
-- INSERT IGNORE keeps re-runs idempotent and never overwrites operator edits
-- made through the Dropdown Manager UI.
-- ============================================================================

INSERT IGNORE INTO `ahg_dropdown`
    (`taxonomy`, `taxonomy_label`, `code`, `label`, `sort_order`, `is_active`)
VALUES
    ('publish_request_status', 'Publish Request Status', 'pending',  'Pending',  10, 1),
    ('publish_request_status', 'Publish Request Status', 'approved', 'Approved', 20, 1),
    ('publish_request_status', 'Publish Request Status', 'rejected', 'Rejected', 30, 1),
    ('publish_request_status', 'Publish Request Status', 'edited',   'Edited',   40, 1);
