> Heratio Help Center article. Category: Collection Mgmt / Condition.

# Condition Reports

## Overview

Heratio's Condition Reports module allows you to create detailed condition assessments for archival materials, museum objects, and library items. Reports include photographs with annotations, AI-powered condition scanning, and integration with the Spectrum condition check procedures.

---

## Creating a Condition Report

To create a new condition report:

1. Navigate to the archival description or object record
2. Click **More > Condition Report** or go to `/condition/{slug}/create`
3. Complete the condition report form

### Report Fields

| Field | Type | Description |
|-------|------|-------------|
| Date of Assessment | Date picker | When the assessment was performed |
| Assessed By | Dropdown | Staff member performing the assessment |
| Overall Condition | Dropdown | Excellent, Good, Fair, Poor, Unacceptable |
| Completeness | Dropdown | Complete, Substantially Complete, Fragmentary |
| Conservation Priority | Dropdown | Urgent, High, Medium, Low, None |
| Description of Condition | Textarea | Detailed free-text description of current condition |
| Damage Types | Multi-select | Tears, Foxing, Fading, Water Damage, Mould, Insect Damage, Abrasion, Corrosion, Staining, Brittleness, Warping, Cracking, Losses, Flaking, Discolouration |
| Structural Integrity | Dropdown | Stable, Vulnerable, Fragile, Actively Deteriorating |
| Storage Requirements | Textarea | Special storage or handling requirements |
| Treatment Recommendations | Textarea | Recommended conservation treatments |
| Handling Instructions | Textarea | Instructions for safe handling |
| Exhibition Suitability | Dropdown | Suitable, Suitable with Conditions, Not Suitable |
| Insurance Value | Decimal | Current insurance valuation |
| Currency | Dropdown | ZAR, USD, GBP, EUR, etc. |
| Notes | Textarea | Additional observations |

---

## Condition Photos

Photos are a critical part of condition documentation. Upload photos directly in the condition report form.

### Uploading Photos

1. Click **Add Photo** in the Photos section
2. Select one or more image files (JPEG, PNG, TIFF supported)
3. Photos are stored in `uploads/condition_photos/` under the Heratio storage path
4. Each photo is associated with the specific condition report

### Photo Annotations

After uploading a photo, you can annotate it to highlight areas of damage or concern:

1. Click the **Annotate** button on an uploaded photo
2. Use the annotation tools:
   - **Rectangle** --- draw a box around an area of interest
   - **Circle** --- highlight a specific point
   - **Arrow** --- point to a feature
   - **Freehand** --- draw a custom outline
3. Add a text label describing the annotation (e.g., "Water staining", "Tear 3cm")
4. Annotations are saved with the photo and appear on printed reports

---

## AI Condition Scan

Heratio integrates with Ollama running the LLaVA vision model to provide AI-assisted condition assessment.

### How It Works

1. Upload a clear photograph of the object
2. Click **AI Condition Scan**
3. The image is sent to the local Ollama LLaVA model for analysis
4. The AI returns:
   - **Detected damage types** (e.g., tears, staining, foxing)
   - **Suggested overall condition rating**
   - **Recommended conservation actions**
   - **Confidence score** for each detection
5. Review the AI suggestions and accept, modify, or reject each one
6. Accepted suggestions are applied to the condition report fields

### Requirements

- Ollama must be installed and running on the server (or a configured remote host)
- The LLaVA model must be pulled: `ollama pull llava`
- Configure the Ollama endpoint in `.env`: `OLLAMA_HOST=http://localhost:11434`

### Important Notes

- AI scanning is an assistive tool, not a replacement for professional assessment
- Always review and verify AI suggestions before saving
- The AI works best with well-lit, high-resolution photographs
- Processing time depends on image size and server hardware

---

## Condition History

Every condition report is retained as part of the item's condition history. The history view shows all previous assessments in chronological order, allowing you to:

- Track condition changes over time
- Compare photos from different assessment dates
- Identify deterioration trends
- Verify the effectiveness of conservation treatments

Access condition history from the object record under **More > Condition History** or at `/condition/{slug}/history`.

---

## Spectrum Condition Checks

Heratio's condition reports align with the Spectrum 5.0 Condition Checking procedure. The Spectrum integration ensures that:

- **Pre-loan checks** are documented before items leave the institution
- **Post-loan checks** are recorded when items return
- **Acquisition condition checks** capture the state of items upon arrival
- **Routine survey checks** are scheduled and tracked
- **Exhibition checks** are performed before and after display periods

Each Spectrum check type generates a condition report with the appropriate context fields pre-filled.

### Spectrum Workflow Integration

1. **Object Entry** --- condition checked on arrival
2. **Loans Out** --- condition checked before dispatch and on return
3. **Loans In** --- condition checked on receipt and before return
4. **Object Audit** --- condition checked during routine surveys
5. **Conservation** --- condition documented before and after treatment

---

## Printing and Export

Condition reports can be:

- **Printed** as formatted PDF documents with photos and annotations
- **Exported** as CSV for bulk analysis
- **Attached** to loan agreements and insurance documentation

---

## Permissions

| Action | Required Role |
|--------|---------------|
| View condition reports | Authenticated user |
| Create condition reports | Editor or above |
| Edit condition reports | Editor or above (own reports), Administrator (all) |
| Delete condition reports | Administrator |
| Run AI Condition Scan | Editor or above |

---

*Part of the Heratio AHG Framework*
