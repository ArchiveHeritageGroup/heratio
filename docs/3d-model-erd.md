# Heratio 3D model — Entity-Relationship Diagram

Data model for the `ahg-3d-model` package (3D digital objects, metadata/paradata,
viewer, hotspots, and IIIF). Renders as a diagram on GitHub and any
Mermaid-aware Markdown viewer.

```mermaid
erDiagram
    information_object ||--o{ object_3d_model : "has 3D model(s)"
    digital_object     }o--|| information_object : "3D file attached to"
    object_3d_model    ||--o{ object_3d_model_i18n : "title/description per culture"
    object_3d_model    ||--o{ object_3d_texture : "textures"
    object_3d_model    ||--o{ object_3d_hotspot : "hotspots"
    object_3d_hotspot  ||--o{ object_3d_hotspot_i18n : "label per culture"
    object_3d_model    ||--o{ object_3d_camera_bookmark : "saved camera views"
    object_3d_model    ||--o| iiif_3d_manifest : "cached IIIF manifest (1:1)"
    object_3d_model    ||--o{ object_3d_audit_log : "change log"

    information_object {
        int id PK
    }
    digital_object {
        int id PK
        int object_id FK "the information_object"
        string mime_type "model/gltf-binary, ..."
        string name
        string path
    }
    object_3d_model {
        int id PK
        int object_id FK "-> information_object"
        string filename
        string file_path
        string format "glb, gltf, obj, ply, stl, usdz"
        string format_version "auto: glTF 2.0"
        string compression "none, draco, meshopt, ktx2"
        int vertex_count "auto"
        int face_count "auto"
        int texture_count "auto"
        int animation_count "auto"
        bool has_materials "auto"
        string bounding_box "auto: minXYZ maxXYZ"
        string pbr_maps "auto"
        decimal real_width "1178"
        decimal real_height "1178"
        decimal real_depth "1178"
        string dimension_unit "1178 dropdown"
        string coordinate_system "1178 dropdown"
        string capture_method "1178 dropdown"
        string capture_device "1178"
        date capture_date "1178"
        decimal accuracy_mm "1178"
        string processing_software "1178"
        string model_author "1178"
        string derivation_note "1178"
        string model_license "1178 dropdown"
        string attribution "1178"
        bool auto_rotate "viewer"
        string camera_orbit "viewer"
        string field_of_view "viewer"
        bool ar_enabled "viewer"
        string poster_image
        string thumbnail
        bool is_primary
        bool is_public
        datetime created_at
    }
    object_3d_model_i18n {
        int model_id FK
        string culture PK
        string title
        text description
        string alt_text
    }
    object_3d_texture {
        int id PK
        int model_id FK
        string texture_type
        int width
        int height
    }
    object_3d_hotspot {
        int id PK
        int model_id FK
        string hotspot_type
        decimal position_x "PointSelector x"
        decimal position_y "PointSelector y"
        decimal position_z "PointSelector z"
        decimal normal_x
        decimal normal_y
        decimal normal_z
        string link_url
        bool is_visible
        int display_order
    }
    object_3d_hotspot_i18n {
        int hotspot_id FK
        string culture PK
        string title
        text description
    }
    object_3d_camera_bookmark {
        int id PK
        int model_id FK
        string name
        string camera_orbit
        string field_of_view
    }
    iiif_3d_manifest {
        int id PK
        int model_id FK
        longtext manifest_json
        string manifest_hash "sha256"
        timestamp generated_at
    }
    object_3d_audit_log {
        int id PK
        int model_id FK
        string action
        int user_id
        timestamp created_at
    }
```

## Key relationships

- **`information_object` 1 — N `object_3d_model`** (`object_id`): a record can have
  several 3D models; one is `is_primary`. (FK `ON DELETE CASCADE`.)
- A 3D file may instead arrive as a **`digital_object`** attached to the record;
  `Model3dRegistry` ensures such files also get an `object_3d_model` row (so they
  carry metadata). Both paths converge on `object_3d_model`.
- **`object_3d_model` 1 — N i18n / texture / hotspot / camera-bookmark / audit**,
  all keyed by `model_id` (`ON DELETE CASCADE`).
- **`object_3d_hotspot` 1 — N `object_3d_hotspot_i18n`** (`hotspot_id`). Hotspot
  `position_x/y/z` map directly to an IIIF **PointSelector** in the manifest.
- **`object_3d_model` 1 — 1 `iiif_3d_manifest`** (`model_id`): cached RC-aligned
  IIIF 3D manifest (regenerated on request; `manifest_hash` detects change).

## Column groups on `object_3d_model`

| Group | Columns (summary) |
|-------|-------------------|
| Identity / file | `id, object_id, filename, original_filename, file_path, file_size, mime_type, format` |
| Geometry (auto-extracted) | `format_version, compression, vertex_count, face_count, texture_count, animation_count, has_materials, has_rig, is_watertight, bounding_box, pbr_maps, texture_colorspace, lod_levels, is_lossless_master` |
| Real-world (#1178) | `real_width, real_height, real_depth, dimension_unit, scale_note, coordinate_system` |
| Capture paradata (#1178) | `capture_method, capture_device, capture_date, capture_operator, source_count, point_density, accuracy_mm, processing_software, processing_notes, georeference` |
| Provenance / rights (#1178) | `model_author, derivation_note, model_license, model_license_holder, attribution, alt_text` |
| Viewer | `auto_rotate, rotation_speed, camera_orbit, min_camera_orbit, max_camera_orbit, field_of_view, exposure, shadow_intensity, shadow_softness, environment_image, skybox_image, background_color` |
| AR | `ar_enabled, ar_scale, ar_placement` |
| Derivatives | `turntable_mp4_path, turntable_generated_at, poster_image, thumbnail` |
| Flags / audit | `is_primary, is_public, display_order, created_by, updated_by, created_at, updated_at` |

Controlled vocabularies (`dimension_unit`, `coordinate_system`, `capture_method`,
`compression`, `model_license`) are managed in the Dropdown Manager
(`ahg_dropdown`, taxonomies `model_3d_*`) — no ENUM columns.
