#!/usr/bin/env python3
"""
ASCII Wireframe to PNG Image Converter for Heratio Documentation
Converts ASCII art wireframes in markdown files to PNG images.
"""

import os
import re
import hashlib
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

def extract_ascii_blocks(content):
    """Extract ASCII art blocks from markdown content."""
    blocks = []
    # Split content by code block markers
    parts = content.split('```')
    for i, part in enumerate(parts):
        # Check if this part contains ASCII box-drawing characters
        if '┌' in part and ('─' in part or '│' in part or '└' in part):
            # Clean up and add (skip first/last line which may be language hints)
            lines = part.strip().split('\n')
            # Filter out first line if it's a language hint
            if lines and lines[0] and not lines[0].startswith('┌'):
                lines = lines[1:]
            if lines:
                block = '\n'.join(lines)
                # Only add if it looks like a wireframe
                if '┌' in block or '└' in block:
                    blocks.append(block)
    return blocks

def ascii_to_image(ascii_art, output_path, font_size=12, padding=20):
    """Convert ASCII art to PNG image."""
    lines = ascii_art.strip().split('\n')
    
    # Calculate dimensions
    max_width = max(len(line) for line in lines)
    max_height = len(lines)
    
    # Try to use a monospace font, fall back to default
    try:
        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf", font_size)
    except:
        try:
            font = ImageFont.truetype("/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf", font_size)
        except:
            font = ImageFont.load_default()
    
    # Get character dimensions
    char_width = font.getbbox('M')[2]
    char_height = font.getbbox('M')[3] + 4
    
    # Image dimensions
    img_width = max_width * char_width + padding * 2
    img_height = max_height * char_height + padding * 2
    
    # Create image with light background
    img = Image.new('RGB', (img_width, img_height), color='#f8f9fa')
    draw = ImageDraw.Draw(img)
    
    # Draw each line
    y = padding
    for line in lines:
        draw.text((padding, y), line, fill='#212529', font=font)
        y += char_height
    
    # Add subtle border
    draw.rectangle([0, 0, img_width-1, img_height-1], outline='#dee2e6', width=1)
    
    img.save(output_path, 'PNG')
    return output_path

def process_markdown_file(md_path, output_dir):
    """Process a markdown file and convert wireframes to images and insert references."""
    with open(md_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Ensure output directory exists
    output_dir = Path(output_dir)
    output_dir.mkdir(parents=True, exist_ok=True)
    
    # Split by code blocks and process
    parts = content.split('```')
    new_parts = []
    generated = []
    
    for part in parts:
        if '┌' in part and ('─' in part or '│' in part or '└' in part):
            lines = part.strip().split('\n')
            # Filter out first line if it's a language hint
            if lines and lines[0] and not lines[0].startswith('┌'):
                lines = lines[1:]
            if lines:
                block = '\n'.join(lines)
                if '┌' in block or '└' in block:
                    # Generate image
                    content_hash = hashlib.md5(block.encode()).hexdigest()[:8]
                    filename = f"wireframe_{content_hash}.png"
                    output_path = output_dir / filename
                    
                    if not output_path.exists():
                        ascii_to_image(block, output_path)
                    
                    generated.append(filename)
                    # Add image reference AFTER the wireframe block
                    image_ref = f"\n![wireframe](./images/wireframes/{filename})\n"
                    part = part.rstrip() + image_ref
        
        new_parts.append(part)
    
    # Write updated content back
    if generated:
        new_content = '```'.join(new_parts)
        with open(md_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
    
    return generated

def main():
    docs_dir = Path("/usr/share/nginx/heratio/docs")
    output_dir = docs_dir / "images" / "wireframes"
    output_dir.mkdir(parents=True, exist_ok=True)
    
    # Process all markdown files
    md_files = list(docs_dir.glob("*.md")) + list(docs_dir.glob("technical/*.md"))
    
    print(f"Processing {len(md_files)} markdown files...")
    
    for md_file in md_files:
        try:
            results = process_markdown_file(md_file, output_dir)
            if results:
                print(f"✓ {md_file.name}: {len(results)} wireframe(s)")
        except Exception as e:
            print(f"✗ {md_file.name}: {e}")
    
    print(f"\nWireframe images saved to: {output_dir}")

if __name__ == "__main__":
    main()
