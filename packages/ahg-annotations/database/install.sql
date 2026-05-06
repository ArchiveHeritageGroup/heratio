-- IIIF Web Annotations storage. Closes #100 (and the persistence half of #81).
--
-- Each row is a single W3C Web Annotation (https://www.w3.org/TR/annotation-model).
-- target_iri is the canvas IRI the annotation pins itself to (the manifest's
-- canvas @id, not the IO slug — annotations are canvas-scoped per the spec).
-- body_json holds the full annotation document so the W3C+IIIF flexibility
-- (multiple bodies, motivation, selectors, target.selector with FragmentSelector,
-- etc.) round-trips intact without exploding into N relational columns.
--
-- created_by + updated_by are user.id when authenticated; null for anonymous
-- (anonymous can READ; only authenticated users can write — gated in the
-- AnnotationsController by the auth.required middleware on POST/PUT/DELETE).
--
-- The Mirador "annotations" companion window expects an Annotot-shaped REST
-- endpoint; AnnotationsController emits that shape from these rows.

CREATE TABLE IF NOT EXISTS `ahg_iiif_annotation` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Mirador's annotation client uses string ids in its DOM/state, so we
    -- expose a stable opaque uuid alongside the numeric primary key.
    `uuid` CHAR(36) NOT NULL,
    -- Canvas IRI the annotation pins itself to. For the local IIIF service
    -- this looks like https://heratio.theahg.co.za/iiif/3/<id>/canvas/1
    `target_iri` VARCHAR(1024) NOT NULL,
    -- Optional: canonical short reference to the IO so admin can filter
    -- annotations by archival record without parsing the IRI. Null when the
    -- annotation lives on a canvas not tied to an IO (rare; keep flexible).
    `information_object_id` BIGINT UNSIGNED NULL,
    `body_json` JSON NOT NULL,
    `created_by` INT NULL,
    `updated_by` INT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uuid_unique` (`uuid`),
    KEY `target_iri_idx` (`target_iri`(255)),
    KEY `io_id_idx` (`information_object_id`),
    KEY `created_by_idx` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
