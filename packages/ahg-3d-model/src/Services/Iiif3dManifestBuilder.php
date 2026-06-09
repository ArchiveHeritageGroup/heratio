<?php

/**
 * Iiif3dManifestBuilder - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 */

namespace Ahg3dModel\Services;

/**
 * #1180 - build an IIIF Presentation 3 manifest for a 3D model, aligned to the
 * IIIF 3D Technical Specification Group's model (release-candidate, Jan 2026,
 * folding into IIIF v4):
 *
 *   Manifest -> Canvas (with height/width/DEPTH = a 3D coordinate space)
 *     painting annotation: body = a "Model" content resource (glTF/GLB), target = Canvas
 *     painting annotation: a PerspectiveCamera resource
 *     painting annotation: an AmbientLight resource
 *   annotations: hotspots as Annotations targeting the Canvas via a PointSelector (x,y,z)
 *
 * Still provisional - the RC may refine property names; revisit on finalisation.
 */
class Iiif3dManifestBuilder
{
    /** Build the manifest array for a model row + its visible hotspots. */
    public function build(object $model, iterable $hotspots, string $baseUrl): array
    {
        $id = (int) $model->id;
        $manifestId = $baseUrl.'/iiif/3d/'.$id.'/manifest.json';
        $canvasId = $baseUrl.'/iiif/3d/'.$id.'/canvas/1';
        $modelUrl = $baseUrl.'/uploads/'.ltrim((string) $model->file_path, '/');

        [$w, $h, $d] = $this->canvasDimensions($model);

        $painting = [[
            'id' => $baseUrl.'/iiif/3d/'.$id.'/annotation/model',
            'type' => 'Annotation',
            'motivation' => 'painting',
            'body' => array_filter([
                'id' => $modelUrl,
                'type' => 'Model',
                'format' => $model->mime_type ?: 'model/gltf-binary',
                'label' => ['en' => [$model->model_title ?? 'Model']],
            ]),
            'target' => $canvasId,
        ]];

        // Camera (PerspectiveCamera) painted onto the canvas.
        $painting[] = [
            'id' => $baseUrl.'/iiif/3d/'.$id.'/annotation/camera',
            'type' => 'Annotation',
            'motivation' => 'painting',
            'body' => array_filter([
                'id' => $baseUrl.'/iiif/3d/'.$id.'/camera/1',
                'type' => 'PerspectiveCamera',
                'fieldOfView' => $this->degrees($model->field_of_view ?? '30deg'),
            ]),
            'target' => $canvasId,
        ];

        // Ambient light painted onto the canvas (intensity from exposure).
        $painting[] = [
            'id' => $baseUrl.'/iiif/3d/'.$id.'/annotation/light',
            'type' => 'Annotation',
            'motivation' => 'painting',
            'body' => [
                'id' => $baseUrl.'/iiif/3d/'.$id.'/light/1',
                'type' => 'AmbientLight',
                'color' => '#ffffff',
                'intensity' => (float) ($model->exposure ?? 1.0),
            ],
            'target' => $canvasId,
        ];

        $manifest = [
            '@context' => [
                'http://iiif.io/api/presentation/3/context.json',
                'http://iiif.io/api/extension/3d/context.json',
            ],
            'id' => $manifestId,
            'type' => 'Manifest',
            'label' => ['en' => [$model->model_title ?: 'Untitled 3D model']],
            'metadata' => $this->metadata($model),
            'items' => [[
                'id' => $canvasId,
                'type' => 'Canvas',
                'height' => $h,
                'width' => $w,
                'depth' => $d,
                'items' => [[
                    'id' => $canvasId.'/page',
                    'type' => 'AnnotationPage',
                    'items' => $painting,
                ]],
            ]],
        ];

        if (! empty($model->description)) {
            $manifest['summary'] = ['en' => [$model->description]];
        }
        if (! empty($model->model_license)) {
            $manifest['rights'] = $this->rightsUri((string) $model->model_license);
        }
        if (! empty($model->attribution)) {
            $manifest['requiredStatement'] = [
                'label' => ['en' => ['Attribution']],
                'value' => ['en' => [(string) $model->attribution]],
            ];
        }

        // Hotspots -> Annotations on the canvas via a PointSelector.
        $annos = [];
        foreach ($hotspots as $hs) {
            $text = trim((string) ($hs->hotspot_title ?? '').($hs->hotspot_description ? ': '.$hs->hotspot_description : ''));
            $annos[] = array_filter([
                'id' => $baseUrl.'/iiif/3d/'.$id.'/hotspot/'.$hs->id,
                'type' => 'Annotation',
                'motivation' => $hs->link_url ? 'linking' : 'commenting',
                'body' => $hs->link_url
                    ? ['id' => $hs->link_url, 'type' => 'Text']
                    : ['type' => 'TextualBody', 'value' => $text, 'format' => 'text/plain'],
                'target' => [
                    'type' => 'SpecificResource',
                    'source' => $canvasId,
                    'selector' => array_filter([
                        'type' => 'PointSelector',
                        'x' => (float) $hs->position_x,
                        'y' => (float) $hs->position_y,
                        'z' => (float) $hs->position_z,
                    ], fn ($v) => $v !== null),
                ],
            ]);
        }
        if ($annos) {
            $manifest['annotations'] = [[
                'id' => $baseUrl.'/iiif/3d/'.$id.'/annotations/1',
                'type' => 'AnnotationPage',
                'items' => $annos,
            ]];
        }

        // Viewer hints kept under a clearly-namespaced extension (non-normative).
        $manifest['heratio:viewer'] = array_filter([
            'autoRotate' => (bool) ($model->auto_rotate ?? false),
            'rotationSpeed' => (int) ($model->rotation_speed ?? 0),
            'cameraOrbit' => $model->camera_orbit ?? null,
            'backgroundColor' => $model->background_color ?? null,
            'arEnabled' => (bool) ($model->ar_enabled ?? false),
        ], fn ($v) => $v !== null && $v !== '');

        return $manifest;
    }

    /** Canvas extents from the model-space bounding box (rounded up, min 1). */
    private function canvasDimensions(object $model): array
    {
        $bb = (string) ($model->bounding_box ?? '');
        if (preg_match('/^\s*(-?[\d.eE+]+),(-?[\d.eE+]+),(-?[\d.eE+]+)\s+(-?[\d.eE+]+),(-?[\d.eE+]+),(-?[\d.eE+]+)\s*$/', $bb, $m)) {
            $w = max(1, (int) ceil(abs((float) $m[4] - (float) $m[1])));
            $h = max(1, (int) ceil(abs((float) $m[5] - (float) $m[2])));
            $d = max(1, (int) ceil(abs((float) $m[6] - (float) $m[3])));

            return [$w, $h, $d];
        }

        return [1000, 1000, 1000];
    }

    private function degrees(string $v): float
    {
        return (float) preg_replace('/[^0-9.]/', '', $v) ?: 30.0;
    }

    private function metadata(object $model): array
    {
        $pairs = [
            ['Format', $model->format_version ?: strtoupper((string) ($model->format ?? ''))],
            ['Dimensions', $this->dims($model)],
            ['Vertices', $model->vertex_count ? number_format((int) $model->vertex_count) : null],
            ['Faces', $model->face_count ? number_format((int) $model->face_count) : null],
            ['Compression', $model->compression ?? null],
            ['Capture method', $model->capture_method ?? null],
            ['Model author', $model->model_author ?? null],
        ];
        $out = [];
        foreach ($pairs as [$label, $value]) {
            if ($value !== null && $value !== '') {
                $out[] = ['label' => ['en' => [$label]], 'value' => ['en' => [(string) $value]]];
            }
        }

        return $out;
    }

    private function dims(object $model): ?string
    {
        if (! empty($model->real_width) || ! empty($model->real_height) || ! empty($model->real_depth)) {
            return sprintf('%s x %s x %s %s', $model->real_width ?? '?', $model->real_height ?? '?', $model->real_depth ?? '?', $model->dimension_unit ?? '');
        }

        return null;
    }

    /** Map a licence code to a rights URI where one is well-known (else omit). */
    private function rightsUri(string $code): ?string
    {
        return [
            'cc0' => 'http://creativecommons.org/publicdomain/zero/1.0/',
            'cc_by' => 'http://creativecommons.org/licenses/by/4.0/',
            'cc_by_sa' => 'http://creativecommons.org/licenses/by-sa/4.0/',
            'cc_by_nc' => 'http://creativecommons.org/licenses/by-nc/4.0/',
            'cc_by_nd' => 'http://creativecommons.org/licenses/by-nd/4.0/',
            'public_domain' => 'http://creativecommons.org/publicdomain/mark/1.0/',
        ][$code] ?? null;
    }
}
