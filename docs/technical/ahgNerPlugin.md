# ahgNerPlugin - Technical Documentation

## Version 1.6.x | January 2026

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Database Schema](#2-database-schema)
3. [Plugin Structure](#3-plugin-structure)
4. [AI API Integration](#4-ai-api-integration)
5. [CLI Tasks](#5-cli-tasks)
6. [Gearman Jobs](#6-gearman-jobs)
7. [Configuration](#7-configuration)
8. [Installation](#8-installation)

---

## 1. Architecture Overview

### System Flow
```
┌─────────────────────────────────────────────────────────────────────────┐
│                        ahgNerPlugin Architecture                         │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                              AtoM (Symfony 1.x)                          │
├─────────────────────────────────────────────────────────────────────────┤
│  ┌────────────────────────────────────────────────────────────────────┐ │
│  │                      ahgNerPlugin                                   │ │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐             │ │
│  │  │   Settings   │  │   Review     │  │  CLI Tasks   │             │ │
│  │  │   Module     │  │  Dashboard   │  │              │             │ │
│  │  └──────────────┘  └──────────────┘  └──────────────┘             │ │
│  │          │                │                 │                      │ │
│  │          └────────────────┼─────────────────┘                      │ │
│  │                           ▼                                        │ │
│  │                 ┌──────────────────┐                               │ │
│  │                 │  NER Repository  │                               │ │
│  │                 │  (Laravel Query  │                               │ │
│  │                 │   Builder)       │                               │ │
│  │                 └────────┬─────────┘                               │ │
│  └──────────────────────────┼─────────────────────────────────────────┘ │
└─────────────────────────────┼───────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          │                   │                   │
          ▼                   ▼                   ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│     MySQL        │ │   AI Service     │ │    Gearman       │
│  ahg_ner_*       │ │   (Python)       │ │   Job Queue      │
│  tables          │ │   Port 5004      │ │                  │
└──────────────────┘ └──────────────────┘ └──────────────────┘
![wireframe](./images/wireframes/wireframe_da3ccdbb.png)
```

### Component Interaction
```
┌─────────────────────────────────────────────────────────────────────────┐
│                         Processing Flow                                  │
└─────────────────────────────────────────────────────────────────────────┘

  User/CLI/Cron
       │
       ▼
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Get Record  │────►│ Extract Text │────►│   Call API   │
│  (QubitIO)   │     │ (Meta + PDF) │     │  /ner/extract│
└──────────────┘     └──────────────┘     └──────┬───────┘
                                                  │
                                                  ▼
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Update     │◄────│    Store     │◄────│   Parse      │
│  ES Index    │     │   Entities   │     │  Response    │
└──────────────┘     └──────────────┘     └──────────────┘
![wireframe](./images/wireframes/wireframe_70bcaeb5.png)
```

---

## 2. Database Schema

### Entity Relationship Diagram
```
┌─────────────────────────────────────────────────────────────────────────┐
│                           NER Database Schema                            │
└─────────────────────────────────────────────────────────────────────────┘

┌───────────────────────┐         ┌───────────────────────┐
│  information_object   │         │   ahg_ner_settings    │
├───────────────────────┤         ├───────────────────────┤
│ id (PK)               │         │ id (PK)               │
│ parent_id             │         │ setting_key (UNIQUE)  │
│ repository_id         │         │ setting_value         │
│ ...                   │         │ updated_at            │
└───────────┬───────────┘         └───────────────────────┘
            │
            │ 1:N
            ▼
┌───────────────────────┐
│  ahg_ner_extraction   │
├───────────────────────┤
│ id (PK)               │
│ object_id (FK)────────┼──► information_object.id
│ backend_used          │
│ status                │
│ entity_count          │
│ extracted_at          │
└───────────┬───────────┘
            │
            │ 1:N
            ▼
┌───────────────────────┐
│    ahg_ner_entity     │
├───────────────────────┤
│ id (PK)               │
│ extraction_id (FK)────┼──► ahg_ner_extraction.id
│ object_id (FK)────────┼──► information_object.id
│ entity_type           │    (PERSON, ORG, GPE, DATE)
│ entity_value          │
│ original_value        │
│ original_type         │
│ correction_type       │
│ training_exported     │
│ confidence            │
│ status                │
│ linked_actor_id (FK)──┼──► actor.id
│ reviewed_by (FK)──────┼──► user.id
│ reviewed_at           │
│ created_at            │
└───────────────────────┘

┌───────────────────────┐
│ ahg_spellcheck_result │
├───────────────────────┤
│ id (PK)               │
│ object_id (FK)────────┼──► information_object.id
│ errors_json (JSON)    │
│ error_count           │
│ status                │
│ reviewed_by (FK)      │
│ reviewed_at           │
│ created_at            │
└───────────────────────┘
![wireframe](./images/wireframes/wireframe_f63bf4fe.png)
```

### Table Definitions

#### ahg_ner_extraction
```sql
CREATE TABLE ahg_ner_extraction (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    backend_used VARCHAR(50) DEFAULT 'local',
    status VARCHAR(50) DEFAULT 'pending',
    entity_count INT DEFAULT 0,
    extracted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_object (object_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### ahg_ner_entity
```sql
CREATE TABLE ahg_ner_entity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extraction_id BIGINT UNSIGNED,
    object_id INT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_value VARCHAR(500) NOT NULL,
    original_value VARCHAR(500) DEFAULT NULL,
    original_type VARCHAR(50) DEFAULT NULL,
    correction_type ENUM('none','value_edit','type_change',
                         'both','rejected','approved') DEFAULT 'none',
    training_exported TINYINT(1) DEFAULT 0,
    confidence DECIMAL(5,4) DEFAULT 1.0000,
    status VARCHAR(50) DEFAULT 'pending',
    linked_actor_id INT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_extraction (extraction_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),
    INDEX idx_type (entity_type),
    FOREIGN KEY (extraction_id) 
        REFERENCES ahg_ner_extraction(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### ahg_ner_settings
```sql
CREATE TABLE ahg_ner_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
               ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Default Settings

| Key | Value | Description |
|-----|-------|-------------|
| api_url | http://localhost:5004/ai/v1 | AI service endpoint |
| api_key | | API authentication |
| api_timeout | 60 | Timeout seconds |
| ner_enabled | 1 | NER active |
| extract_from_pdf | 1 | PDF extraction |
| auto_extract_on_upload | 0 | Auto-run on create |
| require_review | 1 | Manual review required |
| processing_mode | job | hybrid/job |
| summarizer_enabled | 1 | Summarization active |
| summary_field | scope_and_content | Target field |
| summarizer_min_length | 100 | Min chars |
| summarizer_max_length | 500 | Max chars |
| spellcheck_enabled | 0 | Spell check active |
| spellcheck_language | en_ZA | Dictionary |

---

## 3. Plugin Structure
```
ahgNerPlugin/
├── config/
│   └── ahgNerPluginConfiguration.class.php
├── lib/
│   ├── task/
│   │   ├── nerExtractTask.class.php
│   │   ├── nerSummarizeTask.class.php
│   │   ├── nerSpellcheckTask.class.php
│   │   ├── nerInstallTask.class.php
│   │   ├── nerUninstallTask.class.php
│   │   └── nerSyncTask.class.php
│   ├── job/
│   │   └── arNerExtractJob.class.php
│   └── form/
│       └── AhgNerSettingsForm.class.php
├── modules/
│   ├── ahgNerSettings/
│   │   ├── actions/
│   │   │   └── actions.class.php
│   │   └── templates/
│   │       └── indexSuccess.php
│   └── ahgNerReview/
│       ├── actions/
│       │   └── actions.class.php
│       └── templates/
│           └── indexSuccess.php
├── data/
│   └── install.sql
└── extension.json
```

### Plugin Configuration
```php
<?php
// config/ahgNerPluginConfiguration.class.php

class ahgNerPluginConfiguration extends sfPluginConfiguration
{
    public static $summary = 'AI-powered Named Entity Recognition';

    public function initialize()
    {
        $this->dispatcher->connect(
            'routing.load_configuration', 
            [$this, 'loadRoutes']
        );
    }

    public function loadRoutes(sfEvent $event)
    {
        $routing = $event->getSubject();
        
        $routing->prependRoute('ner_settings', new sfRoute(
            '/admin/ahg-settings/ai-services',
            ['module' => 'ahgNerSettings', 'action' => 'index']
        ));
        
        $routing->prependRoute('ner_review', new sfRoute(
            '/ner/review',
            ['module' => 'ahgNerReview', 'action' => 'index']
        ));
    }
}
```

---

## 4. AI API Integration

### API Endpoints

#### NER Extraction
```
POST /ai/v1/ner/extract
Content-Type: application/json
X-API-Key: <api_key>

Request:
{
    "text": "Nelson Mandela was president of South Africa..."
}

Response:
{
    "success": true,
    "entities": {
        "PERSON": ["Nelson Mandela"],
        "GPE": ["South Africa"],
        "ORG": ["ANC"],
        "DATE": ["1994"]
    },
    "entity_count": 4,
    "processing_time_ms": 45
}
```

#### Summarization
```
POST /ai/v1/summarize
Content-Type: application/json
X-API-Key: <api_key>

Request:
{
    "text": "<document text>",
    "min_length": 100,
    "max_length": 500
}

Response:
{
    "success": true,
    "summary": "This document describes...",
    "original_length": 5000,
    "summary_length": 350
}
```

### PHP API Client
```php
protected function callNerApi($objectId, $text, $settings)
{
    $apiUrl = rtrim($settings['api_url'], '/') . '/ner/extract';
    
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['text' => $text]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . ($settings['api_key'] ?? '')
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)($settings['api_timeout'] ?? 60)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("API error: HTTP $httpCode");
    }

    return json_decode($response, true);
}
```

---

## 5. CLI Tasks

### nerExtractTask
```php
<?php
class nerExtractTask extends sfBaseTask
{
    protected function configure()
    {
        $this->addOptions([
            new sfCommandOption('object', null, 
                sfCommandOption::PARAMETER_OPTIONAL),
            new sfCommandOption('repository', null, 
                sfCommandOption::PARAMETER_OPTIONAL),
            new sfCommandOption('all', null, 
                sfCommandOption::PARAMETER_NONE),
            new sfCommandOption('limit', null, 
                sfCommandOption::PARAMETER_OPTIONAL, '', 100),
            new sfCommandOption('dry-run', null, 
                sfCommandOption::PARAMETER_NONE),
            new sfCommandOption('with-pdf', null, 
                sfCommandOption::PARAMETER_NONE),
        ]);
        
        $this->namespace = 'ner';
        $this->name = 'extract';
    }

    protected function execute($arguments = [], $options = [])
    {
        sfContext::createInstance($this->configuration);
        require_once sfConfig::get('sf_root_dir') 
            . '/atom-framework/bootstrap.php';

        $settings = $this->getSettings();
        $withPdf = $options['with-pdf'] 
            || ($settings['extract_from_pdf'] ?? '0') === '1';

        $objectIds = $this->getObjectsToProcess($options);
        
        foreach ($objectIds as $objectId) {
            $this->processObject($objectId, $settings, $withPdf);
        }
    }
}
```

### Task Flow
```
┌─────────────────────────────────────────────────────────────────────────┐
│                        nerExtractTask Flow                               │
└─────────────────────────────────────────────────────────────────────────┘

  ┌──────────────┐
  │    Start     │
  └──────┬───────┘
         │
         ▼
  ┌──────────────┐
  │ Load Settings│
  └──────┬───────┘
         │
         ▼
  ┌──────────────┐
  │ Get Objects  │◄──── Based on --object, --repository,
  │ to Process   │      --all, --limit options
  └──────┬───────┘
         │
         ▼
  ┌──────────────┐     Yes     ┌──────────────┐
  │  --dry-run?  │────────────►│ Show & Exit  │
  └──────┬───────┘             └──────────────┘
         │ No
         ▼
  ┌──────────────┐
  │  For each    │◄────────────────────────────┐
  │  object      │                             │
  └──────┬───────┘                             │
         │                                     │
         ▼                                     │
  ┌──────────────┐                             │
  │Extract Text  │◄──── Metadata + PDF         │
  └──────┬───────┘      (if enabled)           │
         │                                     │
         ▼                                     │
  ┌──────────────┐                             │
  │ Call NER API │                             │
  └──────┬───────┘                             │
         │                                     │
         ▼                                     │
  ┌──────────────┐                             │
  │Store Entities│                             │
  └──────┬───────┘                             │
         │                                     │
         ▼                                     │
  ┌──────────────┐     Yes                     │
  │ More objects?│─────────────────────────────┘
  └──────┬───────┘
         │ No
         ▼
  ┌──────────────┐
  │     Done     │
  └──────────────┘
![wireframe](./images/wireframes/wireframe_85871eee.png)
```

---

## 6. Gearman Jobs

### arNerExtractJob
```php
<?php
class arNerExtractJob extends arBaseJob
{
    protected $extraRequiredParameters = ['objectId'];

    public function runJob($parameters)
    {
        $objectId = $parameters['objectId'];
        $runNer = $parameters['runNer'] ?? true;
        $runSummarize = $parameters['runSummarize'] ?? false;

        require_once sfConfig::get('sf_root_dir') 
            . '/atom-framework/bootstrap.php';

        $settings = $this->getSettings();
        $io = QubitInformationObject::getById($objectId);
        
        if ($runNer) {
            $text = $this->extractText($io);
            $this->runNer($objectId, $text, $settings);
        }

        if ($runSummarize) {
            $this->runSummarize($io, $settings);
        }

        return true;
    }
}
```

### Queue Job
```php
// Queue a job for background processing
$job = new QubitJob();
$job->name = 'arNerExtractJob';
$job->setParameter('objectId', $objectId);
$job->setParameter('runNer', true);
$job->setParameter('runSummarize', false);
$job->save();
```

---

## 7. Configuration

### Settings Form
```php
<?php
class AhgNerSettingsForm extends sfForm
{
    public function configure()
    {
        $this->setWidgets([
            'api_url' => new sfWidgetFormInputText(),
            'api_key' => new sfWidgetFormInputText(),
            'api_timeout' => new sfWidgetFormInputText(),
            'processing_mode' => new sfWidgetFormSelect([
                'choices' => ['hybrid' => 'Hybrid', 'job' => 'Job']
            ]),
            'ner_enabled' => new sfWidgetFormInputCheckbox(),
            'extract_from_pdf' => new sfWidgetFormInputCheckbox(),
            'auto_extract_on_upload' => new sfWidgetFormInputCheckbox(),
            'require_review' => new sfWidgetFormInputCheckbox(),
            'summarizer_enabled' => new sfWidgetFormInputCheckbox(),
            'summary_field' => new sfWidgetFormSelect([
                'choices' => [
                    'scope_and_content' => 'Scope and Content',
                    'abstract' => 'Abstract'
                ]
            ]),
            // ... more fields
        ]);
    }
}
```

### Settings Action
```php
<?php
class ahgNerSettingsActions extends sfActions
{
    public function executeIndex(sfWebRequest $request)
    {
        $this->form = new AhgNerSettingsForm();
        $this->loadSettings();

        if ($request->isMethod('post')) {
            $this->form->bind($request->getParameter('ner_settings'));
            if ($this->form->isValid()) {
                $this->saveSettings($this->form->getValues());
                $this->redirect('ner_settings');
            }
        }
    }

    protected function loadSettings()
    {
        $settings = [];
        $rows = DB::table('ahg_ner_settings')->get();
        foreach ($rows as $row) {
            $settings[$row->setting_key] = $row->setting_value;
        }
        $this->form->setDefaults($settings);
    }

    protected function saveSettings($values)
    {
        foreach ($values as $key => $value) {
            DB::table('ahg_ner_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }
    }
}
```

---

## 8. Installation

### Requirements

- AtoM 2.8+ or 2.10+
- PHP 8.1+
- MySQL 8.0+
- atom-framework installed
- AI Service running (Python Flask)

### Install Steps
```bash
# 1. Enable plugin
php bin/atom extension:enable ahgNerPlugin

# 2. Run install task
php symfony ner:install

# 3. Clear cache
php symfony cc

# 4. Configure settings
# Navigate to /admin/ahg-settings/ai-services
```

### Uninstall
```bash
# 1. Run uninstall task (removes tables)
php symfony ner:uninstall

# 2. Disable plugin
php bin/atom extension:disable ahgNerPlugin

# 3. Clear cache
php symfony cc
```

---

## 9. PII Detection Integration

The NER plugin provides the foundation for PII (Personally Identifiable Information) detection in `ahgPrivacyPlugin`.

### How It Works

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    NER + PII Integration                                 │
└─────────────────────────────────────────────────────────────────────────┘

┌───────────────────┐          ┌───────────────────┐
│   ahgNerPlugin    │          │  ahgPrivacyPlugin │
├───────────────────┤          ├───────────────────┤
│                   │          │                   │
│  Entity Types:    │◄────────►│ PiiDetectionService│
│  • PERSON         │  shared  │                   │
│  • ORG            │  tables  │  Additional PII:  │
│  • GPE            │          │  • SA_ID          │
│  • DATE           │          │  • NG_NIN         │
│                   │          │  • EMAIL          │
│  Tables:          │          │  • PHONE_SA       │
│  ahg_ner_extraction│         │  • BANK_ACCOUNT   │
│  ahg_ner_entity   │          │  • CREDIT_CARD    │
│                   │          │  • TAX_NUMBER     │
└───────────────────┘          └───────────────────┘
![wireframe](./images/wireframes/wireframe_7fdbb7a2.png)
```

### Shared Tables

The PII Detection system uses the same database tables as NER:

| Table | Usage |
|-------|-------|
| `ahg_ner_extraction` | Stores scan records (backend_used = 'pii_detector') |
| `ahg_ner_entity` | Stores detected PII entities with risk flags |

### Entity Type Mapping

| NER Entity | PII Risk | Description |
|------------|----------|-------------|
| PERSON | Medium | Names extracted by spaCy |
| ORG | Low | Organizations via spaCy |
| GPE | Low | Places via spaCy |
| DATE | Low | Dates via spaCy |
| SA_ID | High | SA ID numbers (regex + Luhn) |
| NG_NIN | High | Nigerian NIN (regex) |
| PASSPORT | High | Passport numbers (regex) |
| EMAIL | Medium | Email addresses (regex) |
| PHONE_SA | Medium | SA phone numbers (regex) |
| CREDIT_CARD | Critical | Credit cards (regex + Luhn) |

### CLI Commands

```bash
# Standard NER extraction
php symfony ner:extract --object=123

# PII-focused scan (includes NER + regex patterns)
php symfony privacy:scan-pii --id=123

# Batch PII scan
php symfony privacy:scan-pii --limit=100

# PII statistics
php symfony privacy:scan-pii --stats
```

### Distinguishing NER vs PII Scans

```sql
-- NER extractions only
SELECT * FROM ahg_ner_extraction WHERE backend_used = 'local';

-- PII scans only
SELECT * FROM ahg_ner_extraction WHERE backend_used = 'pii_detector';

-- All extractions
SELECT * FROM ahg_ner_extraction;
```

---

## Appendix: Useful Queries
```sql
-- Check processing status
SELECT 
    (SELECT COUNT(*) FROM ahg_ner_extraction) as processed,
    (SELECT COUNT(*) FROM ahg_ner_entity) as entities,
    (SELECT COUNT(*) FROM information_object WHERE id != 1) - 
    (SELECT COUNT(*) FROM ahg_ner_extraction) as pending;

-- Entity type distribution
SELECT entity_type, COUNT(*) as count 
FROM ahg_ner_entity 
GROUP BY entity_type;

-- Top entities by frequency
SELECT entity_value, entity_type, COUNT(*) as occurrences
FROM ahg_ner_entity
GROUP BY entity_value, entity_type
ORDER BY occurrences DESC
LIMIT 20;

-- Pending review
SELECT COUNT(*) FROM ahg_ner_entity WHERE status = 'pending';
```

---

*© The Archive and Heritage Group | January 2026*
