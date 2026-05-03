-- One-time seed of museum_metadata_i18n (en) from museum_metadata.
-- Idempotent: INSERT IGNORE skips rows where (id,'en') already exists.
INSERT IGNORE INTO museum_metadata_i18n (
  id, culture,
  work_type, object_type, classification, materials, techniques, measurements, dimensions,
  inscription, inscriptions, condition_notes, provenance, style_period, cultural_context,
  current_location, edition_description, state_description, state_identification,
  facture_description, technique_cco, technique_qualifier, orientation, physical_appearance,
  color, shape, condition_term, condition_description, condition_agent, treatment_type,
  treatment_agent, treatment_description, inscription_transcription, inscription_type,
  inscription_location, inscription_language, inscription_translation, mark_type,
  mark_description, mark_location, related_work_type, related_work_relationship,
  related_work_label, current_location_repository, current_location_geography,
  current_location_ref_number, creation_place, creation_place_type, discovery_place,
  discovery_place_type, provenance_text, ownership_history, legal_status, rights_type,
  rights_holder, rights_date, rights_remarks, cataloger_name, cataloging_institution,
  cataloging_remarks, record_type, record_level, creator_identity, creator_role,
  creator_extent, creator_qualifier, creator_attribution, creation_date_display,
  creation_date_qualifier, style, period, cultural_group, movement, school, dynasty,
  subject_indexing_type, subject_display, subject_extent, historical_context,
  architectural_context, archaeological_context, object_class, object_category,
  object_sub_category, edition_number, edition_size
)
SELECT
  id, 'en',
  work_type, object_type, classification, materials, techniques, measurements, dimensions,
  inscription, inscriptions, condition_notes, provenance, style_period, cultural_context,
  current_location, edition_description, state_description, state_identification,
  facture_description, technique_cco, technique_qualifier, orientation, physical_appearance,
  color, shape, condition_term, condition_description, condition_agent, treatment_type,
  treatment_agent, treatment_description, inscription_transcription, inscription_type,
  inscription_location, inscription_language, inscription_translation, mark_type,
  mark_description, mark_location, related_work_type, related_work_relationship,
  related_work_label, current_location_repository, current_location_geography,
  current_location_ref_number, creation_place, creation_place_type, discovery_place,
  discovery_place_type, provenance_text, ownership_history, legal_status, rights_type,
  rights_holder, rights_date, rights_remarks, cataloger_name, cataloging_institution,
  cataloging_remarks, record_type, record_level, creator_identity, creator_role,
  creator_extent, creator_qualifier, creator_attribution, creation_date_display,
  creation_date_qualifier, style, period, cultural_group, movement, school, dynasty,
  subject_indexing_type, subject_display, subject_extent, historical_context,
  architectural_context, archaeological_context, object_class, object_category,
  object_sub_category, edition_number, edition_size
FROM museum_metadata;
