# AtoM AHG Framework - User Manual

## Version 1.6.x  
## Last Updated: January 2026

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [AI Services](#3-ai-services)
4. [Named Entity Recognition (NER)](#4-named-entity-recognition-ner)
5. [AI Summarization](#5-ai-summarization)
6. [Spell Checking](#6-spell-checking)
7. [NER Review Dashboard](#7-ner-review-dashboard)
8. [Batch Processing](#8-batch-processing)
9. [Troubleshooting](#9-troubleshooting)

---

## 1. Introduction

The AtoM AHG Framework extends Access to Memory (AtoM) with advanced AI-powered features for archival management. Key capabilities include:

- **Named Entity Recognition (NER)**: Automatically extract people, organizations, places, and dates from records
- **AI Summarization**: Generate summaries from PDF documents
- **Spell Checking**: Identify spelling errors in metadata fields
- **Security Clearance**: Classify records by security level
- **Audit Trail**: Track all system activities

### System Requirements

- AtoM `2.8+` or `2.10+`
- PHP `8.1+`
- MySQL `8.0+`
- Elasticsearch `6.x` (for AtoM `2.9.x`) or `7.x` (for AtoM `2.10+`)

---

## 2. Getting Started

### Accessing AHG Settings

1. Log in as an administrator
2. Navigate to **Admin** → **AHG Settings**
3. Select the desired settings module

<img src="/plugins/ahgHelpPlugin/images/accessing-ahg-settings.png" alt="AHG Settings" width="500">

---

## 3. AI Services

### Accessing AI Services Settings

Navigate to: **Admin** → **AHG Settings** → **AI Services**

### Configuration Options

#### API Configuration

| Setting          | Description                             | Default                       |
|-----------------|------------------------------------------|-------------------------------|
| API URL         | URL of the AI service endpoint           | `http://localhost:5004/ai/v1` |
| API Key         | Authentication key for API access        | *(empty)*                     |
| Timeout         | Request timeout in seconds               | `60`                          |
| Processing Mode | `Hybrid` (direct) or `Job` (background)  | `Job`                         |

#### NER Settings

| Setting                | Description                                     | Default |
|------------------------|-------------------------------------------------|---------|
| Enable NER             | Turn NER extraction on/off                      | `On`    |
| Extract from PDFs      | Extract text from attached PDFs                 | `On`    |
| Auto-extract on Upload | Run NER when records are created                | `Off`   |
| Require Review         | Require manual review before linking            | `On`    |
| Entity Types           | Types to extract (`PERSON`, `ORG`, `GPE`, `DATE`) | `All`   |

#### Summarization Settings

| Setting              | Description               | Default             |
|---------------------|---------------------------|---------------------|
| Enable Summarization| Turn summarization on/off | `On`                |
| Target Field        | Field to store summaries  | `Scope and Content` |
| Min Length          | Minimum summary length    | `100`               |
| Max Length          | Maximum summary length    | `500`               |

#### Spell Check Settings

| Setting           | Description                    | Default                  |
|------------------|--------------------------------|--------------------------|
| Enable Spell Check | Turn spell checking on/off    | `Off`                    |
| Language         | Dictionary language            | `en_ZA`                  |
| Fields to Check  | Metadata fields to check       | `title, scopeAndContent` |

### Workflow Diagram

<img src="/plugins/ahgHelpPlugin/images/ai-services-workflow.png" alt="AI Services Workflow" width="500">

---

## 4. Named Entity Recognition (NER)

### What is NER?

NER automatically identifies and classifies named entities in text:

| Entity Type  | Code     | Examples                                                   |
|-------------|----------|------------------------------------------------------------|
| Person      | `PERSON` | Nelson Mandela, Cheryl Carolus                             |
| Organization| `ORG`    | African National Congress, Department of Education          |
| Location    | `GPE`    | Johannesburg, South Africa                                 |
| Date        | `DATE`   | 18 January 1993, 1994                                      |

### How NER Works

<img src="/plugins/ahgHelpPlugin/images/ner_processing_flow.png" alt="NER Processing" width="500">

### Text Sources

NER extracts text from multiple sources:

1. **Metadata Fields**
   - Title
   - Scope and Content
   - Archival History

2. **Attached PDFs** (when “Extract from PDFs” is enabled)
   - Uses `pdftotext` for extraction
   - Limited to `50,000` characters per document

### Viewing Extracted Entities

1. Navigate to a record's view page
2. Look for the **Entities** section
3. Entities are grouped by type (People, Organizations, Places, Dates)

---

## 5. AI Summarization

### Overview

AI Summarization automatically generates concise summaries from PDF documents and saves them to the specified metadata field.

### Summarization Workflow

<img src="/plugins/ahgHelpPlugin/images/summarization_workflow.png" alt="Summarization Workflow" width="500">

### Best Practices

- Ensure PDFs contain searchable text (not just images)
- OCR scanned documents before processing
- Review summaries for accuracy, especially for historical documents

---

## 6. Spell Checking

### Overview

Spell checking identifies potential spelling errors in metadata fields using language-specific dictionaries.

### Supported Languages

| Code    | Language                 |
|---------|--------------------------|
| `en_ZA` | English (South Africa)   |
| `en_US` | English (United States)  |
| `en_GB` | English (United Kingdom) |
| `af_ZA` | Afrikaans                |

### Spell Check Results

Results are stored and can be reviewed:

- **Pending**: Not yet reviewed
- **Reviewed**: Corrections made
- **Ignored**: False positives marked to ignore

---

## 7. NER Review Dashboard

### Accessing the Dashboard

Navigate to: `/ner/review` or **Admin** → **NER Review**

### Dashboard Features

<img src="/plugins/ahgHelpPlugin/images/ner_review_dashboard_layout.png" alt="NER Review Dashboard" width="500">

### Review Actions

| Action        | Description                                         |
|---------------|-----------------------------------------------------|
| Approve (✓)   | Confirm entity is correct, optionally link to actor |
| Reject (✗)    | Mark entity as incorrect/not relevant               |
| Edit          | Modify entity value or type                         |
| Link          | Link to existing authority record                   |

### Bulk Actions

- **Approve Selected**: Approve multiple entities at once
- **Reject Selected**: Reject multiple entities at once
- **Export**: Export entities to CSV

---

## 8. Batch Processing

### CLI Commands

Batch processing is available via command line for large-scale operations.

#### NER Extraction

```bash
# Extract from all unprocessed records
php symfony ner:extract --all --limit=1000

# Extract from specific repository
php symfony ner:extract --repository=5 --limit=500

# Extract from single record
php symfony ner:extract --object=12345

# Dry run (show what would be processed)
php symfony ner:extract --all --dry-run --limit=10
```

#### Summarization

```bash
# Summarize records with empty scope_and_content
php symfony ner:summarize --all-empty --limit=100

# Summarize specific record
php symfony ner:summarize --object=12345

# Specify target field
php symfony ner:summarize --all-empty --field=abstract --limit=100
```

#### Spell Check

```bash
# Check all records
php symfony ner:spellcheck --all --limit=100

# Check specific repository
php symfony ner:spellcheck --repository=5 --limit=500

# Specify language
php symfony ner:spellcheck --all --language=af_ZA --limit=100
```

### Running Long Batches

For large archives, use `screen` to run batches in the background:

```bash
# Start a screen session
screen -S batch_ner

# Run the batch
php symfony ner:extract --all --limit=10000

# Detach: Ctrl+A, D
# Reattach: screen -r batch_ner
```

### Monitoring Progress

```sql
-- Check NER progress
SELECT COUNT(*) AS processed FROM ahg_ner_extraction;
SELECT COUNT(*) AS entities FROM ahg_ner_entity;

-- Check summarization progress
SELECT COUNT(*) FROM information_object_i18n
WHERE scope_and_content IS NOT NULL AND scope_and_content != '';
```

---

## 9. Troubleshooting

### Common Issues

#### NER Not Extracting Entities

**Symptoms**: Records processed but no entities found

**Solutions**:

1. Check if “Extract from PDFs” is enabled
2. Verify PDFs contain searchable text
3. Check API connectivity: `curl http://API_URL/health`

#### Summarization Returns Empty

**Symptoms**: “Text too short” errors

**Solutions**:

1. Document may not have enough text (minimum `200` characters)
2. PDF may be image-only (needs OCR)
3. Check PDF extraction: `pdftotext document.pdf -`

#### Elasticsearch Errors

**Symptoms**: Errors when saving records

**Solutions**:

1. Check Elasticsearch is running: `systemctl status elasticsearch`
2. Verify Elastica version matches ES version
3. Rebuild index: `php symfony search:populate`

#### API Connection Errors

**Symptoms**: “API error: HTTP 0” or timeout errors

**Solutions**:

1. Verify API URL in settings
2. Check API key is correct
3. Increase timeout if processing large documents
4. Check API service is running

### Getting Help

- **Documentation**: https://github.com/ArchiveHeritageGroup/atom-extensions-catalog
- **Issues**: https://github.com/ArchiveHeritageGroup/atom-ahg-plugins/issues
- **Email**: support@theahg.co.za

---

## Appendix A: Entity Type Reference

| Type      | Description                         | Examples                                   |
|-----------|-------------------------------------|--------------------------------------------|
| `PERSON`  | Individual names                    | Nelson Mandela, F.W. de Klerk              |
| `ORG`     | Organizations, companies, agencies  | ANC, UN, Department of Education           |
| `GPE`     | Geopolitical entities (countries, cities) | South Africa, Johannesburg, Pretoria |
| `DATE`    | Dates and time periods              | 1994, 18 January 1993, the 1990s           |
| `LOC`     | Non-GPE locations                   | Table Mountain, Robben Island              |
| `EVENT`   | Named events                        | World Cup, Elections                       |
| `MONEY`   | Monetary values                     | R1,000, $500                               |
| `PERCENT` | Percentages                         | 50%, 10 percent                            |

---

## Appendix B: Keyboard Shortcuts

| Shortcut | Action (Review Dashboard) |
|----------|----------------------------|
| `A`      | Approve selected entity    |
| `R`      | Reject selected entity     |
| `E`      | Edit selected entity       |
| `N`      | Next entity                |
| `P`      | Previous entity            |
| `/`      | Focus search box           |

---

*Document Version: 1.0.0*  
*Last Updated: January 2026*  
*© The Archive and Heritage Group*
