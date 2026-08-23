# #1478 triage: the 131 always-fallback Blade bindings

Date: 2026-08-23
Author: Dr Johan Pieterse
Status: triage complete; no fixes applied

`tools/scan-blade-bindings.php` finds Blade `??` chains in which no
alternative resolves to anything the codebase produces. It reports 131 across
73 views. This is the triage of that list: what each one actually is, and
which ones matter.

## The finding that changes the priority

**44 of the 131 are form fields that are never saved.** Not display defects -
input controls that render empty, are absent from the validator, and are
discarded on submit. A cataloguer types into them, presses Save, and the data
goes nowhere. No error is raised at any point.

The remaining 87 are display-only: a value that renders permanently blank, or
as its fallback.

A binding was classed as a form field when the same view uses the property as
a control name (`name="prop"`), and as never-saved when that name appears
nowhere in the owning package's PHP - so nothing can be validating or
persisting it.

## Class A - form fields that are never saved (44)

### Verified in full

**`ahg-gallery/.../gallery/edit.blade.php` - 20 fields, and
`ahg-museum/.../museum/edit.blade.php` - 10.** Both edit forms are built
around Cataloguing Cultural Objects (CCO) elements, each with its own help
text and CCO citation. `GalleryController::edit()` loads the artwork through
`GalleryService::getBySlug()`, which selects from `information_object` joined
to `museum_metadata`. None of the 20 names exists in `museum_metadata`'s 90
columns, and none appears anywhere in `ahg-gallery/src` or `ahg-museum/src` -
not in `update()`'s validator, not in the save path.

So the form offers, among others, `dimensions_display` - annotated in the
markup as **required, CCO 6.1** - and silently drops whatever is typed into
it. A required field that cannot be saved is the sharpest form of this bug.

**`ahg-research/.../edit-researcher-type.blade.php` - 3 fields.** A different
cause with the same effect: the names are truncated versions of the real
columns.

| Form posts | `research_researcher_type` has |
| --- | --- |
| `max_advance_days` | `max_booking_days_advance` |
| `max_hours_per_day` | `max_booking_hours_per_day` |
| `max_materials` | `max_materials_per_booking` |

Booking limits for a researcher type therefore cannot be edited at all.

### Full class A list

- `ahg-acl/resources/views/security/user-clearance.blade.php`
  - `vetting_authority`
  - `vetting_date`
  - `vetting_reference`
- `ahg-cart/resources/views/admin-settings.blade.php`
  - `tax_rate`
- `ahg-exhibition/resources/views/storyline.blade.php`
  - `stop_order`
- `ahg-federation/resources/views/edit-peer.blade.php`
  - `harvest_interval`
  - `set_spec`
- `ahg-gallery/resources/views/gallery/edit.blade.php`
  - `alternate_titles`
  - `attribution_qualifier`
  - `components_count`
  - `condition_summary`
  - `creator_display`
  - `depth_value`
  - `dimension_notes`
  - `dimensions_display`
  - `height_value`
  - `iconography`
  - `impression_quality`
  - `location_within_repository`
  - `materials_display`
  - `named_subjects`
  - `school_group`
  - `subjects_depicted`
  - `title_language`
  - `weight_value`
  - `width_value`
  - `work_type_qualifier`
- `ahg-information-object-manage/resources/views/digitalobject/edit-page.blade.php`
  - `display_as_compound`
- `ahg-museum/resources/views/museum/edit.blade.php`
  - `attribution_qualifier`
  - `condition_summary`
  - `creator_display`
  - `depth_value`
  - `dimensions_display`
  - `height_value`
  - `location_within_repository`
  - `materials_display`
  - `subjects_depicted`
  - `width_value`
- `ahg-repository-manage/resources/views/edit.blade.php`
  - `repository_type`
- `ahg-research/resources/views/research/edit-researcher-type.blade.php`
  - `max_advance_days`
  - `max_hours_per_day`
  - `max_materials`
- `ahg-research/resources/views/research/source-assessment.blade.php`
  - `authenticity_notes`
- `ahg-vendor/resources/views/edit-transaction.blade.php`
  - `completion_date`

## Class C - display-only (87)

These render blank or as their fallback. Lower severity than class A - no
input is lost - but each is a screen reporting something that is not the data,
and the loan-rules and overdue cases already fixed were both of this class.

Worth noting that severity within class C varies more than the count suggests.
The overdue list showed `0 days overdue` and `0.00 fine`, which reads as a
fact rather than as a blank, and is the dangerous end. A field rendering `-`
is merely missing.

- `ahg-accession-manage/resources/views/attachments.blade.php`
  - `size_display`
- `ahg-accession-manage/resources/views/valuation.blade.php`
  - `valuator_name`
- `ahg-acl/resources/views/pending-requests.blade.php`
  - `current_classification`
- `ahg-acl/resources/views/security/declassification.blade.php`
  - `to_name`
- `ahg-acl/resources/views/security/object-view.blade.php`
  - `classified_by_name`
- `ahg-acl/resources/views/security/object.blade.php`
  - `changed_by_username`
  - `classifiedByUsername ?? classified_by_username`
  - `new_name`
- `ahg-acl/resources/views/security/user-clearance.blade.php`
  - `new_name`
  - `renewal_requested_date`
  - `two_factor_verified_at`
- `ahg-acl/resources/views/security/user.blade.php`
  - `grantedByUsername ?? granted_by_username`
  - `new_name`
- `ahg-core/resources/views/add-digital-object.blade.php`
  - `diskUsage`
- `ahg-core/resources/views/components/autocomplete.blade.php`
  - `creator_id`
- `ahg-display/resources/views/display/_browse_content.blade.php`
  - `reference_path`
- `ahg-exhibition/resources/views/_object-list-csv.blade.php`
  - `display_location`
- `ahg-exhibition/resources/views/checklists.blade.php`
  - `checklist_name`
- `ahg-exhibition/resources/views/storylines.blade.php`
  - `stop_count`
- `ahg-function-manage/resources/views/show.blade.php`
  - `relation_dates`
- `ahg-gallery/resources/views/gallery/facility-report.blade.php`
  - `climate_control`
  - `report_date`
- `ahg-gallery/resources/views/galleryReports/facility-reports.blade.php`
  - `climate_control`
  - `fire_safety`
- `ahg-gallery/resources/views/galleryReports/spaces.blade.php`
  - `wall_length`
- `ahg-heritage-manage/resources/views/analytics-content.blade.php`
  - `issue_type`
- `ahg-heritage-manage/resources/views/analytics-search.blade.php`
  - `total_clicks`
- `ahg-heritage-manage/resources/views/my-access-requests.blade.php`
  - `purpose_name`
- `ahg-heritage-manage/resources/views/timeline.blade.php`
  - `year_label`
- `ahg-icip/resources/views/partials/community-protocol-badge.blade.php`
  - `_scope`
- `ahg-information-object-manage/resources/views/_event.blade.php`
  - `eventsRelatedByobjectId`
- `ahg-information-object-manage/resources/views/_related-events.blade.php`
  - `eventsRelatedByobjectId`
- `ahg-information-object-manage/resources/views/_related-material-descriptions.blade.php`
  - `relationsRelatedByobjectId`
  - `relationsRelatedBysubjectId`
- `ahg-information-object-manage/resources/views/digitalobject/edit.blade.php`
  - `canThumbnail`
  - `displayAsCompoundObject`
- `ahg-information-object-manage/resources/views/do-edit.blade.php`
  - `canThumbnail`
  - `displayAsCompoundObject`
- `ahg-ipsas/resources/views/insurance.blade.php`
  - `coverage_currency`
  - `coverage_type`
  - `premium_amount`
  - `premium_currency`
- `ahg-jobs-manage/resources/views/show.blade.php`
  - `error_output`
- `ahg-landing-page/resources/views/_block-card.blade.php`
  - `child_blocks`
- `ahg-landing-page/resources/views/blocks/_block_row_1_col.blade.php`
  - `child_blocks`
  - `computed_data`
- `ahg-landing-page/resources/views/blocks/_block_row_2_col.blade.php`
  - `child_blocks`
  - `computed_data`
- `ahg-landing-page/resources/views/blocks/_block_row_2_col_public.blade.php`
  - `child_blocks`
  - `computed_data`
- `ahg-landing-page/resources/views/blocks/_block_row_3_col.blade.php`
  - `child_blocks`
  - `computed_data`
- `ahg-landing-page/resources/views/index.blade.php`
  - `computed_data`
- `ahg-landing-page/resources/views/my-dashboard.blade.php`
  - `computed_data`
- `ahg-marketplace/resources/views/admin-listing-review.blade.php`
  - `auction_start_price`
- `ahg-marketplace/resources/views/admin-reports.blade.php`
  - `net_seller_payouts`
- `ahg-marketplace/resources/views/admin-seller-verify.blade.php`
  - `follower_count`
- `ahg-marketplace/resources/views/listing.blade.php`
  - `category_slug`
- `ahg-marketplace/resources/views/my-following.blade.php`
  - `listing_count`
- `ahg-marketplace/resources/views/seller-listing-edit.blade.php`
  - `auction_buy_now_price`
  - `auction_reserve_price`
  - `auction_start_price`
- `ahg-multi-tenant/resources/views/index.blade.php`
  - `user_count`
- `ahg-naz/resources/views/researcher-view.blade.php`
  - `material_consulted`
- `ahg-repository-manage/resources/views/_upload-limit.blade.php`
  - `disk_usage`
- `ahg-repository-manage/resources/views/upload-limit-exceeded.blade.php`
  - `disk_usage`
- `ahg-research/resources/views/research/collections.blade.php`
  - `items_count`
- `ahg-research/resources/views/research/custody-chain.blade.php`
  - `staff_name`
- `ahg-research/resources/views/research/custody-return-verify.blade.php`
  - `expected_return`
- `ahg-research/resources/views/research/trust-score.blade.php`
  - `metric_name`
- `ahg-research/resources/views/research/view-booking.blade.php`
  - `researcher_institution`
- `ahg-research/resources/views/research/view-room.blade.php`
  - `is_occupied`
- `ahg-ric/resources/views/_ric-view-repository.blade.php`
  - `geo_cultural_context`
- `ahg-rights-holder-manage/resources/views/extendedRights/_rights-display.blade.php`
  - `cc_code`
  - `cc_name`
  - `cc_uri`
  - `rs_code`
  - `rs_name`
  - `rs_uri`
- `ahg-security-clearance/resources/views/clearance/view-request.blade.php`
  - `object_classification_color`
- `ahg-semantic-search/resources/views/terms.blade.php`
  - `synonym_count`
- `ahg-settings/resources/views/errorLog.blade.php`
  - `request_url`
- `ahg-settings/resources/views/webhooks.blade.php`
  - `delivery_count`
- `ahg-term-taxonomy/resources/views/edit.blade.php`
  - `is_protected`
- `ahg-user-manage/resources/views/index-actor-acl.blade.php`
  - `access_label`
- `ahg-user-manage/resources/views/index-information-object-acl.blade.php`
  - `access_label`
- `ahg-user-manage/resources/views/index-repository-acl.blade.php`
  - `access_label`

## Suggested order of work

1. The 30 CCO fields in the gallery and museum edit forms. Highest severity:
   staff are entering catalogue data that is discarded, and the forms present
   themselves as standards-compliant.
2. The 3 researcher-type booking limits. Small, unambiguous rename.
3. The remaining 11 class A findings.
4. Class C, prioritising anything that renders a number rather than a blank -
   a wrong figure is trusted in a way an empty cell is not.

For any of these, where the concept genuinely does not exist, remove the field
or column rather than inventing a value. That is what was done for `max_items`
in #1477 and it is the reason the loan-rules screen is now honest.

## Caveat

The classification is mechanical and each class A group above was verified by
hand against the actual query and save path. The class C list has not been
individually verified - the scanner's own caveat applies, and some entries
will be dead alternates in chains that work, or properties supplied by code
outside the scanned tree.
