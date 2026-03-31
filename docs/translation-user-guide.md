# Translation Management

## User Guide

Translate archival descriptions between languages using offline machine translation (NLLB-200). Supports all 11 South African official languages plus major international languages.

---

## Overview
```
+-------------------------------------------------------------+
|                   TRANSLATION WORKFLOW                       |
+-------------------------------------------------------------+
|                                                             |
|   SOURCE           TRANSLATE           TARGET               |
|   LANGUAGE         (NLLB-200)          LANGUAGE             |
|      |                 |                  |                 |
|      v                 v                  v                 |
|   Afrikaans  --->   AI Model  --->   English               |
|   isiZulu           Preview          Sesotho               |
|   Dutch             Review           French                |
|                     Apply            German                |
|                                                             |
+-------------------------------------------------------------+
```

---

## What Gets Translated
```
+-------------------------------------------------------------+
|                  TRANSLATABLE FIELDS                         |
+-------------------------------------------------------------+
|  Title                    Scope and Content                  |
|  Alternate Title          Archival History                   |
|  Acquisition              Arrangement                        |
|  Access Conditions        Reproduction Conditions            |
|  Finding Aids             Related Units of Description       |
|  Appraisal                Accruals                           |
|  Physical Characteristics Location of Originals              |
|  Location of Copies       Extent and Medium                  |
|  Sources                  Rules                              |
|  Revision History         Edition                            |
+-------------------------------------------------------------+
```

---

## Supported Languages

### South African Languages (11 Official)
```
+-------------------------------------------------------------+
|                  SOUTH AFRICAN LANGUAGES                     |
+-------------------------------------------------------------+
|  Code  | Language           | Code  | Language              |
+--------+--------------------+-------+-----------------------+
|  af    | Afrikaans          | zu    | isiZulu               |
|  xh    | isiXhosa           | st    | Sesotho               |
|  tn    | Setswana           | nso   | Sepedi (Northern Sotho)|
|  ts    | Xitsonga           | ss    | SiSwati               |
|  ve    | Tshivenda          | nr    | isiNdebele            |
|  en    | English            |       |                       |
+-------------------------------------------------------------+
```

### International Languages
```
+-------------------------------------------------------------+
|                  INTERNATIONAL LANGUAGES                     |
+-------------------------------------------------------------+
|  nl    | Dutch              | fr    | French                |
|  de    | German             | es    | Spanish               |
|  pt    | Portuguese         | sw    | Swahili               |
|  ar    | Arabic             |       |                       |
+-------------------------------------------------------------+
```

---

## How to Access
```
  Record View Page
      |
      v
   Actions Panel
      |
      v
   Translate Button --------------------------------+
      |                                             |
      +---> Opens Translation Modal                 |
            |                                       |
            +---> Step 1: Select Fields             |
            |                                       |
            +---> Step 2: Review & Edit             |
            |                                       |
            +---> Step 3: Approve & Save            |
```

---

## Step-by-Step Translation

### Step 1: Open the Translation Modal

1. Navigate to any **archival description** record
2. Look for the **Translate** button/link in the actions panel
3. Click to open the translation modal

### Step 2: Configure Translation Settings
```
+-------------------------------------------------------------+
|                   TRANSLATION MODAL                          |
+-------------------------------------------------------------+
|                                                             |
|  Source Language:    [Afrikaans (af)          v]            |
|                                                             |
|  Target Language:    [English (en)            v]            |
|                                                             |
|  +-------------------------------------------------------+  |
|  | [ ] Save with AtoM culture code                       |  |
|  |     Saves translation in target language's culture    |  |
|  +-------------------------------------------------------+  |
|  | [ ] Overwrite existing                                |  |
|  |     Overwrite if target field already has content     |  |
|  +-------------------------------------------------------+  |
|                                                             |
+-------------------------------------------------------------+
```

### Step 3: Select Fields to Translate
```
+-------------------------------------------------------------+
|  Fields to Translate          [Select All] [Deselect All]   |
+-------------------------------------------------------------+
|                                                             |
|  [x] Title                    [x] Scope and Content         |
|  [ ] Alternate Title          [ ] Archival History          |
|  [ ] Acquisition              [ ] Arrangement               |
|  [ ] Access Conditions        [ ] Reproduction Conditions   |
|  [ ] Finding Aids             [ ] Related Units             |
|  [ ] Appraisal                [ ] Accruals                  |
|  [ ] Physical Characteristics [ ] Location of Originals     |
|                                                             |
+-------------------------------------------------------------+
```

### Step 4: Preview Translations

Click **Translate** to generate translations:
```
+-------------------------------------------------------------+
|  TRANSLATION PREVIEW                                         |
+-------------------------------------------------------------+
|                                                             |
|  [OK] Title                                                 |
|  +-------------------------------------------------------+  |
|  | Source Text:                                          |  |
|  | Die Argief van Johannesburg                           |  |
|  +-------------------------------------------------------+  |
|  | Translation (editable):                               |  |
|  | The Archive of Johannesburg                           |  |
|  +-------------------------------------------------------+  |
|                                                             |
|  [OK] Scope and Content                                     |
|  +-------------------------------------------------------+  |
|  | Source Text:                                          |  |
|  | Hierdie versameling bevat dokumente...                |  |
|  +-------------------------------------------------------+  |
|  | Translation (editable):                               |  |
|  | This collection contains documents...                 |  |
|  +-------------------------------------------------------+  |
|                                                             |
+-------------------------------------------------------------+
```

### Step 5: Review and Edit

- Review each translation for accuracy
- **Edit** directly in the text area if corrections are needed
- The AI translation is a starting point, not the final word

### Step 6: Approve and Save

Click **Approve & Save** to apply translations:
```
+-------------------------------------------------------------+
|  SUCCESS                                                     |
+-------------------------------------------------------------+
|                                                             |
|  Successfully saved 2 translation(s) with culture code "en" |
|                                                             |
|  Page will reload automatically...                          |
|                                                             |
+-------------------------------------------------------------+
```

---

## Translation Options Explained

### Save with AtoM Culture Code
```
+-------------------------------------------------------------+
|  CULTURE CODE OPTION                                         |
+-------------------------------------------------------------+
|                                                             |
|  ENABLED (Recommended):                                     |
|    Translation saved to target language culture             |
|    Example: Afrikaans -> English saves to 'en' culture      |
|    Result: Record has separate language versions            |
|                                                             |
|  DISABLED:                                                  |
|    Translation saved to source language culture             |
|    Example: Afrikaans -> English saves to 'af' culture      |
|    Result: Original text is replaced                        |
|                                                             |
+-------------------------------------------------------------+
```

### Overwrite Existing
```
+-------------------------------------------------------------+
|  OVERWRITE OPTION                                            |
+-------------------------------------------------------------+
|                                                             |
|  ENABLED:                                                   |
|    Replace existing content in target field                 |
|    Use when updating/correcting translations                |
|                                                             |
|  DISABLED (Default):                                        |
|    Skip fields that already have content                    |
|    Prevents accidental overwrites                           |
|                                                             |
+-------------------------------------------------------------+
```

---

## Draft System

Translations are saved as drafts before being applied:
```
+-------------------------------------------------------------+
|                    DRAFT WORKFLOW                            |
+-------------------------------------------------------------+
|                                                             |
|  1. Translate    -->   Draft Created (status: draft)        |
|                                                             |
|  2. Edit         -->   Draft Updated (if needed)            |
|                                                             |
|  3. Approve      -->   Draft Applied (status: applied)      |
|                        Text saved to record                 |
|                                                             |
|  4. Reject       -->   Draft Rejected (status: rejected)    |
|                        No changes to record                 |
|                                                             |
+-------------------------------------------------------------+
```

### Benefits of Draft System
- Preview before committing changes
- Edit translations before saving
- Maintain audit trail of translation history
- Deduplicate identical translation requests

---

## Settings Configuration

Access settings at `/translation/settings`:
```
+-------------------------------------------------------------+
|  TRANSLATION SETTINGS                                        |
+-------------------------------------------------------------+
|                                                             |
|  MT Endpoint:                                               |
|  [http://192.168.0.112:5004/ai/v1/translate        ]        |
|  Example: http://127.0.0.1:5100/translate                   |
|                                                             |
|  Timeout (seconds):                                         |
|  [60                                               ]        |
|                                                             |
|                              [Save]                         |
|                                                             |
|  ---------------------------------------------------------  |
|  Health check: /translation/health                          |
|                                                             |
+-------------------------------------------------------------+
```

---

## Health Check

Test the translation service connectivity:

1. Go to `/translation/health`
2. Response indicates service status:
```json
{
  "ok": true,
  "endpoint": "http://192.168.0.112:5004/ai/v1/translate",
  "http_status": 200,
  "curl_error": null
}
```

---

## Common Use Cases
```
+-------------------------------------------------------------+
|                     USE TRANSLATION TO:                      |
+-------------------------------------------------------------+
|  Translate Afrikaans archival descriptions to English       |
|  Create multilingual access to historical records           |
|  Translate Dutch colonial documents for wider access        |
|  Support South African indigenous language preservation     |
|  Enable international researchers to access local archives  |
|  Provide English summaries of foreign language materials    |
+-------------------------------------------------------------+
```

---

## Tips
```
+--------------------------------+------------------------------+
|  DO                            |  DO NOT                      |
+--------------------------------+------------------------------+
|  Review translations before    |  Blindly accept AI output    |
|  saving                        |                              |
|                                |                              |
|  Check health endpoint first   |  Ignore connection errors    |
|                                |                              |
|  Use culture codes for         |  Overwrite original text     |
|  multilingual records          |  without backup              |
|                                |                              |
|  Translate most important      |  Translate everything at     |
|  fields first                  |  once without review         |
|                                |                              |
|  Edit translations for         |  Assume perfect accuracy     |
|  accuracy                      |                              |
+--------------------------------+------------------------------+
```

---

## Troubleshooting
```
+-------------------------------------------------------------+
|  ISSUE                    |  SOLUTION                        |
+---------------------------+----------------------------------+
|  "Translation failed"     |  Check health endpoint           |
|                           |  Verify MT service is running    |
|                           |                                  |
|  Timeout errors           |  Increase timeout in settings    |
|                           |  Check network connectivity      |
|                           |                                  |
|  "No source text"         |  Field is empty in source        |
|                           |  language - add content first    |
|                           |                                  |
|  "Target not empty"       |  Enable "Overwrite existing"     |
|                           |  option to replace content       |
|                           |                                  |
|  Poor translation quality |  Edit before saving              |
|                           |  Some language pairs work better |
+-------------------------------------------------------------+
```

---

## Translation Quality Notes

NLLB-200 (No Language Left Behind) provides good quality for:
- High-resource language pairs (English, French, German)
- Common South African languages (Afrikaans, isiZulu)

Quality may vary for:
- Low-resource language pairs
- Technical/archival terminology
- Historical language forms

**Always review translations before applying.**

---

## Need Help?

Contact your system administrator if you experience issues with the translation service.

---

*Part of the AtoM AHG Framework*
