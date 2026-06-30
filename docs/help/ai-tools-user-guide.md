> Heratio Help Center article. Category: AI & Automation.

# AI Tools

## User Guide

Powerful AI-powered tools for archival records: Named Entity Recognition (NER), Translation, Summarization, Spellcheck, Handwriting Text Recognition (HTR), and **LLM Description Suggestions**.

> **The AI tools are an optional paid subscription, not part of the base platform.** They are dormant until an AHG AI gateway subscription is activated. See **AI features - subscription service** for what is included and how to subscribe.

---

## Overview
```
+-------------------------------------------------------------------------+
|                          AI TOOLS SUITE                                  |
+-------------------------------------------------------------------------+
|                                                                          |
|  +----------+ +----------+ +----------+ +----------+ +----------------+  |
|  |   NER    | | TRANSLATE| | SUMMARIZE| | SUGGEST  | |   SPELLCHECK   |  |
|  +----+-----+ +----+-----+ +----+-----+ +----+-----+ +-------+--------+  |
|       |            |            |            |               |           |
|       v            v            v            v               v           |
|  Extract      Translate    Generate     LLM-powered     Check spelling   |
|  names,       between      AI           description     and grammar      |
|  places,      languages    summaries    suggestions     in metadata      |
|  dates                     from PDFs    from OCR                         |
|                                                                          |
+-------------------------------------------------------------------------+
```

---

## Features Summary
```
+--------------------+--------------------------------------------------+
|    Feature         |           Description                            |
+--------------------+--------------------------------------------------+
|  NER               | Extract persons, organizations, places, dates    |
|  Translation       | Offline machine translation (Argos Translate)    |
|  Summarization     | AI-powered text summarization from PDFs          |
|  Suggest Descr.    | LLM-powered scope_and_content from OCR/metadata  |
|  Spellcheck        | Spelling and grammar checking                    |
|  HTR               | Handwriting Text Recognition for images          |
+--------------------+--------------------------------------------------+
```

---

## Named Entity Recognition (NER)

### What is NER?

NER automatically identifies and extracts named entities from your archival records:
```
+-----------------------------------------------------------------+
|                    ENTITY TYPES DETECTED                         |
+-----------------------------------------------------------------+
|  PERSON     - Individual names (John Smith, Dr. Jane Doe)        |
|  ORG        - Organizations (UNESCO, British Museum)             |
|  GPE        - Places/Locations (London, South Africa)            |
|  DATE       - Dates and time periods (1985, January 2020)        |
+-----------------------------------------------------------------+
```

### Using NER from the Interface

#### Step 1: Navigate to Record
Go to any archival description (Information Object)

#### Step 2: Click Extract Entities
```
+--------------------------------------------------+
|  AI Tools                                         |
|  +--------------------------------------------+  |
|  |  [Generate Summary]                        |  |
|  +--------------------------------------------+  |
|  |  [Extract Entities]  <-- Click here        |  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
```

#### Step 3: View Results
```
+--------------------------------------------------+
|  Extraction Results                               |
|  +--------------------------------------------+  |
|  |  Found 12 entities                         |  |
|  |  [Review & Link ->]                        |  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
```

### Reviewing Extracted Entities

#### Access the Review Dashboard
Navigate to: **Admin** -> **AI Tools** -> **NER Review**

#### Review Dashboard
```
+------------------------------------------------------------+
|  NER Review Dashboard                                       |
+------------------------------------------------------------+
|                                                             |
|  +------------------------+  +-------------------------+    |
|  |        127             |  |          23             |    |
|  |  Entities Pending      |  |  Objects to Review      |    |
|  +------------------------+  +-------------------------+    |
|                                                             |
|  Objects with Pending Entities                              |
|  +------------------------------------------------------+  |
|  | Object                    | Pending | Actions        |  |
|  +------------------------------------------------------+  |
|  | Meeting Minutes 1985-90   |   15    | [Review]       |  |
|  | Personnel Records Box 3   |   12    | [Review]       |  |
|  | Annual Report 2023        |    8    | [Review]       |  |
|  +------------------------------------------------------+  |
|                                                             |
+------------------------------------------------------------+
```

### Entity Review Actions
```
+----------------------------------------------------------+
|              ACTIONS FOR EACH ENTITY                      |
+----------------------------------------------------------+
|                                                           |
|  CREATE & LINK     Create new actor/place/subject and    |
|                    link to record                         |
|                                                           |
|  LINK TO EXISTING  Link to existing authority record      |
|                    (exact or fuzzy match suggested)       |
|                                                           |
|  APPROVE           Mark as correct but don't link         |
|                                                           |
|  REJECT            Mark as incorrect/not relevant         |
|                                                           |
+----------------------------------------------------------+
```

### Editing Entities Before Saving
```
+------------------------------------------------------------+
|  Review Extracted Entities                                  |
+------------------------------------------------------------+
|                                                             |
|  People (4)                                                 |
|  +------------------------------------------------------+  |
|  | [J. Smith_______] [Type: Person v] [Create Actor v]  |  |
|  | Match: John Smith                                    |  |
|  +------------------------------------------------------+  |
|  | [Dr. Brown______] [Type: Person v] [Link to: Dr...v] |  |
|  | Similar: Dr. James Brown, Dr. Mary Brown             |  |
|  +------------------------------------------------------+  |
|                                                             |
|  Organizations (2)                                          |
|  +------------------------------------------------------+  |
|  | [UNESCO_________] [Type: Org    v] [Link to: UNE..v] |  |
|  | Exact Match: UNESCO                                  |  |
|  +------------------------------------------------------+  |
|                                                             |
|           [Create All]  [Reject All]  [Save All Decisions] |
+------------------------------------------------------------+
```

### Auto-trigger NER on Document Upload

NER extraction can be automatically triggered when documents are uploaded, saving time and ensuring all uploaded content is processed.

#### Enabling Auto-trigger

Navigate to: **Admin > AHG Settings > AI Services > NER**

Enable the **"Auto-extract on upload"** setting.

#### How It Works
```
+------------------------------------------------------------------+
|                  AUTO-TRIGGER WORKFLOW                            |
+------------------------------------------------------------------+
|                                                                   |
|   1. USER UPLOADS DOCUMENT                                        |
|      (PDF, Word, RTF, or text file)                               |
|                |                                                  |
|                v                                                  |
|   2. SYSTEM CHECKS                                                |
|      - Is auto-trigger enabled?                                   |
|      - Is file type processable?                                  |
|                |                                                  |
|                v                                                  |
|   3. NER EXTRACTION QUEUED                                        |
|      - Background processing via the Heratio queue worker         |
|      - Or drained on demand with ahg:ai-process-pending           |
|                |                                                  |
|                v                                                  |
|   4. ENTITIES EXTRACTED                                           |
|      - Results available in NER Review Dashboard                  |
|                                                                   |
+------------------------------------------------------------------+
```

#### Supported Document Types for Auto-trigger
```
+----------------------------------------------------------------+
|                PROCESSABLE DOCUMENT TYPES                       |
+----------------------------------------------------------------+
|  Type                              | Description                |
+------------------------------------+----------------------------+
|  application/pdf                   | PDF documents              |
|  text/plain                        | Plain text files           |
|  text/html                         | HTML documents             |
|  application/msword                | Word documents (.doc)      |
|  application/vnd.openxmlformats-   | Word documents (.docx)     |
|    officedocument.wordprocessingml.document                     |
|  application/rtf                   | Rich text format           |
+----------------------------------------------------------------+
```

#### Processing Pending Queue

Uploaded documents are queued for background processing. The standard Heratio queue worker drains them automatically; you can also process the queue on demand:

```bash
# Process pending NER extractions
php artisan ahg:ai-process-pending --limit=20
```

This runs from Heratio's task scheduler/queue worker - no per-task crontab entry is required.

---

## Translation

### About Translation

Translate archival descriptions between languages using offline machine translation (Argos Translate).

### Supported Languages
```
+---------------------+
| Language  | Code    |
+-----------+---------+
| English   |   en    |
| Afrikaans |   af    |
| French    |   fr    |
| Dutch     |   nl    |
| Portuguese|   pt    |
| Spanish   |   es    |
| German    |   de    |
+-----------+---------+
```

### Using Translation

#### From the Command Line
```bash
# Translate a single record
php artisan ahg:ai-translate --from=en --to=af --object=12345

# Translate all records in a repository
php artisan ahg:ai-translate --from=en --to=af --repository=5 --limit=50

# Overwrite existing target-culture text instead of skipping
php artisan ahg:ai-translate --from=en --to=af --object=12345 --overwrite

# Preview what would be translated
php artisan ahg:ai-translate --from=en --to=af --object=12345 --dry-run
```

#### Translation Options
```
+----------------------------------------------------------------+
|                    TRANSLATION OPTIONS                          |
+----------------------------------------------------------------+
|  --from           Source culture to read from (default: en)     |
|  --to             Target culture to write (required, e.g. af)    |
|  --object         Translate a specific information_object ID     |
|  --repository     Translate all in a repository ID               |
|  --limit          Maximum records to translate (0/blank = all)   |
|  --overwrite      Replace existing target text instead of skip   |
|  --dry-run        Preview without making changes                |
+----------------------------------------------------------------+
```

### Translation Process
```
     Source Record                     Target Record
     (English)                         (Afrikaans)
         |                                  |
         v                                  v
  +-------------+                    +-------------+
  | Title       | ----Translate----> | Titel       |
  | Scope &     | ----Translate----> | Omvang en   |
  | Content     |                    | Inhoud      |
  +-------------+                    +-------------+
```

---

## Summarization

### About Summarization

Automatically generate summaries for records with attached PDF documents, saving time on cataloging.

### Using Summarization

#### From the Interface
```
+--------------------------------------------------+
|  AI Tools                                         |
|  +--------------------------------------------+  |
|  |  [Generate Summary]  <-- Click here        |  |
|  +--------------------------------------------+  |
|  |  [Extract Entities]                        |  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
```

#### Result
```
+--------------------------------------------------+
|  Summary Generated                                |
|  +--------------------------------------------+  |
|  |  Summary saved to Scope & Content          |  |
|  |  Processing time: 2345ms                   |  |
|  |  [Refresh Page]                            |  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
```

#### From the Command Line
```bash
# Summarize a specific record
php artisan ahg:ai-summarize --object=12345

# Summarize records with empty Scope & Content
php artisan ahg:ai-summarize --all-empty --limit=50

# Summarize records in a repository
php artisan ahg:ai-summarize --repository=5 --limit=100

# Preview what would be processed
php artisan ahg:ai-summarize --all-empty --dry-run
```

### Summarization Settings
```
+----------------------------------------------------------------+
|                  SUMMARIZATION OPTIONS                          |
+----------------------------------------------------------------+
|  --object         Summarize specific object ID                  |
|  --repository     Summarize all in repository ID                |
|  --all-empty      Process records with empty summary            |
|  --field          Target field (default: scope_and_content)     |
|  --limit          Maximum records to process                    |
|  --dry-run        Preview without making changes                |
+----------------------------------------------------------------+
```

### How It Works
```
+------------------------------------------------------------------+
|                   SUMMARIZATION WORKFLOW                          |
+------------------------------------------------------------------+
|                                                                   |
|   1. EXTRACT TEXT                                                 |
|      +----------------+                                           |
|      |  PDF Document  | ---> pdftotext ---> Raw Text              |
|      +----------------+         OR                                |
|      | Metadata Fields| ---> Direct extraction                    |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   2. ANALYZE & SUMMARIZE                                          |
|      +----------------+                                           |
|      |  AI API        | ---> Generate concise summary             |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   3. SAVE RESULT                                                  |
|      +----------------+                                           |
|      |  Scope &       | <--- Summary saved                        |
|      |  Content Field |                                           |
|      +----------------+                                           |
|                                                                   |
+------------------------------------------------------------------+
```

---

## LLM Description Suggestions

### About Description Suggestions

Generate intelligent `Scope and Content` descriptions using Large Language Models (LLMs). The system combines OCR text, metadata, and contextual information to suggest comprehensive archival descriptions.

### Supported LLM Providers
```
+----------------------------------------------------------------+
|                    LLM PROVIDERS                                |
+----------------------------------------------------------------+
|  Provider    | Type    | Description                           |
+--------------+---------+---------------------------------------+
|  Ollama      | Local   | Privacy-focused, runs on your server |
|              |         | Models: llama3.1, mistral, mixtral    |
+--------------+---------+---------------------------------------+
|  OpenAI      | Cloud   | GPT models via API                    |
|              |         | Models: gpt-4o-mini, gpt-4o           |
+--------------+---------+---------------------------------------+
|  Anthropic   | Cloud   | Claude models via API                 |
|              |         | Models: claude-3-haiku, claude-3-sonnet|
+----------------------------------------------------------------+
```

### Using Description Suggestions

#### From the Interface

##### Step 1: Navigate to Record
Go to any archival description with OCR text or metadata

##### Step 2: Click Suggest Description
```
+--------------------------------------------------+
|  AI Tools                                         |
|  +--------------------------------------------+  |
|  |  [Generate Summary]                        |  |
|  +--------------------------------------------+  |
|  |  [Extract Entities]                        |  |
|  +--------------------------------------------+  |
|  |  [Suggest Description (AI)]  <-- Click     |  |
|  +--------------------------------------------+  |
+--------------------------------------------------+
```

##### Step 3: Review Side-by-Side
```
+------------------------------------------------------------+
|  AI Description Suggestion                                  |
+------------------------------------------------------------+
|                                                             |
|  +------------------------+  +------------------------+     |
|  | CURRENT DESCRIPTION    |  | AI SUGGESTION          |     |
|  +------------------------+  +------------------------+     |
|  |                        |  |                        |     |
|  | [Existing text or      |  | [AI-generated text     |     |
|  |  empty]                |  |  based on OCR and      |     |
|  |                        |  |  metadata - EDITABLE]  |     |
|  |                        |  |                        |     |
|  +------------------------+  +------------------------+     |
|                                                             |
|  Review Notes: [_______________________________________]    |
|                                                             |
|  Model: llama3.1:8b  |  Tokens: 450  |  Time: 2.3s         |
|                                                             |
|              [Approve]  [Edit & Approve]  [Reject]          |
+------------------------------------------------------------+
```

##### Step 4: Make Decision
```
+----------------------------------------------------------------+
|                    DECISION OPTIONS                             |
+----------------------------------------------------------------+
|  APPROVE         Accept suggestion as-is, save to record        |
|  EDIT & APPROVE  Modify suggestion, then save to record         |
|  REJECT          Discard suggestion, add rejection notes        |
+----------------------------------------------------------------+
```

### Review Dashboard

Access via: **Admin** -> **AI Tools** -> **Suggestion Review**

```
+------------------------------------------------------------+
|  Description Suggestion Review                              |
+------------------------------------------------------------+
|                                                             |
|  +----------+  +----------+  +----------+  +----------+     |
|  |   45     |  |   23     |  |   12     |  |    8     |     |
|  | Pending  |  | Approved |  | Rejected |  |  Edited  |     |
|  +----------+  +----------+  +----------+  +----------+     |
|                                                             |
|  Filter by Repository: [All Repositories        v]          |
|                                                             |
|  Pending Suggestions                                        |
|  +------------------------------------------------------+  |
|  | Record              | Generated    | Model    | Act  |  |
|  +------------------------------------------------------+  |
|  | Annual Report 2023  | 2 hours ago  | llama3.1 | [->] |  |
|  | Meeting Minutes Q1  | 3 hours ago  | llama3.1 | [->] |  |
|  | Personnel File #42  | 1 day ago    | gpt-4o   | [->] |  |
|  +------------------------------------------------------+  |
|                                                             |
+------------------------------------------------------------+
```

### From the Command Line

```bash
# Generate suggestion for specific record
php artisan ahg:ai-suggest-description --object=12345

# Process records with empty scope_and_content
php artisan ahg:ai-suggest-description --empty-only --limit=50

# Prefer records that have OCR text
php artisan ahg:ai-suggest-description --with-ocr --limit=100

# Process records in a specific repository
php artisan ahg:ai-suggest-description --repository=5 --limit=50

# Preview what would be processed (dry run)
php artisan ahg:ai-suggest-description --empty-only --dry-run

# Use specific prompt template
php artisan ahg:ai-suggest-description --template=2 --limit=20

# Use specific LLM configuration
php artisan ahg:ai-suggest-description --llm-config=1 --limit=20
```

### Command Options
```
+----------------------------------------------------------------+
|              SUGGEST DESCRIPTION OPTIONS                        |
+----------------------------------------------------------------+
|  --object=ID        Process specific object ID                  |
|  --repository=ID    Process all in repository ID                |
|  --level=LEVEL      Filter by level (fonds, series, file, item) |
|  --empty-only       Only records with empty scope_and_content   |
|  --with-ocr         Only records that have OCR text available   |
|  --limit=N          Maximum records to process (default: 50)    |
|  --template=ID      Prompt template ID to use                   |
|  --llm-config=ID    LLM configuration ID                        |
|  --dry-run          Preview without generating suggestions      |
|  --delay=MS         Delay between API calls (default: 1000)     |
+----------------------------------------------------------------+
```

### How It Works
```
+------------------------------------------------------------------+
|                DESCRIPTION SUGGESTION WORKFLOW                    |
+------------------------------------------------------------------+
|                                                                   |
|   1. GATHER CONTEXT                                               |
|      +----------------+                                           |
|      | Record Data    | ---> Title, identifier, dates, level     |
|      | OCR Text       | ---> Full text from digital objects      |
|      | Metadata       | ---> Creator, repository, existing data  |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   2. SELECT TEMPLATE                                              |
|      +----------------+                                           |
|      | Prompt Template| ---> Based on level/repository/default   |
|      | - System prompt|                                           |
|      | - User template| ---> Variables: {title}, {ocr_text}, etc |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   3. CALL LLM                                                     |
|      +----------------+                                           |
|      | LLM Provider   | ---> Ollama / OpenAI / Anthropic         |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   4. SAVE & REVIEW                                                |
|      +----------------+                                           |
|      | Pending        | ---> Custodian reviews suggestion        |
|      | Suggestion     |                                           |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   5. APPLY (on approval)                                          |
|      +----------------+                                           |
|      | scope_and_     | <--- Approved text saved                  |
|      | content        |                                           |
|      +----------------+                                           |
|                                                                   |
+------------------------------------------------------------------+
```

### Prompt Templates

The system includes default templates that can be customized:

```
+----------------------------------------------------------------+
|                    DEFAULT TEMPLATES                            |
+----------------------------------------------------------------+
|  Template            | Use Case                                 |
+----------------------+------------------------------------------+
|  Standard Archival   | General archival descriptions            |
|  Item-Level OCR      | Items with OCR text (transcriptions)     |
|  Photograph          | Photographs and image collections        |
+----------------------------------------------------------------+
```

### Template Variables
```
+----------------------------------------------------------------+
|                  TEMPLATE VARIABLES                             |
+----------------------------------------------------------------+
|  Variable                | Description                          |
+--------------------------+--------------------------------------+
|  {title}                 | Record title                         |
|  {identifier}            | Reference code/identifier            |
|  {level_of_description}  | Level (fonds, series, file, item)    |
|  {date_range}            | Date expression                      |
|  {creator}               | Creator name                         |
|  {repository}            | Repository name                      |
|  {ocr_text}              | Full OCR text from digital objects   |
|  {existing_metadata}     | All available metadata fields        |
+----------------------------------------------------------------+
```

### Cron Job Scheduling

Schedule description-suggestion generation through Heratio's task scheduler, or run on demand:

```bash
# Generate for empty records
php artisan ahg:ai-suggest-description --empty-only --limit=100

# Prefer records with OCR text
php artisan ahg:ai-suggest-description --with-ocr --limit=200
```

### Best Practices
```
+------------------------------------------------------------+
|          DESCRIPTION SUGGESTION BEST PRACTICES              |
+------------------------------------------------------------+
|  DO                           |  DON'T                      |
+-------------------------------+-----------------------------+
|  Always review suggestions    |  Auto-approve without review|
|  Use OCR for richer context   |  Ignore OCR text            |
|  Edit suggestions if needed   |  Accept low-quality output  |
|  Choose appropriate template  |  Use wrong template type    |
|  Process in small batches     |  Generate thousands at once |
|  Use local Ollama for privacy |  Send sensitive data to cloud|
+-------------------------------+-----------------------------+
```

---

## Spellcheck

### About Spellcheck

Check spelling and grammar in metadata fields to improve data quality.

### Using Spellcheck

#### From the Command Line
```bash
# Check a specific record
php artisan ahg:ai-spellcheck --object=12345

# Check all records in a repository
php artisan ahg:ai-spellcheck --repository=5 --limit=100

# Check all records
php artisan ahg:ai-spellcheck --all --limit=100

# Specify the i18n culture
php artisan ahg:ai-spellcheck --all --culture=en

# Preview what would be checked
php artisan ahg:ai-spellcheck --all --dry-run
```

### Spellcheck Options
```
+----------------------------------------------------------------+
|                    SPELLCHECK OPTIONS                           |
+----------------------------------------------------------------+
|  --object         Check specific object ID                      |
|  --repository     Check all in repository ID                    |
|  --all            Check all objects                             |
|  --language       Language code (default: en_US)                |
|  --limit          Maximum records to check                      |
|  --dry-run        Preview without making changes                |
+----------------------------------------------------------------+
```

### Spellcheck Results
```
+----------------------------------------------------------------+
|                    SPELLCHECK OUTPUT                            |
+----------------------------------------------------------------+
|  Checked 50 objects (lang: en)                                  |
|  Object 12345: 3 issues                                         |
|  Object 12346: 0 issues                                         |
|  Object 12347: 5 issues                                         |
|  Done: 50 checked, 12 with issues                               |
+----------------------------------------------------------------+
```

---

## Handwriting Text Recognition (HTR)

### About HTR

Extract text from handwritten documents using AI-powered recognition with zone detection.

### How HTR Works
```
+------------------------------------------------------------------+
|                      HTR WORKFLOW                                 |
+------------------------------------------------------------------+
|                                                                   |
|   1. IMAGE INPUT                                                  |
|      +----------------+                                           |
|      |  Scanned Image | ---> Load image file                      |
|      |  (JPG/PNG/TIFF)|                                           |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   2. ZONE DETECTION                                               |
|      +----------------+                                           |
|      | Detect text    | ---> Identify text line regions           |
|      | zones/lines    |                                           |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   3. TEXT RECOGNITION                                             |
|      +----------------+                                           |
|      | HTR Models     | ---> Recognize handwritten text           |
|      | (date/digits/  |      in each zone                         |
|      |  letters)      |                                           |
|      +----------------+                                           |
|                |                                                  |
|                v                                                  |
|   4. OUTPUT                                                       |
|      +----------------+                                           |
|      | Extracted Text | ---> Per-zone results with coordinates    |
|      +----------------+                                           |
|                                                                   |
+------------------------------------------------------------------+
```

### HTR Recognition Modes
```
+----------------------------------------------------------------+
|                      HTR MODES                                  |
+----------------------------------------------------------------+
|  Mode      | Description                                        |
+------------+----------------------------------------------------+
|  all       | Use all models (date, digits, letters) - default   |
|  date      | Optimized for date recognition                     |
|  digits    | Optimized for numeric content                      |
|  letters   | Optimized for alphabetic text                      |
+----------------------------------------------------------------+
```

---

## CLI Commands Reference

### Installation & Setup
```bash
# Install AI database tables (idempotent)
php artisan ahg:ai-install

# Parse and count statements without executing
php artisan ahg:ai-install --dry-run
```

### NER Commands
```bash
# Extract entities from all matching records
php artisan ahg:ai-ner

# Only objects with no entities extracted yet
php artisan ahg:ai-ner --unprocessed --limit=100

# Read descriptions in a specific culture
php artisan ahg:ai-ner --culture=en --limit=50

# Preview (dry run)
php artisan ahg:ai-ner --dry-run
```

### Training Data Sync
```bash
# Export pending corrections to a JSONL training file (default action)
php artisan ahg:ai-ner-sync --export

# Export to a specific path
php artisan ahg:ai-ner-sync --export --out=storage/app/ner-training/corrections.jsonl

# View correction statistics
php artisan ahg:ai-ner-sync --stats
```

**Note:** Pushing corrections to a central training server (`--retrain`) is not yet wired (see #1268). Export + stats work today.

### Pending Queue Processing
```bash
# Process pending NER extractions from the queue
php artisan ahg:ai-process-pending --limit=50

# Preview what would be processed (dry run)
php artisan ahg:ai-process-pending --dry-run
```

**Note:** `--task-type` currently supports only `ner`. Auto-triggered NER jobs from document uploads are queued and drained by this command (or by the standard Heratio queue worker).

### Translation Commands
```bash
# Translate a single record into Afrikaans
php artisan ahg:ai-translate --to=af --object=12345

# Translate a repository's records
php artisan ahg:ai-translate --to=af --repository=5 --limit=50

# Overwrite existing target-culture text instead of skipping
php artisan ahg:ai-translate --to=af --object=12345 --overwrite
```

### Summarization Commands
```bash
# Summarize a single record
php artisan ahg:ai-summarize --object=12345

# Summarize records whose target field is empty
php artisan ahg:ai-summarize --all-empty --limit=50
```

### Spellcheck Commands
```bash
# Check a single record
php artisan ahg:ai-spellcheck --object=12345

# Check all records with description text
php artisan ahg:ai-spellcheck --all --limit=100
```

### Description Suggestion Commands
```bash
# Generate a suggestion for a single record
php artisan ahg:ai-suggest-description --object=12345

# Process records with an empty scope_and_content
php artisan ahg:ai-suggest-description --empty-only --limit=50

# Prefer objects that have OCR text
php artisan ahg:ai-suggest-description --with-ocr --limit=100

# Use a specific prompt template and LLM config
php artisan ahg:ai-suggest-description --template=2 --llm-config=1 --limit=20

# Preview what would be processed
php artisan ahg:ai-suggest-description --empty-only --dry-run
```

---

## NER Review Workflow

### Complete Workflow
```
+------------------------------------------------------------------+
|                   NER REVIEW WORKFLOW                             |
+------------------------------------------------------------------+
|                                                                   |
|   1. EXTRACTION                                                   |
|      Run extraction via UI or CLI                                 |
|      -> Entities stored with 'pending' status                     |
|                                                                   |
|   2. REVIEW                                                       |
|      Open NER Review Dashboard                                    |
|      -> Select object to review                                   |
|      -> Edit entity values/types if needed                        |
|      -> Choose action for each entity                             |
|                                                                   |
|   3. SAVE DECISIONS                                               |
|      Click "Save All Decisions"                                   |
|      -> Entities processed in batches                             |
|      -> Access points created/linked                              |
|                                                                   |
|   4. TRAINING FEEDBACK                                            |
|      Corrections tracked for model improvement                    |
|      -> Run ahg:ai-ner-sync --export to export training data      |
|                                                                   |
+------------------------------------------------------------------+
```

### Entity Linking Results
```
+----------------------------------------------------------------+
|                 LINKING RESULTS BY TYPE                         |
+----------------------------------------------------------------+
|  Entity Type  | Creates/Links To                                |
+---------------+------------------------------------------------+
|  PERSON       | Actor (Name Access Point)                       |
|  ORG          | Actor - Corporate Body (Name Access Point)      |
|  GPE          | Place Term (Place Access Point)                 |
|  DATE         | Subject Term or Event                           |
+----------------------------------------------------------------+
```

---

## Training Data Export

### Correction Types Tracked
```
+----------------------------------------------------------------+
|                 CORRECTION TYPES                                |
+----------------------------------------------------------------+
|  Type         | Description                                     |
+---------------+------------------------------------------------+
|  value_edit   | Entity value was edited before saving           |
|  type_change  | Entity type was changed (e.g., PERSON -> ORG)   |
|  both         | Both value and type were changed                |
|  approved     | Entity approved as-is (no link)                 |
|  rejected     | Entity marked as incorrect                      |
+----------------------------------------------------------------+
```

### Export Training Data
```bash
# View correction statistics
php artisan ahg:ai-ner-sync --stats

# Output:
# value_edit: 45 total, 20 exported, 25 pending
# type_change: 12 total, 8 exported, 4 pending
# rejected: 30 total, 25 exported, 5 pending

# Export to a specific file
php artisan ahg:ai-ner-sync --export --out=storage/app/ner-training/corrections.jsonl

# Export pending corrections (default action)
php artisan ahg:ai-ner-sync --export
```

### AHG Central Configuration

Training data sync requires AHG Central integration. Configure it at:

**Admin > AHG Settings > AHG Central**

| Setting | Description |
|---------|-------------|
| Enable Integration | Master switch for cloud sync |
| API URL | AHG Central endpoint (default: https://train.theahg.co.za/api) |
| API Key | Your authentication key (contact support@theahg.co.za) |
| Site ID | Unique identifier for your Heratio instance |

You can test the connection before saving to verify your credentials.

---

## Best Practices

### NER Best Practices
```
+------------------------------------------------------------+
|                    NER BEST PRACTICES                       |
+------------------------------------------------------------+
|  DO                           |  DON'T                      |
+-------------------------------+-----------------------------+
|  Review entities regularly    |  Auto-link without review   |
|  Fix entity values if wrong   |  Ignore fuzzy matches       |
|  Export training data         |  Delete all pending         |
|  Use batch processing         |  Process one at a time      |
|  Process PDFs for more data   |  Skip PDF extraction        |
+-------------------------------+-----------------------------+
```

### Translation Best Practices
```
+------------------------------------------------------------+
|               TRANSLATION BEST PRACTICES                    |
+------------------------------------------------------------+
|  DO                           |  DON'T                      |
+-------------------------------+-----------------------------+
|  Install packages first       |  Translate without packages |
|  Use --dry-run to preview     |  Bulk translate blindly     |
|  Review translated content    |  Trust 100% accuracy        |
|  Process in batches           |  Translate entire database  |
+-------------------------------+-----------------------------+
```

### Summarization Best Practices
```
+------------------------------------------------------------+
|             SUMMARIZATION BEST PRACTICES                    |
+------------------------------------------------------------+
|  DO                           |  DON'T                      |
+-------------------------------+-----------------------------+
|  Use for records with PDFs    |  Expect perfect summaries   |
|  Review generated summaries   |  Auto-publish without review|
|  Set appropriate min/max      |  Use default for all types  |
|  Process records in batches   |  Summarize entire archive   |
+-------------------------------+-----------------------------+
```

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| NER returns no entities | Check if record has text content in title/scope |
| Translation fails | Ensure the language pair is installed in the translation backend (Argos) |
| Summarization fails | Ensure PDF has extractable text (not image-only) |
| Spellcheck errors | Install aspell dictionary for language |
| HTR not working | Ensure image file is accessible and valid format |
| LLM suggestion fails | Check Ollama is running (`ollama serve`) or API keys are configured |
| "Ollama not available" | Install and start Ollama, or configure OpenAI/Anthropic |
| Empty suggestion | Record needs OCR text or substantial metadata for context |
| Slow LLM response | Increase timeout or use smaller/faster model |

### Error Messages
```
+----------------------------------------------------------------+
|                    ERROR MESSAGES                               |
+----------------------------------------------------------------+
|  "No text content found"                                        |
|  -> Record has no title, scope, or extractable PDF text         |
|                                                                 |
|  "Language package not installed"                               |
|  -> Install the X->Y language pair in the Argos backend         |
|                                                                 |
|  "Summarizer service not available"                             |
|  -> Check AI API is running and accessible                      |
|                                                                 |
|  "NER is disabled in settings"                                  |
|  -> Enable in ahg_ai_settings table (feature='ner', key='enabled') |
|                                                                 |
|  "LLM provider not available"                                   |
|  -> Check Ollama: curl http://localhost:11434/api/tags          |
|  -> Or configure OpenAI/Anthropic API keys                      |
|                                                                 |
|  "No LLM configuration found"                                   |
|  -> Run database migration to create default configs            |
|  -> Or add config in ahg_llm_config table                       |
|                                                                 |
|  "Suggestion requires review"                                   |
|  -> Suggestions are pending - approve via Review Dashboard      |
+----------------------------------------------------------------+
```

### Checking Service Health
```bash
# Check AI API health
curl http://localhost:5004/ai/v1/health

# Expected response:
# {"status": "healthy", "services": {"ner": true, "summarizer": true, "translate": true}}

# Check Ollama health (for LLM suggestions)
curl http://localhost:11434/api/tags

# Expected response:
# {"models": [{"name": "llama3.1:8b", ...}]}

# Check LLM health via Heratio
curl https://your-site.com/ai/llm/health
```

---

## Configuration

### Settings Table
```
+----------------------------------------------------------------+
|                    AI SETTINGS                                  |
+----------------------------------------------------------------+
|  Feature     | Setting Key              | Default Value         |
+--------------|--------------------------|----------------------+
|  general     | api_url                  | http://192.168.0.112:5004/ai/v1 |
|  general     | api_key                  | ahg_ai_demo_internal_2026 |
|  general     | api_timeout              | 60                    |
|  ner         | enabled                  | 1                     |
|  ner         | confidence_threshold     | 0.85                  |
|  ner         | enabled_entity_types     | ["PERSON","ORG","GPE","DATE"] |
|  summarize   | enabled                  | 1                     |
|  summarize   | max_length               | 1000                  |
|  summarize   | min_length               | 100                   |
|  translate   | enabled                  | 1                     |
|  translate   | engine                   | argos                 |
|  spellcheck  | enabled                  | 1                     |
|  spellcheck  | language                 | en                    |
|  suggest     | enabled                  | 1                     |
|  suggest     | require_review           | 1                     |
|  suggest     | auto_expire_days         | 30                    |
|  suggest     | default_llm_config       | 1                     |
|  suggest     | default_template         | 1                     |
+----------------------------------------------------------------+
```

### LLM Configurations
```
+----------------------------------------------------------------+
|                    LLM CONFIGURATIONS                           |
+----------------------------------------------------------------+
|  Provider  | Default Model           | Endpoint               |
+------------+-------------------------+------------------------+
|  ollama    | llama3.1:8b             | http://localhost:11434 |
|  openai    | gpt-4o-mini             | https://api.openai.com |
|  anthropic | claude-3-haiku-20240307 | https://api.anthropic.com |
+----------------------------------------------------------------+
```

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the Heratio AHG Framework*
