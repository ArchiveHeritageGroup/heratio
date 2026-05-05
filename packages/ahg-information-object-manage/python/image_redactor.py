#!/usr/bin/env python3
"""
Image Redaction Service for AtoM AHG Privacy Plugin

Applies coordinate-based redaction boxes to images (JPG, PNG, TIFF).
Uses Pillow for image manipulation.

Usage:
    python3 image_redactor.py <input_image> <output_image> <regions_json>

    regions_json: JSON array of regions, e.g.:
    '[{"x": 0.1, "y": 0.2, "width": 0.3, "height": 0.1, "color": "#000000", "normalized": true}]'

Or as a module:
    from image_redactor import redact_image
    redact_image('/path/to/input.jpg', '/path/to/output.jpg', regions)
"""

import sys
import json
import os
from typing import List, Dict, Any, Tuple
from PIL import Image, ImageDraw


class ImageRedactor:
    """Image Redaction Service using Pillow"""

    def __init__(self):
        self.stats = {
            'regions_applied': 0,
            'original_size': (0, 0),
            'output_size': (0, 0)
        }

    def hex_to_rgb(self, hex_color: str) -> Tuple[int, int, int]:
        """Convert hex color to RGB tuple"""
        hex_color = hex_color.lstrip('#')
        if len(hex_color) != 6:
            return (0, 0, 0)  # Default to black
        return tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))

    def redact_image(
        self,
        input_path: str,
        output_path: str,
        regions: List[Dict[str, Any]]
    ) -> Dict[str, Any]:
        """
        Apply redaction boxes to an image.

        Args:
            input_path: Path to the input image
            output_path: Path for the redacted output image
            regions: List of region dictionaries with coordinates

        Returns:
            Dictionary with redaction results
        """
        if not os.path.exists(input_path):
            return {
                'success': False,
                'error': f'Input image not found: {input_path}'
            }

        if not regions:
            # No regions to redact, just copy the file
            try:
                img = Image.open(input_path)
                img.save(output_path, quality=95)
                return {
                    'success': True,
                    'redactions': 0,
                    'message': 'No regions to redact'
                }
            except Exception as e:
                return {'success': False, 'error': str(e)}

        # Reset stats
        self.stats = {
            'regions_applied': 0,
            'original_size': (0, 0),
            'output_size': (0, 0)
        }

        try:
            # Open the image
            img = Image.open(input_path)

            # Convert to RGB if necessary (for RGBA or palette images)
            if img.mode in ('RGBA', 'P', 'LA'):
                background = Image.new('RGB', img.size, (255, 255, 255))
                if img.mode == 'P':
                    img = img.convert('RGBA')
                background.paste(img, mask=img.split()[-1] if img.mode == 'RGBA' else None)
                img = background
            elif img.mode != 'RGB':
                img = img.convert('RGB')

            width, height = img.size
            self.stats['original_size'] = (width, height)

            # Create drawing context
            draw = ImageDraw.Draw(img)

            # Apply each region
            for region in regions:
                try:
                    # Get coordinates
                    x = region.get('x', 0)
                    y = region.get('y', 0)
                    w = region.get('width', 0)
                    h = region.get('height', 0)
                    color = region.get('color', '#000000')
                    normalized = region.get('normalized', True)

                    # Convert normalized coordinates to pixels
                    if normalized:
                        x = int(x * width)
                        y = int(y * height)
                        w = int(w * width)
                        h = int(h * height)
                    else:
                        x = int(x)
                        y = int(y)
                        w = int(w)
                        h = int(h)

                    # Ensure valid coordinates
                    x = max(0, min(x, width))
                    y = max(0, min(y, height))
                    x2 = max(0, min(x + w, width))
                    y2 = max(0, min(y + h, height))

                    if x2 <= x or y2 <= y:
                        continue  # Invalid region

                    # Draw filled rectangle
                    fill_color = self.hex_to_rgb(color)
                    draw.rectangle([x, y, x2, y2], fill=fill_color)

                    self.stats['regions_applied'] += 1

                except Exception as e:
                    print(f"Warning: Failed to apply region: {e}", file=sys.stderr)
                    continue

            # Save the output
            self.stats['output_size'] = img.size

            # Determine output format from extension
            ext = os.path.splitext(output_path)[1].lower()
            if ext in ('.jpg', '.jpeg'):
                img.save(output_path, 'JPEG', quality=95)
            elif ext == '.png':
                img.save(output_path, 'PNG')
            elif ext in ('.tif', '.tiff'):
                img.save(output_path, 'TIFF')
            else:
                img.save(output_path, quality=95)

            return {
                'success': True,
                'input': input_path,
                'output': output_path,
                'original_size': self.stats['original_size'],
                'regions_applied': self.stats['regions_applied'],
                'regions_requested': len(regions)
            }

        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'input': input_path
            }


def redact_image(
    input_path: str,
    output_path: str,
    regions: List[Dict[str, Any]]
) -> Dict[str, Any]:
    """
    Convenience function to redact an image.

    Args:
        input_path: Path to input image
        output_path: Path for output image
        regions: List of region dictionaries

    Returns:
        Dictionary with redaction results
    """
    redactor = ImageRedactor()
    return redactor.redact_image(input_path, output_path, regions)


def main():
    """CLI entry point"""
    if len(sys.argv) < 4:
        print(json.dumps({
            'success': False,
            'error': 'Usage: image_redactor.py <input_image> <output_image> <regions_json>'
        }))
        sys.exit(1)

    input_image = sys.argv[1]
    output_image = sys.argv[2]

    try:
        regions = json.loads(sys.argv[3])
        if not isinstance(regions, list):
            regions = [regions]
    except json.JSONDecodeError as e:
        print(json.dumps({
            'success': False,
            'error': f'Invalid JSON for regions: {e}'
        }))
        sys.exit(1)

    result = redact_image(input_image, output_image, regions)
    print(json.dumps(result))

    sys.exit(0 if result['success'] else 1)


if __name__ == '__main__':
    main()
