# ahgLibraryPlugin - Technical Documentation

**Version:** 1.9.14
**Category:** Sector (Library/Bibliographic)
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

Library and bibliographic cataloging module with MARC-inspired fields, multi-source ISBN lookup, automatic book cover retrieval, and comprehensive reporting. Designed for GLAM institutions managing book collections alongside archival materials.

---

## Architecture

```
+---------------------------------------------------------------------+
|                        ahgLibraryPlugin                             |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                    Symfony Controllers                        |  |
|  |  library/      - browse, view, edit, add actions              |  |
|  |  isbn/         - ISBN lookup test/stats                       |  |
|  |  libraryReports/ - catalogue, creators, subjects reports      |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                      Service Layer                            |  |
|  |  +-------------------+  +------------------+  +--------------+ |  |
|  |  | LibraryService    |  | WorldCatService  |  | BookCover    | |  |
|  |  | - CRUD ops        |  | - ISBN lookup    |  | Service      | |  |
|  |  | - validation      |  | - multi-source   |  | - cover URLs | |  |
|  |  | - ES indexing     |  | - caching        |  | - proxy      | |  |
|  |  +-------------------+  +------------------+  +--------------+ |  |
|  |                              |                                 |  |
|  |  +-------------------+  +------------------+                   |  |
|  |  | IsbnMetadata      |  | LanguageService  |                   |  |
|  |  | Mapper            |  | (from framework) |                   |  |
|  |  +-------------------+  +------------------+                   |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                   Repository Layer                            |  |
|  |  +------------------------+  +---------------------------+    |  |
|  |  | LibraryRepository      |  | IsbnLookupRepository      |    |  |
|  |  | - library_item CRUD    |  | - ISBN cache management   |    |  |
|  |  | - creators/subjects    |  | - lookup audit logging    |    |  |
|  |  | - copies/holdings      |  | - provider configuration  |    |  |
|  |  | - search/statistics    |  | - statistics              |    |  |
|  |  +------------------------+  +---------------------------+    |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                    Database Tables                            |  |
|  |  library_item, library_item_creator, library_item_subject     |  |
|  |  atom_isbn_cache, atom_isbn_provider, atom_isbn_lookup_audit  |  |
|  |  atom_library_cover_queue                                     |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+----------------------------------+     +-------------------------------+
|         library_item             |     |      atom_isbn_cache          |
+----------------------------------+     +-------------------------------+
| PK id BIGINT                     |     | PK id INT                     |
| FK information_object_id INT     |     |    isbn VARCHAR(13) UNIQUE    |
|                                  |     |    isbn_10 VARCHAR(10)        |
| -- Classification --             |     |    isbn_13 VARCHAR(13)        |
|    material_type VARCHAR(50)     |     |    metadata JSON              |
|    call_number VARCHAR(100)      |     |    source VARCHAR(50)         |
|    classification_scheme         |     |    oclc_number VARCHAR(20)    |
|    dewey_decimal                 |     |    expires_at TIMESTAMP       |
|    shelf_location                |     |    created_at, updated_at     |
|    copy_number, volume           |     +-------------------------------+
|                                  |
| -- Identifiers --                |     +-------------------------------+
|    isbn VARCHAR(17)              |     |      atom_isbn_provider       |
|    issn VARCHAR(9)               |     +-------------------------------+
|    lccn, oclc_number, doi        |     | PK id INT                     |
|    barcode VARCHAR(50)           |     |    name VARCHAR(100)          |
|    openlibrary_id, goodreads_id  |     |    slug VARCHAR(100) UNIQUE   |
|    openlibrary_url               |     |    api_endpoint VARCHAR(500)  |
|                                  |     |    api_key_setting            |
| -- Publication --                |     |    priority INT               |
|    publisher VARCHAR(255)        |     |    enabled TINYINT            |
|    publication_place             |     |    rate_limit_per_minute      |
|    publication_date              |     |    response_format ENUM       |
|    edition, edition_statement    |     +-------------------------------+
|    series_title, series_number   |
|                                  |     +-------------------------------+
| -- Physical Description --       |     |   atom_isbn_lookup_audit      |
|    pagination VARCHAR(100)       |     +-------------------------------+
|    dimensions VARCHAR(100)       |     | PK id INT                     |
|    physical_details TEXT         |     |    isbn VARCHAR(13)           |
|    language                      |     | FK user_id INT                |
|                                  |     | FK information_object_id INT  |
| -- Notes --                      |     |    source VARCHAR(50)         |
|    summary TEXT                  |     |    success TINYINT            |
|    contents_note TEXT            |     |    fields_populated JSON      |
|    general_note TEXT             |     |    error_message TEXT         |
|    bibliography_note TEXT        |     |    lookup_time_ms INT         |
|                                  |     |    ip_address VARCHAR(45)     |
| -- Serials --                    |     |    created_at TIMESTAMP       |
|    frequency VARCHAR(50)         |     +-------------------------------+
|    publication_start_date DATE   |
|    publication_end_date DATE     |     +-------------------------------+
|    publication_status            |     |  atom_library_cover_queue     |
|                                  |     +-------------------------------+
| -- Circulation --                |     | PK id INT                     |
|    total_copies SMALLINT         |     | FK information_object_id INT  |
|    available_copies SMALLINT     |     |    isbn VARCHAR(20)           |
|    circulation_status VARCHAR    |     |    status ENUM                |
|                                  |     |    attempts TINYINT           |
| -- Metadata --                   |     |    error_message TEXT         |
|    cataloging_source             |     |    created_at, processed_at   |
|    cataloging_rules              |     +-------------------------------+
|    created_at, updated_at        |
+----------------------------------+
         |
         | 1:N
         v
+----------------------------------+     +-------------------------------+
|     library_item_creator         |     |     library_item_subject      |
+----------------------------------+     +-------------------------------+
| PK id BIGINT                     |     | PK id BIGINT                  |
| FK library_item_id BIGINT        |     | FK library_item_id BIGINT     |
|    name VARCHAR(500)             |     |    heading VARCHAR(500)       |
|    role VARCHAR(50)              |     |    subject_type VARCHAR(50)   |
|    sort_order INT                |     |    source VARCHAR(100)        |
|    authority_uri VARCHAR(500)    |     |    uri VARCHAR(500)           |
|    created_at TIMESTAMP          |     |    created_at TIMESTAMP       |
+----------------------------------+     +-------------------------------+
```

### SQL Schema - Core Tables

```sql
CREATE TABLE library_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT UNSIGNED NOT NULL,
    material_type VARCHAR(50) NOT NULL DEFAULT 'monograph',
    subtitle VARCHAR(500),
    responsibility_statement VARCHAR(500),

    -- Classification
    call_number VARCHAR(100),
    classification_scheme VARCHAR(50),
    classification_number VARCHAR(100),
    dewey_decimal VARCHAR(50),
    cutter_number VARCHAR(50),
    shelf_location VARCHAR(100),
    copy_number VARCHAR(20),
    volume_designation VARCHAR(100),

    -- Identifiers
    isbn VARCHAR(17),
    issn VARCHAR(9),
    lccn VARCHAR(50),
    oclc_number VARCHAR(50),
    openlibrary_id VARCHAR(50),
    goodreads_id VARCHAR(50),
    librarything_id VARCHAR(50),
    openlibrary_url VARCHAR(500),
    ebook_preview_url VARCHAR(500),
    cover_url VARCHAR(500),
    cover_url_original VARCHAR(500),
    doi VARCHAR(255),
    barcode VARCHAR(50),

    -- Publication
    publisher VARCHAR(255),
    publication_place VARCHAR(255),
    publication_date VARCHAR(100),
    copyright_date VARCHAR(50),
    edition VARCHAR(255),
    edition_statement VARCHAR(500),
    printing VARCHAR(100),
    series_title VARCHAR(500),
    series_number VARCHAR(50),
    series_issn VARCHAR(9),
    subseries_title VARCHAR(500),

    -- Physical Description
    pagination VARCHAR(100),
    dimensions VARCHAR(100),
    physical_details TEXT,
    language VARCHAR(100),
    accompanying_material TEXT,

    -- Notes
    summary TEXT,
    contents_note TEXT,
    general_note TEXT,
    bibliography_note TEXT,
    target_audience TEXT,
    system_requirements TEXT,
    binding_note TEXT,

    -- Serials
    frequency VARCHAR(50),
    former_frequency VARCHAR(100),
    numbering_peculiarities VARCHAR(255),
    publication_start_date DATE,
    publication_end_date DATE,
    publication_status VARCHAR(20),

    -- Circulation
    total_copies SMALLINT UNSIGNED DEFAULT 1,
    available_copies SMALLINT UNSIGNED DEFAULT 1,
    circulation_status VARCHAR(30) DEFAULT 'available',

    -- Metadata
    cataloging_source VARCHAR(100),
    cataloging_rules VARCHAR(20),
    encoding_level VARCHAR(20),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE library_item_creator (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    library_item_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(500) NOT NULL,
    role VARCHAR(50) DEFAULT 'author',
    sort_order INT DEFAULT 0,
    authority_uri VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_library_item_id (library_item_id),
    INDEX idx_name (name(100)),
    FOREIGN KEY (library_item_id) REFERENCES library_item(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE library_item_subject (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    library_item_id BIGINT UNSIGNED NOT NULL,
    heading VARCHAR(500) NOT NULL,
    subject_type VARCHAR(50) DEFAULT 'topic',
    source VARCHAR(100),
    uri VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_library_item_id (library_item_id),
    INDEX idx_heading (heading(100)),
    FOREIGN KEY (library_item_id) REFERENCES library_item(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### SQL Schema - ISBN Lookup Tables

```sql
CREATE TABLE atom_isbn_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(13) NOT NULL UNIQUE,
    isbn_10 VARCHAR(10),
    isbn_13 VARCHAR(13),
    metadata JSON NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'worldcat',
    oclc_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,

    INDEX idx_isbn_10 (isbn_10),
    INDEX idx_isbn_13 (isbn_13),
    INDEX idx_oclc (oclc_number),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_isbn_provider (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    api_endpoint VARCHAR(500) NOT NULL,
    api_key_setting VARCHAR(100),
    priority INT NOT NULL DEFAULT 100,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    rate_limit_per_minute INT UNSIGNED,
    response_format ENUM('json','xml','marcxml') DEFAULT 'json',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_enabled_priority (enabled, priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_isbn_lookup_audit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(13) NOT NULL,
    user_id INT,
    information_object_id INT,
    source VARCHAR(50) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    fields_populated JSON,
    error_message TEXT,
    lookup_time_ms INT UNSIGNED,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_isbn (isbn),
    INDEX idx_user (user_id),
    INDEX idx_io (information_object_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE SET NULL,
    FOREIGN KEY (information_object_id) REFERENCES information_object(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE atom_library_cover_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT UNSIGNED NOT NULL,
    isbn VARCHAR(20) NOT NULL,
    status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_io_id (information_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Material Types

| Type | Code | Description |
|------|------|-------------|
| Monograph | `monograph` | Single published work (book) |
| Serial | `serial` | Periodical publications |
| Volume | `volume` | Individual volume of multi-volume work |
| Issue | `issue` | Single issue of a serial |
| Chapter | `chapter` | Chapter within a monograph |
| Article | `article` | Article within a serial |
| Manuscript | `manuscript` | Unpublished handwritten/typed work |
| Map | `map` | Cartographic material |
| Pamphlet | `pamphlet` | Brief printed work |
| Score | `score` | Musical score |
| Electronic | `electronic` | Electronic resource |

---

## Classification Schemes

| Scheme | Code | Description |
|--------|------|-------------|
| Dewey | `dewey` | Dewey Decimal Classification (DDC) |
| LCC | `lcc` | Library of Congress Classification |
| UDC | `udc` | Universal Decimal Classification |
| Bliss | `bliss` | Bliss Bibliographic Classification |
| Colon | `colon` | Colon Classification (Ranganathan) |
| Custom | `custom` | Local/custom scheme |

---

## Creator Roles (MARC Relator Codes)

| Role | MARC Code | Description |
|------|-----------|-------------|
| author | aut | Primary author |
| editor | edt | Editor |
| translator | trl | Translator |
| illustrator | ill | Illustrator |
| compiler | com | Compiler |
| contributor | ctb | Contributor |
| author_of_introduction | aui | Author of introduction |
| author_of_afterword | aft | Author of afterword |
| photographer | pht | Photographer |
| composer | cmp | Composer |

---

## Service Methods

### LibraryService

```php
namespace ahgLibraryPlugin;

class LibraryService
{
    // Singleton
    public static function getInstance(?string $culture = null): self;

    // CRUD Operations
    public function getByObjectId(int $objectId): ?LibraryItem;
    public function getById(int $id): ?LibraryItem;
    public function save(int $objectId, array $data): LibraryItem;
    public function delete(int $objectId): bool;

    // Validation
    public function validate(array $data): array;
    public function validateIsbn(string $isbn): bool;
    public function validateIssn(string $issn): bool;

    // ISBN Utilities
    public function cleanIsbn(string $isbn): string;
    public function formatIsbn(string $isbn): string;
    public function isbn10To13(string $isbn10): ?string;

    // ISSN Utilities
    public function cleanIssn(string $issn): string;
    public function formatIssn(string $issn): string;

    // ISBN Lookup (Open Library)
    public function lookupIsbn(string $isbn): ?array;

    // Search & Stats
    public function search(array $params = []): array;
    public function getStatistics(): array;

    // Form Options
    public function getFormOptions(): array;
    public function getRelatorCode(string $role): ?string;

    // Citation Generation
    public function generateCitation(LibraryItem $item, string $title, string $style = 'apa'): string;
}
```

### WorldCatService

```php
namespace ahgLibraryPlugin\Service;

class WorldCatService
{
    public function __construct(
        IsbnLookupRepository $repository,
        ?Logger $logger = null,
        array $config = []
    );

    // Primary lookup method - tries providers in priority order
    public function lookup(string $isbn, ?int $userId = null, ?int $objectId = null): array;

    // ISBN validation
    public function validateIsbn(string $isbn): bool;
}

// Response format:
[
    'success' => true,
    'data' => [
        'title' => 'Book Title',
        'subtitle' => 'Subtitle',
        'authors' => [['name' => 'Author Name', 'url' => '...']],
        'publishers' => ['Publisher Name'],
        'publish_date' => '2024',
        'number_of_pages' => 350,
        'subjects' => [['name' => 'Subject', 'url' => '...']],
        'isbn_10' => '0123456789',
        'isbn_13' => '9780123456789',
        'lccn' => '...',
        'oclc_number' => '...',
        'description' => 'Book description...',
        'cover_url' => 'https://...',
    ],
    'source' => 'openlibrary',
    'cached' => false,
]
```

### BookCoverService

```php
namespace ahgLibraryPlugin\Service;

class BookCoverService
{
    public const SIZE_SMALL = 'S';
    public const SIZE_MEDIUM = 'M';
    public const SIZE_LARGE = 'L';

    public static function getOpenLibraryUrl(string $isbn, string $size = 'M'): string;
    public static function getAllSizes(string $isbn): array;
    public static function getByOclc(string $oclc, string $size = 'M'): string;
    public static function imgTag(string $isbn, string $size = 'M', array $attributes = []): string;
}
```

### IsbnLookupRepository

```php
namespace ahgLibraryPlugin\Repository;

class IsbnLookupRepository
{
    // Cache operations (7-day expiry)
    public function getCached(string $isbn): ?array;
    public function cache(string $isbn, array $metadata, string $source = 'worldcat'): int;
    public function clearExpired(): int;

    // Audit trail
    public function audit(array $data): int;

    // Provider management
    public function getProviders(): Collection;
    public function getProvider(string $slug): ?object;

    // Statistics
    public function getStatistics(?int $days = 30): array;
    public function getRecentLookups(int $limit = 20): Collection;
}
```

### LibraryRepository

```php
class LibraryRepository
{
    public static function getInstance(?string $culture = null): self;

    // Find operations
    public function find(int $id): ?LibraryItem;
    public function findByObjectId(int $objectId): ?LibraryItem;
    public function findByIsbn(string $isbn): ?LibraryItem;
    public function findByBarcode(string $barcode): ?LibraryItem;
    public function findByCallNumber(string $callNumber): array;

    // CRUD
    public function save(LibraryItem $item): LibraryItem;
    public function delete(int $id): bool;
    public function deleteByObjectId(int $objectId): bool;

    // Related data
    public function getCreators(int $itemId): array;
    public function saveCreators(int $itemId, array $creators): void;
    public function getSubjects(int $itemId): array;
    public function saveSubjects(int $itemId, array $subjects): void;
    public function getCopies(int $itemId): array;
    public function saveCopies(int $itemId, array $copies): void;
    public function getSerialHoldings(int $itemId): array;
    public function saveSerialHoldings(int $itemId, array $holdings): void;

    // Search & stats
    public function search(array $params = []): array;
    public function getStatistics(): array;
}
```

---

## Routes

### Library Module Routes

| Route Name | URL Pattern | Controller/Action | Description |
|------------|-------------|-------------------|-------------|
| library_browse | /library | library/browse | Browse all library items |
| library_add | /library/add | library/edit | Add new library item |
| library_view | /library/:slug | library/index | View library item |
| library_edit | /library/:slug/edit | library/edit | Edit library item |
| library_isbn_lookup | /library/isbnLookup | library/isbnLookup | AJAX ISBN lookup |
| library_cover_proxy | /library/cover/:isbn | library/coverProxy | Proxy book covers |
| library_isbn_providers | /library/isbn-providers | library/isbnProviders | Manage ISBN providers |
| library_isbn_provider_edit | /library/isbn-provider/edit/:id | library/isbnProviderEdit | Edit provider |
| library_isbn_provider_toggle | /library/isbn-provider/toggle/:id | library/isbnProviderToggle | Enable/disable provider |
| library_isbn_provider_delete | /library/isbn-provider/delete/:id | library/isbnProviderDelete | Delete provider |
| library_api_isbn | /api/library/isbn/:isbn | library/apiIsbnLookup | API ISBN lookup |

### ISBN Module Routes

| Route Name | URL Pattern | Controller/Action | Description |
|------------|-------------|-------------------|-------------|
| isbn_lookup | /isbn/lookup | isbn/lookup | ISBN lookup page |
| isbn_test | /isbn/test | isbn/test | Test ISBN lookup |
| isbn_api_test | /isbn/apiTest | isbn/apiTest | API test endpoint |
| isbn_stats | /admin/isbn/stats | isbn/stats | Lookup statistics |

### Library Reports Routes

| Route Name | URL Pattern | Controller/Action | Description |
|------------|-------------|-------------------|-------------|
| libraryReports_index | /libraryReports | libraryReports/index | Reports dashboard |
| libraryReports_catalogue | /libraryReports/catalogue | libraryReports/catalogue | Catalogue report |
| libraryReports_creators | /libraryReports/creators | libraryReports/creators | Creators report |
| libraryReports_subjects | /libraryReports/subjects | libraryReports/subjects | Subjects report |
| libraryReports_publishers | /libraryReports/publishers | libraryReports/publishers | Publishers report |
| libraryReports_callNumbers | /libraryReports/callNumbers | libraryReports/callNumbers | Call numbers report |
| libraryReports_exportCsv | /libraryReports/exportCsv | libraryReports/exportCsv | Export CSV |

---

## CLI Commands

### library:process-covers

Process pending book cover downloads from Open Library.

```bash
# Process default batch (10 covers)
php symfony library:process-covers

# Process larger batch
php symfony library:process-covers --limit=50
```

**Process Flow:**
1. Queries `atom_library_cover_queue` for pending items
2. Downloads cover from Open Library (Large size)
3. Validates image size (>1000 bytes to avoid placeholder)
4. Creates QubitDigitalObject with the cover
5. Updates `library_item.cover_url`
6. Marks queue item as completed

---

## ISBN Lookup Providers

The plugin supports multiple ISBN metadata providers, tried in priority order:

| Provider | Slug | API Key Required | Response Format |
|----------|------|------------------|-----------------|
| Open Library | `openlibrary` | No | JSON |
| Google Books | `googlebooks` | Optional | JSON |
| WorldCat | `worldcat` | Yes (OCLC) | MARCXML |

### Provider Configuration

Providers are stored in `atom_isbn_provider` table:

```sql
INSERT INTO atom_isbn_provider (name, slug, api_endpoint, priority, enabled, response_format) VALUES
('Open Library', 'openlibrary', 'https://openlibrary.org/api/books', 10, 1, 'json'),
('Google Books', 'googlebooks', 'https://www.googleapis.com/books/v1/volumes', 20, 1, 'json'),
('WorldCat', 'worldcat', 'http://www.worldcat.org/webservices/catalog/content/isbn/', 30, 0, 'marcxml');
```

### Lookup Flow

```
                          +------------------+
                          |  ISBN Lookup     |
                          |  Request         |
                          +--------+---------+
                                   |
                                   v
                          +------------------+
                          |  Check Cache     |
                          |  (7 day expiry)  |
                          +--------+---------+
                                   |
                      +------------+------------+
                      |                         |
                      v                         v
               [Cache Hit]              [Cache Miss]
                      |                         |
                      v                         v
               Return Cached            Try Providers
               Metadata                 in Priority Order
                                              |
                          +-------------------+-------------------+
                          |                   |                   |
                          v                   v                   v
                   Open Library        Google Books           WorldCat
                          |                   |                   |
                          +-------------------+-------------------+
                                              |
                                              v
                                     [First Success]
                                              |
                                              v
                                     Cache Result &
                                     Log to Audit
                                              |
                                              v
                                     Return Metadata
```

---

## Cover Retrieval

### Open Library Cover URLs

Covers are retrieved from Open Library using direct URLs:

```
https://covers.openlibrary.org/b/isbn/{ISBN}-S.jpg  (Small: 45x68)
https://covers.openlibrary.org/b/isbn/{ISBN}-M.jpg  (Medium: 180x270)
https://covers.openlibrary.org/b/isbn/{ISBN}-L.jpg  (Large: 450x675)
```

### Cover Queue Processing

For new records, covers are queued for background processing:

1. Record created with ISBN
2. Entry added to `atom_library_cover_queue`
3. CLI task `library:process-covers` downloads covers
4. Digital object created and attached to information object

For existing records being edited, covers download immediately during save.

---

## Elasticsearch Integration

The plugin updates Elasticsearch index with library-specific fields:

```php
$esDoc = [
    'library_material_type' => $item->material_type,
    'library_call_number' => $item->call_number,
    'library_isbn' => $item->isbn,
    'library_issn' => $item->issn,
    'library_publisher' => $item->publisher,
    'library_publication_date' => $item->publication_date,
    'library_series_title' => $item->series_title,
    'library_edition' => $item->edition,
    'library_circulation_status' => $item->circulation_status,
    'library_creators' => ['Author 1', 'Author 2'],
    'library_primary_creator' => 'Primary Author',
    'library_subjects' => ['Subject 1', 'Subject 2'],
];
```

---

## Audit Trail Integration

The plugin integrates with ahgAuditTrailPlugin for change tracking:

```php
// Logged actions
'create' - New library item created
'update' - Library item modified
'delete' - Library item deleted

// Fields tracked
'identifier', 'title', 'scope_and_content',
'material_type', 'subtitle', 'isbn', 'issn',
'publisher', 'publication_place', 'publication_date',
'edition', 'call_number', 'series_title', 'language', 'summary'
```

---

## Level of Description Terms

The plugin installs library-specific level of description terms:

| Term | Sector | Display Order |
|------|--------|---------------|
| Book | library | 10 |
| Monograph | library | 20 |
| Periodical | library | 30 |
| Journal | library | 40 |
| Article | library | 45 |
| Manuscript | library | 50 |
| Document | library, dam | 60 |

---

## Configuration

Plugin settings are managed via the admin interface and stored in the database.

### ISBN Provider Settings

Managed via `/library/isbn-providers`:
- Enable/disable providers
- Set priority order
- Configure API keys (for WorldCat)

### Display Standard

The plugin creates a "Library (MARC-inspired)" display standard term in taxonomy 70, used to identify library records.

---

## Reports

### Available Reports

| Report | Description | Filters |
|--------|-------------|---------|
| Catalogue | Full catalogue listing | material_type, status, search, call_number |
| Creators | Author/creator index | role, search |
| Subjects | Subject heading index | subject_type, source, search |
| Publishers | Publisher statistics | None |
| Call Numbers | Shelf list by call number | None |

### CSV Export

All reports support CSV export via `/libraryReports/exportCsv?report={type}`.

---

## Dependencies

- **atom-framework**: Laravel Query Builder, base services
- **ahgCorePlugin**: AhgAccessGate for embargo checks, AhgDb initialization
- **ahgAuditTrailPlugin** (optional): Change tracking integration

---

## File Structure

```
ahgLibraryPlugin/
+-- config/
|   +-- ahgLibraryPluginConfiguration.class.php
+-- database/
|   +-- install.sql
+-- lib/
|   +-- Model/
|   |   +-- LibraryItem.php
|   +-- Repository/
|   |   +-- LibraryRepository.php
|   |   +-- IsbnLookupRepository.php
|   +-- Service/
|   |   +-- LibraryService.php
|   |   +-- WorldCatService.php
|   |   +-- BookCoverService.php
|   |   +-- IsbnMetadataMapper.php
|   +-- helper/
|   |   +-- BookCoverHelper.php
|   +-- task/
|       +-- libraryCoverProcessTask.class.php
+-- modules/
|   +-- library/
|   |   +-- actions/
|   |   |   +-- indexAction.class.php
|   |   |   +-- browseAction.class.php
|   |   |   +-- editAction.class.php
|   |   |   +-- addAction.class.php
|   |   |   +-- isbnLookupAction.class.php
|   |   |   +-- coverProxyAction.class.php
|   |   |   +-- isbnProvidersAction.class.php
|   |   |   +-- isbnProviderEditAction.class.php
|   |   |   +-- isbnProviderToggleAction.class.php
|   |   |   +-- isbnProviderDeleteAction.class.php
|   |   +-- templates/
|   |   +-- config/
|   +-- isbn/
|   |   +-- actions/
|   |   +-- templates/
|   +-- libraryReports/
|       +-- actions/
|       +-- templates/
+-- web/
|   +-- js/
|   |   +-- JsBarcode.all.min.js
|   +-- cover-proxy.php
+-- extension.json
```

---

*Part of the AtoM AHG Framework*
