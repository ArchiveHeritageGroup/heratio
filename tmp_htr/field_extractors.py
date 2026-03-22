"""
Document-type-specific field extraction for South African vital records.
Uses TrOCR to perform OCR on image regions and maps results to structured fields.
"""

import logging
from typing import Dict, List, Optional, Tuple
from PIL import Image

logger = logging.getLogger("ahg-htr")

# Field definitions per document type with approximate relative regions
# Regions are (x_pct, y_pct, w_pct, h_pct) as percentages of image dimensions
BIRTH_FIELDS = [
    {"name": "registration_number", "label": "Registration Number", "region": (0.60, 0.02, 0.38, 0.06)},
    {"name": "surname", "label": "Surname", "region": (0.25, 0.15, 0.70, 0.06)},
    {"name": "first_names", "label": "First Names", "region": (0.25, 0.22, 0.70, 0.06)},
    {"name": "date_of_birth", "label": "Date of Birth", "region": (0.25, 0.29, 0.40, 0.06)},
    {"name": "place_of_birth", "label": "Place of Birth", "region": (0.25, 0.36, 0.70, 0.06)},
    {"name": "father_name", "label": "Father's Name", "region": (0.25, 0.50, 0.70, 0.06)},
    {"name": "mother_name", "label": "Mother's Name", "region": (0.25, 0.57, 0.70, 0.06)},
    {"name": "district", "label": "District", "region": (0.25, 0.43, 0.70, 0.06)},
]

DEATH_FIELDS = [
    {"name": "registration_number", "label": "Registration Number", "region": (0.60, 0.02, 0.38, 0.06)},
    {"name": "surname", "label": "Surname", "region": (0.25, 0.15, 0.70, 0.06)},
    {"name": "first_names", "label": "First Names", "region": (0.25, 0.22, 0.70, 0.06)},
    {"name": "date_of_death", "label": "Date of Death", "region": (0.25, 0.29, 0.40, 0.06)},
    {"name": "place_of_death", "label": "Place of Death", "region": (0.25, 0.36, 0.70, 0.06)},
    {"name": "cause_of_death", "label": "Cause of Death", "region": (0.25, 0.50, 0.70, 0.10)},
    {"name": "age", "label": "Age", "region": (0.25, 0.43, 0.30, 0.06)},
]

MARRIAGE_FIELDS = [
    {"name": "registration_number", "label": "Registration Number", "region": (0.60, 0.02, 0.38, 0.06)},
    {"name": "groom_surname", "label": "Groom Surname", "region": (0.25, 0.15, 0.70, 0.06)},
    {"name": "groom_first_names", "label": "Groom First Names", "region": (0.25, 0.22, 0.70, 0.06)},
    {"name": "bride_surname", "label": "Bride Surname", "region": (0.25, 0.36, 0.70, 0.06)},
    {"name": "bride_first_names", "label": "Bride First Names", "region": (0.25, 0.43, 0.70, 0.06)},
    {"name": "date_of_marriage", "label": "Date of Marriage", "region": (0.25, 0.29, 0.40, 0.06)},
    {"name": "place_of_marriage", "label": "Place of Marriage", "region": (0.25, 0.57, 0.70, 0.06)},
    {"name": "witness_1", "label": "Witness 1", "region": (0.25, 0.70, 0.70, 0.06)},
    {"name": "witness_2", "label": "Witness 2", "region": (0.25, 0.77, 0.70, 0.06)},
]

DOC_TYPE_FIELDS = {
    "type_a": BIRTH_FIELDS,
    "type_b": DEATH_FIELDS,
    "type_c": MARRIAGE_FIELDS,
}


def get_fields_for_type(doc_type: str) -> List[Dict]:
    """Return field definitions for a given document type."""
    return DOC_TYPE_FIELDS.get(doc_type, [])


def crop_region(image: Image.Image, region: Tuple[float, float, float, float]) -> Image.Image:
    """Crop a region from an image given relative coordinates (x_pct, y_pct, w_pct, h_pct)."""
    w, h = image.size
    x_pct, y_pct, w_pct, h_pct = region
    left = int(x_pct * w)
    top = int(y_pct * h)
    right = int((x_pct + w_pct) * w)
    bottom = int((y_pct + h_pct) * h)
    # Clamp to image bounds
    left = max(0, min(left, w))
    top = max(0, min(top, h))
    right = max(0, min(right, w))
    bottom = max(0, min(bottom, h))
    if right <= left or bottom <= top:
        return image
    return image.crop((left, top, right, bottom))


def detect_document_type(image: Image.Image, ocr_func) -> str:
    """
    Attempt to auto-detect document type by OCR-ing the header region
    and looking for keywords.
    """
    w, h = image.size
    # Crop top 15% of image for header text
    header = image.crop((0, 0, w, int(h * 0.15)))
    try:
        header_text = ocr_func(header).lower()
    except Exception:
        logger.warning("Auto-detect failed, defaulting to type_a (birth)")
        return "type_a"

    if "death" in header_text or "sterfte" in header_text or "overlijden" in header_text:
        return "type_b"
    elif "marriage" in header_text or "huwelik" in header_text or "huwelijk" in header_text:
        return "type_c"
    elif "birth" in header_text or "geboorte" in header_text:
        return "type_a"
    else:
        logger.info("Could not determine document type from header, defaulting to type_a")
        return "type_a"


def extract_fields(image: Image.Image, doc_type: str, ocr_func) -> Tuple[str, List[Dict]]:
    """
    Extract all fields from an image for a given document type.

    Args:
        image: PIL Image of the document
        doc_type: 'type_a', 'type_b', 'type_c', or 'auto'
        ocr_func: callable that takes a PIL Image and returns recognized text string

    Returns:
        Tuple of (resolved_doc_type, list of field dicts with name/value/confidence/bbox)
    """
    if doc_type == "auto":
        doc_type = detect_document_type(image, ocr_func)

    field_defs = get_fields_for_type(doc_type)
    if not field_defs:
        logger.warning(f"No field definitions for doc_type={doc_type}")
        return doc_type, []

    results = []
    w, h = image.size

    for field_def in field_defs:
        region = field_def["region"]
        try:
            cropped = crop_region(image, region)
            text = ocr_func(cropped)
            text = text.strip() if text else ""

            # Calculate pixel bbox
            x_pct, y_pct, w_pct, h_pct = region
            bbox = {
                "x": int(x_pct * w),
                "y": int(y_pct * h),
                "width": int(w_pct * w),
                "height": int(h_pct * h),
            }

            # Simple confidence heuristic: longer text with more alpha chars = higher confidence
            if text:
                alpha_ratio = sum(1 for c in text if c.isalpha()) / max(len(text), 1)
                confidence = round(min(0.95, 0.5 + alpha_ratio * 0.4), 2)
            else:
                confidence = 0.0

            results.append({
                "name": field_def["name"],
                "value": text,
                "confidence": confidence,
                "bbox": bbox,
            })
        except Exception as e:
            logger.error(f"Error extracting field {field_def['name']}: {e}")
            results.append({
                "name": field_def["name"],
                "value": "",
                "confidence": 0.0,
                "bbox": {"x": 0, "y": 0, "width": 0, "height": 0},
            })

    return doc_type, results


def full_page_ocr(image: Image.Image, ocr_func) -> List[Dict]:
    """
    Perform full-page OCR without field-specific extraction.
    Returns a single field with the full text.
    """
    try:
        text = ocr_func(image)
        w, h = image.size
        return [{
            "name": "full_text",
            "value": text.strip() if text else "",
            "confidence": 0.7,
            "bbox": {"x": 0, "y": 0, "width": w, "height": h},
        }]
    except Exception as e:
        logger.error(f"Full page OCR failed: {e}")
        return [{
            "name": "full_text",
            "value": "",
            "confidence": 0.0,
            "bbox": {"x": 0, "y": 0, "width": 0, "height": 0},
        }]
