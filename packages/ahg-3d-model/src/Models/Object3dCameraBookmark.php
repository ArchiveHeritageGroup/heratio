<?php

namespace Ahg3dModel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Camera bookmark for a 3D model.
 *
 * One row per saved viewpoint. user_id NULL means "shared" — visible to
 * everybody who can see the model. Otherwise the bookmark belongs to the
 * user that created it.
 *
 * Fields are exactly what Google Model Viewer's `camera-orbit`,
 * `camera-target` and `field-of-view` attributes consume.
 */
class Object3dCameraBookmark extends Model
{
    protected $table = 'object_3d_camera_bookmark';

    protected $fillable = [
        'object_3d_id',
        'user_id',
        'name',
        'camera_orbit',
        'camera_target',
        'field_of_view',
        'is_default',
    ];

    protected $casts = [
        'object_3d_id' => 'integer',
        'user_id' => 'integer',
        'is_default' => 'boolean',
    ];
}
