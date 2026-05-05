#!/usr/bin/env python3
"""
PDF Redaction Service for AtoM AHG Privacy Plugin

Redacts specified text from PDF documents while preserving document structure.
Uses PyMuPDF (fitz) for efficient text finding and redaction.

Usage:
    python3 pdf_redactor.py <input_pdf> <output_pdf> <terms_json>

    terms_json: JSON array of terms to redact, e.g., '["John Smith", "john@email.com"]'

Or as a module:
    from pdf_redactor import redact_pdf
    redact_pdf('/path/to/input.pdf', '/path/to/output.pdf', ['term1', 'term2'])
"""

import sys
import json
import os
import fitz  # PyMuPDF
import re
from typing import List, Optional, Dict, Any
import tempfile
import shutil


class PdfRedactor:
    """PDF Redaction Service"""

    # Redaction styling
    REDACT_FILL_COLOR = (0, 0, 0)  # Black fill
    REDACT_TEXT_COLOR = (1, 1, 1)  # White text
    REDACT_FONT_SIZE = 8

    def __init__(self, case_sensitive: bool = False):
        """
        Initialize the PDF redactor.

        Args:
            case_sensitive: Whether term matching should be case-sensitive
        """
        self.case_sensitive = case_sensitive
        self.stats = {
            'pages_processed': 0,
            'terms_found': 0,
            'redactions_applied': 0
        }

    def redact_pdf(
        self,
        input_path: str,
        output_path: str,
        terms: List[str],
        replacement_text: str = "[REDACTED]"
    ) -> Dict[str, Any]:
        """
        Redact specified terms from a PDF document.

        Args:
            input_path: Path to the input PDF
            output_path: Path for the redacted output PDF
            terms: List of text strings to redact
            replacement_text: Text to show in place of redacted content

        Returns:
            Dictionary with redaction statistics
        """
        if not os.path.exists(input_path):
            raise FileNotFoundError(f"Input PDF not found: {input_path}")

        if not terms:
            # No terms to redact, just copy the file
            shutil.copy(input_path, output_path)
            return {'success': True, 'redactions': 0, 'message': 'No terms to redact'}

        # Reset stats
        self.stats = {
            'pages_processed': 0,
            'terms_found': 0,
            'redactions_applied': 0,
            'terms_by_page': {}
        }

        try:
            # Open the PDF
            doc = fitz.open(input_path)

            # Process each page
            for page_num, page in enumerate(doc):
                self.stats['pages_processed'] += 1
                page_redactions = 0

                for term in terms:
                    if not term or len(term.strip()) == 0:
                        continue

                    # Find all instances of the term
                    flags = 0 if self.case_sensitive else fitz.TEXT_PRESERVE_WHITESPACE
                    text_instances = page.search_for(term, flags=flags)

                    if text_instances:
                        self.stats['terms_found'] += len(text_instances)

                        for rect in text_instances:
                            # Add redaction annotation
                            page.add_redact_annot(
                                rect,
                                text=replacement_text,
                                fontsize=self.REDACT_FONT_SIZE,
                                fill=self.REDACT_FILL_COLOR,
                                text_color=self.REDACT_TEXT_COLOR
                            )
                            page_redactions += 1

                # Apply all redactions for this page
                if page_redactions > 0:
                    page.apply_redactions()
                    self.stats['redactions_applied'] += page_redactions
                    self.stats['terms_by_page'][page_num + 1] = page_redactions

            # Save the redacted PDF
            doc.save(output_path, garbage=4, deflate=True)
            doc.close()

            return {
                'success': True,
                'input': input_path,
                'output': output_path,
                'pages_processed': self.stats['pages_processed'],
                'terms_searched': len(terms),
                'instances_found': self.stats['terms_found'],
                'redactions_applied': self.stats['redactions_applied'],
                'terms_by_page': self.stats['terms_by_page']
            }

        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'input': input_path
            }

    def redact_pdf_to_bytes(
        self,
        input_path: str,
        terms: List[str],
        replacement_text: str = "[REDACTED]"
    ) -> Optional[bytes]:
        """
        Redact PDF and return as bytes (for streaming).

        Args:
            input_path: Path to the input PDF
            terms: List of text strings to redact
            replacement_text: Text to show in place of redacted content

        Returns:
            Redacted PDF as bytes, or None on error
        """
        with tempfile.NamedTemporaryFile(suffix='.pdf', delete=False) as tmp:
            tmp_path = tmp.name

        try:
            result = self.redact_pdf(input_path, tmp_path, terms, replacement_text)
            if result['success']:
                with open(tmp_path, 'rb') as f:
                    return f.read()
            return None
        finally:
            if os.path.exists(tmp_path):
                os.unlink(tmp_path)

    def hex_to_rgb(self, hex_color: str) -> tuple:
        """Convert hex color to RGB tuple (0-1 range for PyMuPDF)"""
        hex_color = hex_color.lstrip('#')
        if len(hex_color) != 6:
            return (0, 0, 0)  # Default to black
        r, g, b = tuple(int(hex_color[i:i+2], 16) for i in (0, 2, 4))
        return (r / 255, g / 255, b / 255)

    def redact_pdf_regions(
        self,
        input_path: str,
        output_path: str,
        regions: List[Dict[str, Any]]
    ) -> Dict[str, Any]:
        """
        Redact specified regions (coordinate-based) from a PDF document.

        Args:
            input_path: Path to the input PDF
            output_path: Path for the redacted output PDF
            regions: List of region dictionaries with:
                - page: Page number (1-indexed)
                - x, y, width, height: Coordinates (normalized 0-1 or absolute)
                - normalized: Whether coordinates are normalized (default True)
                - color: Hex color for the redaction box (default #000000)

        Returns:
            Dictionary with redaction statistics
        """
        if not os.path.exists(input_path):
            return {
                'success': False,
                'error': f'Input PDF not found: {input_path}'
            }

        if not regions:
            # No regions to redact, just copy the file
            shutil.copy(input_path, output_path)
            return {'success': True, 'redactions': 0, 'message': 'No regions to redact'}

        # Reset stats
        stats = {
            'pages_processed': set(),
            'regions_applied': 0,
            'regions_by_page': {}
        }

        try:
            # Open the PDF
            doc = fitz.open(input_path)
            total_pages = len(doc)

            # Group regions by page
            regions_by_page = {}
            for region in regions:
                page_num = region.get('page', 1)
                if page_num < 1 or page_num > total_pages:
                    continue
                if page_num not in regions_by_page:
                    regions_by_page[page_num] = []
                regions_by_page[page_num].append(region)

            # Process each page with regions
            for page_num, page_regions in regions_by_page.items():
                page = doc[page_num - 1]  # 0-indexed
                page_width = page.rect.width
                page_height = page.rect.height
                page_redactions = 0

                for region in page_regions:
                    try:
                        # Get coordinates
                        x = float(region.get('x', 0))
                        y = float(region.get('y', 0))
                        w = float(region.get('width', 0))
                        h = float(region.get('height', 0))
                        color = region.get('color', '#000000')
                        normalized = region.get('normalized', True)

                        # Convert normalized coordinates to absolute
                        if normalized:
                            x = x * page_width
                            y = y * page_height
                            w = w * page_width
                            h = h * page_height

                        # Create rectangle
                        rect = fitz.Rect(x, y, x + w, y + h)

                        # Validate rectangle
                        if rect.is_empty or rect.is_infinite:
                            continue

                        # Clip to page bounds
                        rect = rect & page.rect

                        # Parse color
                        fill_color = self.hex_to_rgb(color)

                        # Add redaction annotation
                        page.add_redact_annot(
                            rect,
                            text="",
                            fill=fill_color,
                            text_color=(1, 1, 1)
                        )
                        page_redactions += 1

                    except Exception as e:
                        print(f"Warning: Failed to apply region: {e}", file=sys.stderr)
                        continue

                # Apply all redactions for this page
                if page_redactions > 0:
                    page.apply_redactions()
                    stats['pages_processed'].add(page_num)
                    stats['regions_applied'] += page_redactions
                    stats['regions_by_page'][page_num] = page_redactions

            # Save the redacted PDF
            doc.save(output_path, garbage=4, deflate=True)
            doc.close()

            return {
                'success': True,
                'input': input_path,
                'output': output_path,
                'total_pages': total_pages,
                'pages_processed': len(stats['pages_processed']),
                'regions_requested': len(regions),
                'regions_applied': stats['regions_applied'],
                'regions_by_page': stats['regions_by_page']
            }

        except Exception as e:
            return {
                'success': False,
                'error': str(e),
                'input': input_path
            }


def redact_pdf(
    input_path: str,
    output_path: str,
    terms: List[str],
    replacement_text: str = "[REDACTED]",
    case_sensitive: bool = False
) -> Dict[str, Any]:
    """
    Convenience function to redact a PDF by text terms.

    Args:
        input_path: Path to input PDF
        output_path: Path for output PDF
        terms: List of terms to redact
        replacement_text: Replacement text for redacted areas
        case_sensitive: Whether matching is case-sensitive

    Returns:
        Dictionary with redaction results
    """
    redactor = PdfRedactor(case_sensitive=case_sensitive)
    return redactor.redact_pdf(input_path, output_path, terms, replacement_text)


def redact_pdf_regions(
    input_path: str,
    output_path: str,
    regions: List[Dict[str, Any]]
) -> Dict[str, Any]:
    """
    Convenience function to redact a PDF by coordinate regions.

    Args:
        input_path: Path to input PDF
        output_path: Path for output PDF
        regions: List of region dictionaries with coordinates

    Returns:
        Dictionary with redaction results
    """
    redactor = PdfRedactor()
    return redactor.redact_pdf_regions(input_path, output_path, regions)


def main():
    """CLI entry point"""
    if len(sys.argv) < 4:
        print(json.dumps({
            'success': False,
            'error': 'Usage: pdf_redactor.py <input_pdf> <output_pdf> <terms_or_regions_json> [--regions]'
        }))
        sys.exit(1)

    input_pdf = sys.argv[1]
    output_pdf = sys.argv[2]

    # Check for --regions flag
    use_regions = '--regions' in sys.argv

    try:
        data = json.loads(sys.argv[3])
        if not isinstance(data, list):
            data = [data]
    except json.JSONDecodeError as e:
        print(json.dumps({
            'success': False,
            'error': f'Invalid JSON: {e}'
        }))
        sys.exit(1)

    if use_regions:
        # Region-based redaction
        result = redact_pdf_regions(input_pdf, output_pdf, data)
    else:
        # Text-based redaction (legacy)
        replacement_text = "[REDACTED]"
        # Find replacement text if provided (not --regions)
        for i, arg in enumerate(sys.argv):
            if arg not in ('--regions',) and i > 3:
                replacement_text = arg
                break
        result = redact_pdf(input_pdf, output_pdf, data, replacement_text)

    print(json.dumps(result))
    sys.exit(0 if result['success'] else 1)


if __name__ == '__main__':
    main()
