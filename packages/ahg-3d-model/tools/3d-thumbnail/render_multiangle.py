"""
Multi-angle renderer for 3D models using Blender.

Generates 6 views (front, back, left, right, top, detail) of a 3D model
for AI description and gallery display.

Usage:
    blender --background --python render_multiangle.py -- <input> <output_dir> [size]

@author Johan Pieterse <johan@theahg.co.za>
"""

import bpy
import mathutils
import sys
import os
import math


def clear_scene():
    bpy.ops.object.select_all(action='SELECT')
    bpy.ops.object.delete()
    # Remove orphan data
    for block in bpy.data.meshes:
        if block.users == 0:
            bpy.data.meshes.remove(block)


def import_model(filepath):
    """Import 3D model based on file extension."""
    ext = os.path.splitext(filepath)[1].lower()

    if ext in ['.glb', '.gltf']:
        bpy.ops.import_scene.gltf(filepath=filepath)
    elif ext == '.obj':
        if bpy.app.version >= (4, 0, 0):
            bpy.ops.wm.obj_import(filepath=filepath)
        else:
            bpy.ops.import_scene.obj(filepath=filepath)
    elif ext == '.stl':
        bpy.ops.import_mesh.stl(filepath=filepath)
    elif ext == '.fbx':
        bpy.ops.import_scene.fbx(filepath=filepath)
    elif ext == '.ply':
        bpy.ops.import_mesh.ply(filepath=filepath)
    elif ext == '.dae':
        bpy.ops.wm.collada_import(filepath=filepath)
    else:
        raise ValueError(f"Unsupported format: {ext}")

    imported = [obj for obj in bpy.context.selected_objects]
    if not imported:
        imported = [obj for obj in bpy.data.objects if obj.type == 'MESH']

    return imported


def setup_world():
    world = bpy.context.scene.world
    if not world:
        world = bpy.data.worlds.new("World")
        bpy.context.scene.world = world
    if hasattr(world, 'use_nodes'):
        world.use_nodes = True
        bg = world.node_tree.nodes.get('Background')
        if bg:
            bg.inputs[0].default_value = (0.92, 0.93, 0.95, 1)


def setup_lighting():
    # Key light (sun)
    bpy.ops.object.light_add(type='SUN', location=(5, -5, 10))
    key = bpy.context.object
    key.data.energy = 3

    # Fill light (area)
    bpy.ops.object.light_add(type='AREA', location=(-5, -3, 5))
    fill = bpy.context.object
    fill.data.energy = 100

    # Rim light for separation
    bpy.ops.object.light_add(type='POINT', location=(0, 5, 3))
    rim = bpy.context.object
    rim.data.energy = 200


def get_bounding_box(objects):
    """Calculate combined bounding box center and size."""
    min_co = [float('inf')] * 3
    max_co = [float('-inf')] * 3

    for obj in objects:
        if obj.type == 'MESH':
            for corner in obj.bound_box:
                world_corner = obj.matrix_world @ mathutils.Vector(corner)
                for i in range(3):
                    min_co[i] = min(min_co[i], world_corner[i])
                    max_co[i] = max(max_co[i], world_corner[i])

    if min_co[0] == float('inf'):
        return [0, 0, 0], 1.0

    center = [(min_co[i] + max_co[i]) / 2 for i in range(3)]
    size = max(max_co[i] - min_co[i] for i in range(3))
    return center, max(size, 0.001)


def position_camera(camera, center, size, azimuth_deg, elevation_deg):
    """Position camera at given azimuth/elevation around center."""
    distance = size * 2.5
    az = math.radians(azimuth_deg)
    el = math.radians(elevation_deg)

    x = center[0] + distance * math.cos(el) * math.sin(az)
    y = center[1] - distance * math.cos(el) * math.cos(az)
    z = center[2] + distance * math.sin(el)

    camera.location = (x, y, z)

    direction = mathutils.Vector(center) - camera.location
    rot_quat = direction.to_track_quat('-Z', 'Y')
    camera.rotation_euler = rot_quat.to_euler()


def render_view(output_path, width, height):
    """Render the current scene to a file."""
    scene = bpy.context.scene
    scene.render.resolution_x = width
    scene.render.resolution_y = height
    scene.render.resolution_percentage = 100
    scene.render.image_settings.file_format = 'PNG'
    scene.render.filepath = output_path
    scene.render.film_transparent = False

    # Try EEVEE_NEXT first (Blender 4.2+), fall back to EEVEE
    try:
        scene.render.engine = 'BLENDER_EEVEE_NEXT'
    except TypeError:
        scene.render.engine = 'BLENDER_EEVEE'

    bpy.ops.render.render(write_still=True)


# Camera angles: (name, azimuth, elevation)
VIEWS = [
    ('front',  0,   15),
    ('back',   180, 15),
    ('left',   270, 15),
    ('right',  90,  15),
    ('top',    0,   80),
    ('detail', 45,  35),
]


def main():
    argv = sys.argv
    try:
        idx = argv.index("--")
        argv = argv[idx + 1:]
    except ValueError:
        print("Usage: blender --background --python render_multiangle.py -- <input> <output_dir> [size]")
        sys.exit(1)

    if len(argv) < 2:
        print("Usage: blender --background --python render_multiangle.py -- <input> <output_dir> [size]")
        sys.exit(1)

    input_file = argv[0]
    output_dir = argv[1]
    size = int(argv[2]) if len(argv) > 2 else 1024

    if not os.path.exists(input_file):
        print(f"Error: File not found: {input_file}")
        sys.exit(1)

    os.makedirs(output_dir, exist_ok=True)

    print(f"Multi-angle render: {input_file} → {output_dir} ({size}x{size})")

    clear_scene()
    setup_world()
    setup_lighting()

    # Add camera
    bpy.ops.object.camera_add(location=(0, 0, 0))
    camera = bpy.context.object
    bpy.context.scene.camera = camera

    try:
        objects = import_model(input_file)
        print(f"Imported {len(objects)} objects")
    except Exception as e:
        print(f"Import error: {e}")
        sys.exit(1)

    center, obj_size = get_bounding_box(objects)
    print(f"Bounding box center={center}, size={obj_size:.3f}")

    rendered = 0
    for view_name, azimuth, elevation in VIEWS:
        output_path = os.path.join(output_dir, f"{view_name}.png")
        position_camera(camera, center, obj_size, azimuth, elevation)
        try:
            render_view(output_path, size, size)
            if os.path.exists(output_path) and os.path.getsize(output_path) > 500:
                print(f"  {view_name}: OK ({os.path.getsize(output_path)} bytes)")
                rendered += 1
            else:
                print(f"  {view_name}: FAILED (file missing or too small)")
        except Exception as e:
            print(f"  {view_name}: ERROR ({e})")

    print(f"Rendered {rendered}/{len(VIEWS)} views")
    sys.exit(0 if rendered > 0 else 1)


if __name__ == "__main__":
    main()
