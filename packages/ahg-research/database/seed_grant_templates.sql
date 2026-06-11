-- heratio#1239 - Research OS #17 (moonshot 24): Grant Engine - funder templates.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a template keeps its edits on re-run. Each funder template is a row in the
-- `grant_funder_template` dropdown taxonomy. Its ordered SECTION LIST lives in
-- the `metadata` JSON column as {"sections":[{"key":..,"label":..,"hint":..}]}.
--
-- The funders below (generic, NRF, ERC, NIH, Wellcome) are selectable EXAMPLES,
-- NOT assumptions about where a researcher applies. The DEFAULT is the generic,
-- jurisdiction-neutral template. A site adds its own funder templates from the
-- Dropdown Manager without code changes - the Grant Engine reads whatever rows
-- exist in this taxonomy.
--
-- Two further taxonomies seed the draft lifecycle and the tracked-call status,
-- both VARCHAR-backed (never ENUM) and surfaced in the Dropdown Manager.

-- ---------------------------------------------------------------------------
-- Funder templates (the section list per funder lives in metadata.sections)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `metadata`, `created_at`)
VALUES
('grant_funder_template', 'Grant Funder Template', 'research', 'generic', 'Generic (jurisdiction-neutral)', 'secondary', 'file-alt', 10, 1, 1,
 '{"funder":"","sections":[{"key":"summary","label":"Project summary / abstract","hint":"A short, plain-language overview of what you will do and why it matters."},{"key":"background","label":"Background and rationale","hint":"The problem, the gap in current knowledge, and why now."},{"key":"aims","label":"Aims and objectives","hint":"The specific, measurable goals of the project."},{"key":"methodology","label":"Methodology / approach","hint":"How you will do the work, drawn from your method protocol."},{"key":"outputs","label":"Expected outputs and outcomes","hint":"What the project will produce and the change it will create."},{"key":"impact","label":"Significance and impact","hint":"Who benefits and how the field or society is advanced."},{"key":"workplan","label":"Work plan and timeline","hint":"Phases, milestones and the schedule."},{"key":"budget","label":"Budget justification","hint":"What you are requesting and why each item is needed."},{"key":"team","label":"Team and capability","hint":"Who is involved and why they can deliver this."},{"key":"ethics","label":"Ethics and data management","hint":"Consent, privacy, retention and responsible-research considerations, jurisdiction-neutral."}]}',
 NOW()),

('grant_funder_template', 'Grant: NRF-style template', 'research', 'nrf', 'NRF-style (research foundation)', 'info', 'file-alt', 20, 0, 1,
 '{"funder":"National Research Foundation (example)","sections":[{"key":"summary","label":"Lay summary","hint":"Accessible summary for a non-specialist review panel."},{"key":"background","label":"Background and problem statement","hint":"Context and the research problem."},{"key":"questions","label":"Research questions and hypotheses","hint":"Drawn from your question brief."},{"key":"aims","label":"Aims and objectives","hint":"Specific objectives."},{"key":"methodology","label":"Research design and methods","hint":"From your method protocol."},{"key":"outputs","label":"Anticipated outputs","hint":"Publications, datasets, artefacts, training."},{"key":"impact","label":"Expected impact and contribution to knowledge","hint":"Scholarly and broader impact."},{"key":"capacity","label":"Human capacity development","hint":"Students trained, skills transferred."},{"key":"workplan","label":"Work plan and milestones","hint":"Timeline and deliverables."},{"key":"budget","label":"Budget and justification","hint":"Costs and rationale."},{"key":"ethics","label":"Ethics and risk","hint":"Approvals, consent, data management."}]}',
 NOW()),

('grant_funder_template', 'Grant: ERC-style template', 'research', 'erc', 'ERC-style (frontier research)', 'info', 'file-alt', 30, 0, 1,
 '{"funder":"European Research Council (example)","sections":[{"key":"summary","label":"Extended synopsis","hint":"The big idea and why it is high-risk / high-gain."},{"key":"ambition","label":"Ground-breaking nature and ambition","hint":"What makes this beyond the state of the art."},{"key":"objectives","label":"Objectives","hint":"The scientific objectives."},{"key":"methodology","label":"Methodology","hint":"From your method protocol; feasibility of the approach."},{"key":"outputs","label":"Expected outcomes","hint":"What success looks like."},{"key":"impact","label":"Impact and significance","hint":"Advance to the field."},{"key":"feasibility","label":"Feasibility and risk management","hint":"Why it can be done and how risks are handled."},{"key":"resources","label":"Resources and budget","hint":"What is needed and why."},{"key":"team","label":"Principal investigator and team","hint":"Track record and capability."},{"key":"ethics","label":"Ethics and research integrity","hint":"Responsible research, jurisdiction-neutral."}]}',
 NOW()),

('grant_funder_template', 'Grant: NIH-style template', 'research', 'nih', 'NIH-style (health research)', 'info', 'file-alt', 40, 0, 1,
 '{"funder":"National Institutes of Health (example)","sections":[{"key":"summary","label":"Specific aims","hint":"The aims page: the goals and what each aim will establish."},{"key":"significance","label":"Significance","hint":"Importance of the problem and the gap addressed."},{"key":"innovation","label":"Innovation","hint":"How the project challenges or shifts current practice."},{"key":"approach","label":"Approach","hint":"Design, methods and analyses, from your method protocol."},{"key":"outputs","label":"Expected outcomes","hint":"What the work will yield."},{"key":"impact","label":"Impact","hint":"Effect on the field and on health / society."},{"key":"workplan","label":"Timeline and milestones","hint":"Schedule and deliverables."},{"key":"budget","label":"Budget and justification","hint":"Costs and rationale."},{"key":"team","label":"Investigators and environment","hint":"Team and institutional setting."},{"key":"ethics","label":"Human subjects, data and rigour","hint":"Protections, rigour and reproducibility, jurisdiction-neutral."}]}',
 NOW()),

('grant_funder_template', 'Grant: Wellcome-style template', 'research', 'wellcome', 'Wellcome-style (discovery / health)', 'info', 'file-alt', 50, 0, 1,
 '{"funder":"Wellcome Trust (example)","sections":[{"key":"summary","label":"Vision and summary","hint":"The discovery question and why it matters."},{"key":"background","label":"Background and context","hint":"Where the field is and the gap."},{"key":"aims","label":"Research aims","hint":"What you intend to discover or build."},{"key":"approach","label":"Research approach","hint":"Methods and rationale, from your method protocol."},{"key":"outputs","label":"Outputs and open research","hint":"Outputs and your open-research / data-sharing plan."},{"key":"impact","label":"Significance and impact","hint":"Advance to knowledge and to people."},{"key":"workplan","label":"Plan and milestones","hint":"Timeline and deliverables."},{"key":"budget","label":"Resources requested","hint":"What is needed and why."},{"key":"team","label":"Team and track record","hint":"Capability to deliver."},{"key":"ethics","label":"Research integrity and data management","hint":"Responsible research, jurisdiction-neutral."}]}',
 NOW());

-- ---------------------------------------------------------------------------
-- Draft status taxonomy (Dropdown Manager - never ENUM)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('grant_draft_status', 'Grant Draft Status', 'research', 'draft', 'Draft', 'secondary', NULL, 10, 1, 1, NOW()),
('grant_draft_status', 'Grant Draft Status', 'research', 'in_review', 'In Review', 'info', NULL, 20, 0, 1, NOW()),
('grant_draft_status', 'Grant Draft Status', 'research', 'ready', 'Ready', 'primary', NULL, 30, 0, 1, NOW()),
('grant_draft_status', 'Grant Draft Status', 'research', 'submitted', 'Submitted', 'success', NULL, 40, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Tracked-call status taxonomy (Dropdown Manager - never ENUM)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('grant_call_status', 'Grant Call Status', 'research', 'watching', 'Watching', 'secondary', NULL, 10, 1, 1, NOW()),
('grant_call_status', 'Grant Call Status', 'research', 'preparing', 'Preparing', 'info', NULL, 20, 0, 1, NOW()),
('grant_call_status', 'Grant Call Status', 'research', 'submitted', 'Submitted', 'primary', NULL, 30, 0, 1, NOW()),
('grant_call_status', 'Grant Call Status', 'research', 'awarded', 'Awarded', 'success', NULL, 40, 0, 1, NOW()),
('grant_call_status', 'Grant Call Status', 'research', 'declined', 'Declined', 'danger', NULL, 50, 0, 1, NOW()),
('grant_call_status', 'Grant Call Status', 'research', 'closed', 'Closed', 'dark', NULL, 60, 0, 1, NOW());
