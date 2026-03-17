import bpy
import mathutils
import sys
import os
import math

def clear_scene():
    bpy.ops.object.select_all(action='SELECT')
    bpy.ops.object.delete()

def setup_camera():
    bpy.ops.object.camera_add(location=(3, -3, 2))
    camera = bpy.context.object
    camera.rotation_euler = (math.radians(65), 0, math.radians(45))
    bpy.context.scene.camera = camera
    return camera

def setup_lighting():
    bpy.ops.object.light_add(type='SUN', location=(5, -5, 10))
    key = bpy.context.object
    key.data.energy = 3
    
    bpy.ops.object.light_add(type='AREA', location=(-5, -3, 5))
    fill = bpy.context.object
    fill.data.energy = 100

def setup_world():
    world = bpy.context.scene.world
    if not world:
        world = bpy.data.worlds.new("World")
        bpy.context.scene.world = world
    if hasattr(world, 'use_nodes'):
        world.use_nodes = True
        bg = world.node_tree.nodes.get('Background')
        if bg:
            bg.inputs[0].default_value = (0.9, 0.92, 0.95, 1)

def import_model(filepath):
    """Import 3D model based on file extension"""
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

def frame_camera_to_objects(camera, objects):
    """Position camera to frame all objects"""
    if not objects:
        return
    
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
        return
    
    center = [(min_co[i] + max_co[i]) / 2 for i in range(3)]
    size = max(max_co[i] - min_co[i] for i in range(3))
    
    distance = size * 2.5
    camera.location = (
        center[0] + distance * 0.7,
        center[1] - distance * 0.7,
        center[2] + distance * 0.5
    )
    
    direction = mathutils.Vector(center) - camera.location
    rot_quat = direction.to_track_quat('-Z', 'Y')
    camera.rotation_euler = rot_quat.to_euler()

def render_thumbnail(output_path, width=512, height=512):
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

def main():
    argv = sys.argv
    argv = argv[argv.index("--") + 1:]
    
    if len(argv) < 2:
        print("Usage: blender --background --python blender_thumbnail.py -- <input> <output.png> [width] [height]")
        sys.exit(1)
    
    input_file = argv[0]
    output_file = argv[1]
    width = int(argv[2]) if len(argv) > 2 else 512
    height = int(argv[3]) if len(argv) > 3 else 512
    
    if not os.path.exists(input_file):
        print(f"Error: File not found: {input_file}")
        sys.exit(1)
    
    print(f"Generating thumbnail for: {input_file}")
    
    clear_scene()
    setup_world()
    setup_lighting()
    camera = setup_camera()
    
    try:
        objects = import_model(input_file)
        print(f"Imported {len(objects)} objects")
        frame_camera_to_objects(camera, objects)
        render_thumbnail(output_file, width, height)
        print(f"Thumbnail saved to: {output_file}")
    except Exception as e:
        print(f"Import error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
