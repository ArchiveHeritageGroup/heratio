-- Research report templates (Research > Report Templates, table research_report_template).
--
-- Until this seed, a fresh install had none: 07_report_templates.sql fills the central
-- reports' report_template table only, and the six system templates below existed only on
-- databases that were migrated from an older install.
--
-- INSERT IGNORE keyed on the unique `code`, so re-running is idempotent and a site that has
-- edited a template keeps its edits. Each sections_config entry is "type" or "type:Title",
-- where type is one of title_page, toc, heading, text, bibliography, collection_list,
-- annotation_list, timeline, custom (see ReportService::createFromTemplate).
-- Wording is jurisdiction-neutral.

INSERT IGNORE INTO `research_report_template` (`name`, `code`, `description`, `sections_config`, `is_system`) VALUES
('Research Summary', 'research_summary', 'General research summary report',
 '["title_page", "toc", "heading:Introduction", "text:Background", "text:Methodology", "text:Findings", "text:Conclusion", "bibliography"]', 1),
('Genealogical Report', 'genealogical', 'Family history research report',
 '["title_page", "toc", "heading:Family Overview", "text:Origins", "text:Family Timeline", "text:Notable Members", "collection_list", "bibliography"]', 1),
('Historical Analysis', 'historical', 'Historical research analysis',
 '["title_page", "toc", "heading:Historical Context", "text:Primary Sources", "text:Analysis", "text:Interpretation", "annotation_list", "bibliography"]', 1),
('Source Analysis', 'source_analysis', 'Archival source analysis report',
 '["title_page", "toc", "heading:Source Description", "text:Provenance", "text:Content Analysis", "text:Reliability Assessment", "annotation_list", "bibliography"]', 1),
('Finding Aid', 'finding_aid', 'Collection finding aid',
 '["title_page", "toc", "heading:Collection Overview", "text:Administrative History", "text:Scope and Content", "collection_list", "text:Access Conditions"]', 1),
('Custom Report', 'custom', 'Blank report with no predefined sections',
 '["title_page", "text:Content"]', 1),
('Biographical Study', 'biographical', 'Life and work of a person, drawn from the archive and authority records',
 '["title_page", "toc", "heading:Introduction", "text:Early Life", "text:Career and Public Life", "text:Personal Life and Relationships", "timeline:Chronology", "text:Legacy", "collection_list:Sources Consulted", "bibliography"]', 1),
('Object Biography', 'object_biography', 'The history of a single museum object from making to the present',
 '["title_page", "toc", "heading:The Object", "text:Description and Materials", "text:Making and Origin", "text:Ownership and Use", "text:Acquisition by the Museum", "text:Condition and Conservation", "text:Significance", "annotation_list:Research Notes", "bibliography"]', 1),
('Provenance Research', 'provenance', 'Chain of ownership and custody for an object or collection, with gaps noted',
 '["title_page", "toc", "heading:Summary of Findings", "text:Object or Collection Identified", "timeline:Chain of Ownership", "text:Gaps and Uncertainties", "text:Documentary Evidence", "text:Conclusion and Recommendations", "collection_list:Records Examined", "bibliography"]', 1),
('Exhibition Research', 'exhibition', 'Background research and object selection for an exhibition or display',
 '["title_page", "toc", "heading:Exhibition Concept", "text:Themes and Storyline", "text:Audience", "collection_list:Proposed Objects and Records", "text:Interpretation and Labels", "text:Loans and Permissions", "bibliography"]', 1),
('Oral History Report', 'oral_history', 'Interview summary with context, themes and links to related records',
 '["title_page", "heading:Interview Details", "text:Interviewee and Context", "text:Summary of the Interview", "text:Key Themes", "text:Names, Places and Events Mentioned", "text:Consent and Access Conditions", "collection_list:Related Records"]', 1),
('Collection Significance Assessment', 'significance', 'Assessment of the historical, aesthetic, scientific and social value of a collection',
 '["title_page", "toc", "heading:The Collection", "text:History and Provenance", "text:Historical Significance", "text:Aesthetic Significance", "text:Research Potential", "text:Social and Community Value", "text:Comparative Collections", "text:Statement of Significance", "bibliography"]', 1),
('Research Visit Report', 'research_visit', 'A record of a reading room visit: what was consulted and what was found',
 '["title_page", "heading:Visit Details", "text:Purpose of the Visit", "collection_list:Material Consulted", "text:Findings", "annotation_list:Notes Taken", "text:Follow-up Required"]', 1);
